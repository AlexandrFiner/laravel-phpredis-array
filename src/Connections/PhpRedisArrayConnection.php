<?php

namespace LaravelPhpRedisArray\Connections;

use Illuminate\Redis\Connections\PhpRedisConnection;
use InvalidArgumentException;

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
     * Evaluate a script on the node of its keys.
     *
     * RedisArray routes EVAL by its first argument, the script text, so every call
     * of the same script lands on one node whatever its keys are. A script without
     * keys does not depend on the node, so it keeps that routing.
     *
     * @param  string  $script
     * @param  int  $numberOfKeys
     * @param  mixed  ...$arguments
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function eval($script, $numberOfKeys, ...$arguments)
    {
        $keys = array_slice($arguments, 0, (int) $numberOfKeys);

        if ($keys === []) {
            return parent::eval($script, $numberOfKeys, ...$arguments);
        }

        return $this->client->_instance($this->nodeForKeys($keys))->eval($script, $arguments, (int) $numberOfKeys);
    }

    /**
     * Evaluate a script on the node of its keys.
     *
     * SCRIPT LOAD and EVALSHA would be routed to different nodes, and EVAL caches
     * the script on the node it runs on anyway.
     *
     * @param  string  $script
     * @param  int  $numkeys
     * @param  mixed  ...$arguments
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function evalsha($script, $numkeys, ...$arguments)
    {
        return $this->eval($script, $numkeys, ...$arguments);
    }

    /**
     * Find the single node all the given keys are routed to.
     *
     * @param  non-empty-array<int, mixed>  $keys
     *
     * @throws \InvalidArgumentException
     */
    private function nodeForKeys(array $keys): string
    {
        $nodes = array_values(array_unique(array_map(
            fn ($key) => $this->client->_target((string) $key),
            $keys,
        )));

        if (count($nodes) > 1) {
            throw new InvalidArgumentException('RedisArray cannot run a script with keys on different nodes.');
        }

        return $nodes[0];
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
