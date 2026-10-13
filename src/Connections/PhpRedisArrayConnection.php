<?php

namespace LaravelPhpRedisArray\Connections;

use Illuminate\Redis\Connections\PhpRedisConnection;

class PhpRedisArrayConnection extends PhpRedisConnection
{
    use PacksPhpRedisValues;

    /**
     * The RedisArray client.
     *
     * @var \RedisArray
     *
     * @phpstan-ignore property.phpDocType (the connection wraps RedisArray, Laravel documents the client as Redis)
     */
    protected $client;

    /**
     * RedisArray cannot route phpredis _pack/_serialize for integer arguments.
     *
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, mixed>
     */
    public function pack(array $values): array
    {
        if ($values === []) {
            return $values;
        }

        $values = array_map(
            static fn ($value) => is_int($value) ? (string) $value : $value,
            $values,
        );

        return parent::pack($values);
    }

    /**
     * Flush the selected Redis database on every node.
     *
     * @return void
     */
    public function flushdb()
    {
        $arguments = func_get_args();

        $async = strtoupper((string) ($arguments[0] ?? null)) === 'ASYNC';

        foreach ($this->client->_hosts() as $master) {
            $async
                ? $this->command('rawCommand', [$master, 'flushdb', 'async'])
                : $this->command('flushdb', [$master]);
        }
    }
}
