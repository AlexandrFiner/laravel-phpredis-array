<?php

namespace LaravelPhpRedisArray\Connections;

use Closure;
use Illuminate\Redis\Connections\PhpRedisConnection as BasePhpRedisConnection;
use RedisException;

/**
 * Rebuilds the client after any RedisException, not only after the "lost connection" messages
 * Laravel knows about.
 *
 * When a read timeout hits in the middle of a reply, phpredis throws "protocol error, ..."
 * but keeps the socket open, so the rest of that reply is read as the answer to the next
 * command and the connection stays out of sync until the process restarts. Laravel 12 does not
 * rebuild the client on this message, and never rebuilds it when pipeline() fails.
 *
 * On Laravel 13 pipeline() is covered (laravel/framework#61632), the message list is not
 * (laravel/framework#61661), so overriding causedByLostConnection() is enough there.
 */
class PhpRedisConnection extends BasePhpRedisConnection
{
    /**
     * Run a command against the Redis database.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \RedisException
     */
    public function command($method, array $parameters = [])
    {
        return $this->rebuildClientOnException(fn () => parent::command($method, $parameters));
    }

    /**
     * Execute commands in a pipeline.
     *
     * @param  callable|null  $callback
     * @return \Redis|array
     *
     * @throws \RedisException
     */
    public function pipeline(?callable $callback = null)
    {
        if (is_null($callback)) {
            return parent::pipeline();
        }

        return $this->rebuildClientOnException(fn () => parent::pipeline($callback));
    }

    /**
     * Run the given callback and replace the client with a fresh one if it throws a RedisException.
     *
     * @param  \Closure  $call
     * @return mixed
     *
     * @throws \RedisException
     */
    private function rebuildClientOnException(Closure $call): mixed
    {
        $client = $this->client;

        try {
            return $call();
        } catch (RedisException $e) {
            // parent::command() may have rebuilt the client already
            if ($this->client === $client && $this->connector) {
                rescue(static fn () => $client->close(), report: false);
                rescue(fn () => $this->client = call_user_func($this->connector), report: false);
            }

            throw $e;
        }
    }
}
