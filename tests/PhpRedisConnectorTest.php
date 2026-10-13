<?php

namespace LaravelPhpRedisArray\Tests;

use InvalidArgumentException;
use LaravelPhpRedisArray\Connections\PhpRedisArrayConnection;
use LaravelPhpRedisArray\Connectors\PhpRedisConnector;
use PHPUnit\Framework\TestCase;

class PhpRedisConnectorTest extends TestCase
{
    private function connector(): PhpRedisConnector
    {
        return new PhpRedisConnector();
    }

    private function serversRangeConfig(): array
    {
        return [
            'servers_range' => [
                'shard_count' => (int) (getenv('REDIS_SHARD_COUNT') ?: 2),
                'host_mask' => getenv('REDIS_HOST_MASK') ?: 'redis-{shard}',
                'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            ],
        ];
    }

    public function test_servers_range_is_expanded_into_shards(): void
    {
        $connection = $this->connector()->connectToArray($this->serversRangeConfig(), [], []);

        $this->assertInstanceOf(PhpRedisArrayConnection::class, $connection);
        $this->assertCount(2, $connection->client()->_hosts());
    }

    public function test_explicit_servers_are_used(): void
    {
        $config = [
            'servers' => [
                ['host' => 'redis-0', 'port' => 6379],
                ['host' => 'redis-1', 'port' => 6379],
            ],
        ];

        $connection = $this->connector()->connectToArray($config, [], []);

        $this->assertCount(2, $connection->client()->_hosts());
    }

    public function test_empty_servers_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->connector()->connectToArray([], [], []);
    }

    public function test_can_write_and_read_across_shards(): void
    {
        $connection = $this->connector()->connectToArray($this->serversRangeConfig(), [], []);
        $connection->flushdb();

        foreach (range(1, 20) as $i) {
            $connection->set("key:$i", "value:$i");
        }

        foreach (range(1, 20) as $i) {
            $this->assertSame("value:$i", $connection->get("key:$i"));
        }

        $connection->flushdb();

        $this->assertNull($connection->get('key:1'));
    }

    public function test_serialization_and_compression_are_disabled_by_default(): void
    {
        $connection = $this->connector()->connectToArray($this->serversRangeConfig(), [], []);

        $this->assertFalse($connection->serialized());
        $this->assertFalse($connection->compressed());
    }
}
