<?php

namespace LaravelPhpRedisArray\Tests;

use LaravelPhpRedisArray\Connections\PhpRedisArrayConnection;
use LaravelPhpRedisArray\Connectors\PhpRedisConnector;
use PHPUnit\Framework\TestCase;

class PhpRedisArrayPackTest extends TestCase
{
    private function connection(): PhpRedisArrayConnection
    {
        return (new PhpRedisConnector())->connectToArray([
            'servers_range' => [
                'shard_count' => (int) (getenv('REDIS_SHARD_COUNT') ?: 2),
                'host_mask' => getenv('REDIS_HOST_MASK') ?: 'redis-{shard}',
                'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            ],
        ], [], []);
    }

    public function test_empty_array_is_returned_untouched(): void
    {
        $this->assertSame([], $this->connection()->pack([]));
    }

    public function test_string_values_are_left_untouched(): void
    {
        $this->assertSame(['foo', 'bar'], $this->connection()->pack(['foo', 'bar']));
    }

    /**
     * Integers would make RedisArray throw "Could not find key" while routing
     * the _pack/_serialize call, so they must be cast to strings first.
     */
    public function test_integer_values_are_cast_to_strings(): void
    {
        $this->assertSame(['1', '2', '3'], $this->connection()->pack([1, 2, 3]));
    }

    public function test_mixed_integer_and_string_values_are_packed(): void
    {
        $this->assertSame(['a', '5', 'b'], $this->connection()->pack(['a', 5, 'b']));
    }

    public function test_string_keys_are_preserved(): void
    {
        $this->assertSame(
            ['first' => '10', 'second' => 'value'],
            $this->connection()->pack(['first' => 10, 'second' => 'value']),
        );
    }
}
