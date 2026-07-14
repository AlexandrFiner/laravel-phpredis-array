<?php

namespace LaravelPhpRedisArray\Tests;

use Illuminate\Support\Facades\Redis;
use LaravelPhpRedisArray\Connections\PhpRedisArrayConnection;
use LaravelPhpRedisArray\RedisServiceProvider;
use Orchestra\Testbench\TestCase;

class RedisManagerTest extends TestCase
{
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
                'options' => [],
                'shards' => [
                    'servers_range' => [
                        'shard_count' => (int) (getenv('REDIS_SHARD_COUNT') ?: 2),
                        'host_mask' => getenv('REDIS_HOST_MASK') ?: 'redis-{shard}',
                        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
                    ],
                ],
            ],
        ]);
    }

    public function test_manager_resolves_an_array_connection(): void
    {
        $connection = Redis::connection('shards');

        $this->assertInstanceOf(PhpRedisArrayConnection::class, $connection);
        $this->assertCount(2, $connection->client()->_hosts());
    }

    public function test_set_and_get_through_the_facade(): void
    {
        $connection = Redis::connection('shards');
        $connection->flushdb();

        $connection->set('foo', 'bar');

        $this->assertSame('bar', $connection->get('foo'));

        $connection->flushdb();
    }
}