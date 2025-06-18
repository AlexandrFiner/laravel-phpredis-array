<?php

namespace AlexandrFiner\LaravelPhpredisArray\Redis;

use AlexandrFiner\LaravelPhpredisArray\Redis\Connectors\PhpRedisConnector;
use Illuminate\Redis\Connectors\PredisConnector;
use Illuminate\Redis\RedisManager as BaseRedisManager;
use InvalidArgumentException;

class RedisManager extends BaseRedisManager
{
    public function resolve($name = null)
    {
        $name = $name ?: 'default';
        if (isset($this->config['arrays'][$name])) {
            return $this->resolveArray($name);
        }
        return parent::resolve($name);
    }

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

    protected function connector(): PredisConnector|PhpRedisConnector
    {
        return match ($this->driver) {
            'predis'   => new PredisConnector(),
            'phpredis' => new PhpRedisConnector(),
            default    => throw new InvalidArgumentException('Redis driver ' . $this->driver . ' does not exists'),
        };
    }
}
