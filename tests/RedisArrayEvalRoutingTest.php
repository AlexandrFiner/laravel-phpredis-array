<?php

namespace LaravelPhpRedisArray\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use LaravelPhpRedisArray\Connections\PhpRedisArrayConnection;
use LaravelPhpRedisArray\RedisServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * RedisArray routes EVAL by its first argument, the script text, so every script
 * ran on one node whatever its keys were. Scripts must run on the node of their key.
 */
class RedisArrayEvalRoutingTest extends TestCase
{
    private const KEYS = 20;

    protected function getPackageProviders($app): array
    {
        return [RedisServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.redis', [
            'client' => 'phpredis',
            'options' => [],
            'arrays' => [
                'shards' => [
                    'servers_range' => [
                        'shard_count' => (int) (getenv('REDIS_SHARD_COUNT') ?: 2),
                        'host_mask' => getenv('REDIS_HOST_MASK') ?: 'redis-{shard}',
                        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                    ],
                ],
            ],
        ]);
        $app['config']->set('cache.stores.shards', ['driver' => 'redis', 'connection' => 'shards']);
        $app['config']->set('cache.default', 'shards');
        $app['config']->set('cache.limiter', 'shards');
        $app['config']->set('cache.prefix', '');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection()->flushdb();
    }

    protected function tearDown(): void
    {
        $this->connection()->flushdb();

        parent::tearDown();
    }

    private function connection(): PhpRedisArrayConnection
    {
        $connection = Redis::connection('shards');
        $this->assertInstanceOf(PhpRedisArrayConnection::class, $connection);

        return $connection;
    }

    /**
     * TTL of the key on the node RedisArray routes the key to.
     */
    private function ttlOnOwnNode(string $key): int
    {
        $client = $this->connection()->client();

        return $client->_instance($client->_target($key))->ttl($key);
    }

    public function test_keys_are_spread_over_more_than_one_node(): void
    {
        $client = $this->connection()->client();
        $nodes = array_map(static fn (int $i) => $client->_target("key-$i"), range(1, self::KEYS));

        $this->assertGreaterThan(1, count(array_unique($nodes)));
    }

    public function test_eval_runs_on_the_node_of_its_key(): void
    {
        foreach (range(1, self::KEYS) as $i) {
            $this->connection()->eval("return redis.call('setex', KEYS[1], 60, ARGV[1])", 1, "key-$i", 'value');

            $this->assertGreaterThan(0, $this->ttlOnOwnNode("key-$i"), "key-$i");
        }
    }

    public function test_evalsha_runs_on_the_node_of_its_key(): void
    {
        foreach (range(1, self::KEYS) as $i) {
            $this->connection()->evalsha("return redis.call('setex', KEYS[1], 60, ARGV[1])", 1, "key-$i", 'value');

            $this->assertGreaterThan(0, $this->ttlOnOwnNode("key-$i"), "key-$i");
        }
    }

    public function test_cache_add_stores_on_the_node_of_its_key(): void
    {
        foreach (range(1, self::KEYS) as $i) {
            $this->assertTrue(Cache::add("key-$i", 'first', 60), "key-$i");
            $this->assertFalse(Cache::add("key-$i", 'second', 60), "key-$i");
            $this->assertSame('first', Cache::get("key-$i"), "key-$i");
        }
    }

    public function test_rate_limiter_counters_expire(): void
    {
        foreach (range(1, self::KEYS) as $i) {
            RateLimiter::hit("key-$i", 60);

            $this->assertGreaterThan(0, $this->ttlOnOwnNode("key-$i"), "key-$i");
            $this->assertGreaterThan(0, $this->ttlOnOwnNode("key-$i:timer"), "key-$i:timer");
        }
    }

    public function test_rate_limiter_blocks_after_max_attempts(): void
    {
        foreach (range(1, self::KEYS) as $i) {
            RateLimiter::hit("key-$i", 60);
            RateLimiter::hit("key-$i", 60);

            $this->assertTrue(RateLimiter::tooManyAttempts("key-$i", 2), "key-$i");
        }
    }

    public function test_lock_is_released_on_the_node_of_its_key(): void
    {
        foreach (range(1, self::KEYS) as $i) {
            $lock = Cache::lock("key-$i", 60);

            $this->assertTrue($lock->get(), "key-$i");
            $this->assertTrue($lock->release(), "key-$i");
            $this->assertTrue(Cache::lock("key-$i", 60)->get(), "key-$i");
        }
    }

    public function test_script_without_keys_runs(): void
    {
        $this->assertSame('value', $this->connection()->eval('return ARGV[1]', 0, 'value'));
        $this->assertSame('value', $this->connection()->evalsha('return ARGV[1]', 0, 'value'));
    }

    public function test_script_with_keys_on_different_nodes_is_rejected(): void
    {
        $client = $this->connection()->client();
        $keys = array_map(static fn (int $i) => "key-$i", range(1, self::KEYS));
        $byNode = [];
        foreach ($keys as $key) {
            $byNode[$client->_target($key)] ??= $key;
        }
        [$first, $second] = array_values($byNode);

        $this->expectException(InvalidArgumentException::class);

        $this->connection()->eval('return 1', 2, $first, $second);
    }
}
