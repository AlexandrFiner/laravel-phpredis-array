<?php

namespace LaravelPhpRedisArray\Tests;

use Closure;
use Illuminate\Redis\Connections\PhpRedisConnection;
use LaravelPhpRedisArray\Connectors\PhpRedisConnector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RedisException;
use RuntimeException;

/**
 * A read timeout in the middle of a multi-bulk reply makes phpredis (6.2.0, x86_64) throw
 * "protocol error, couldn't parse MULTI-BULK response" but keeps the socket open, so
 * the rest of that reply is read as the answer to the next command. The connection
 * must drop such a client and connect again.
 */
class ReconnectOnRedisExceptionTest extends TestCase
{
    /** @var resource|null */
    private $server;

    private string $log;

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        if (isset($this->log) && is_file($this->log)) {
            unlink($this->log);
        }

        parent::tearDown();
    }

    public static function splitReplies(): array
    {
        $eval = static fn (PhpRedisConnection $connection) => $connection->eval('return 1', 0);
        $pipeline = static fn (PhpRedisConnection $connection) => $connection->pipeline(
            static fn ($pipe) => $pipe->eval('return 1', [], 0)->get('queued'),
        );

        return [
            'command, cut between elements' => [$eval, 'element'],
            'command, cut inside element length' => [$eval, 'length'],
            'pipeline, cut between elements' => [$pipeline, 'element'],
        ];
    }

    #[DataProvider('splitReplies')]
    public function test_connection_is_rebuilt_after_reply_is_cut_by_read_timeout(Closure $call, string $cut): void
    {
        $connection = $this->connectToFakeServer($cut);

        try {
            $call($connection);
            $this->fail('RedisException was not thrown');
        } catch (RedisException) {
        }

        // let the rest of the cut reply reach the old socket
        usleep(600000);

        $this->assertSame('after', $connection->get('after'));
        $this->assertGreaterThan(1, $this->logged('accept'), 'client was not reconnected');
        $this->assertSame(1, $this->logged('EVAL'), 'command was retried');
    }

    private function connectToFakeServer(string $cut): PhpRedisConnection
    {
        $this->log = tempnam(sys_get_temp_dir(), 'fake-redis-');
        $this->server = proc_open(
            [PHP_BINARY, __DIR__ . '/Fixtures/split-reply-server.php', $this->log, $cut],
            [1 => ['pipe', 'w']],
            $pipes,
        );

        $port = (int) fgets($pipes[1]);
        if ($port === 0) {
            throw new RuntimeException('Fake Redis server did not start');
        }

        return (new PhpRedisConnector())->connect([
            'host' => '127.0.0.1',
            'port' => $port,
            'timeout' => 1,
            'read_timeout' => 0.2,
        ], []);
    }

    private function logged(string $line): int
    {
        return count(array_keys(file($this->log, FILE_IGNORE_NEW_LINES), $line, true));
    }
}
