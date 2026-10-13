<?php

namespace LaravelPhpRedisArray;

use LaravelPhpRedisArray\Connectors\PhpRedisConnector;
use Illuminate\Redis\Connectors\PredisConnector;
use Illuminate\Redis\RedisManager as BaseRedisManager;
use InvalidArgumentException;

class RedisManager extends BaseRedisManager
{
    /**
     * Resolve the given connection by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Redis\Connections\Connection
     *
     * @throws \InvalidArgumentException
     */
    public function resolve($name = null)
    {
        $name = $name ?: 'default';
        if (isset($this->config['arrays'][$name])) {
            return $this->resolveArray($name);
        }
        return parent::resolve($name);
    }

    /**
     * Resolve the given RedisArray connection by name.
     *
     * @param  string  $name
     * @return \LaravelPhpRedisArray\Connections\PhpRedisArrayConnection
     */
    protected function resolveArray($name)
    {
        return $this->connector()->connectToArray(
            array_map(function ($config) {
                return $this->parseConnectionConfiguration($config);
            }, $this->config['arrays'][$name]),
            $this->config['arrays']['options'] ?? [],
            $this->config['options'] ?? []
        );
    }

    /**
     * Get the connector instance for the current driver.
     *
     * @return \Illuminate\Redis\Connectors\PredisConnector|\LaravelPhpRedisArray\Connectors\PhpRedisConnector
     *
     * @throws \InvalidArgumentException
     */
    protected function connector(): PredisConnector|PhpRedisConnector
    {
        return match ($this->driver) {
            'predis'   => new PredisConnector(),
            'phpredis' => new PhpRedisConnector(),
            default    => throw new InvalidArgumentException('Redis driver ' . $this->driver . ' does not exists'),
        };
    }
}
