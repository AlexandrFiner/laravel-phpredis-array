<?php

namespace LaravelPhpRedisArray\Connectors;

use LaravelPhpRedisArray\Connections\PhpRedisArrayConnection;
use LaravelPhpRedisArray\Connections\PhpRedisConnection;
use Illuminate\Redis\Connectors\PhpRedisConnector as BasePhpRedisConnector;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis as RedisFacade;
use InvalidArgumentException;
use LogicException;
use Redis;
use RedisArray;

class PhpRedisConnector extends BasePhpRedisConnector
{
    /**
     * Create a new connection that rebuilds its client on any RedisException.
     *
     * @param  array  $config
     * @param  array  $options
     * @return \LaravelPhpRedisArray\Connections\PhpRedisConnection
     */
    public function connect(array $config, array $options): PhpRedisConnection
    {
        $formattedOptions = Arr::pull($config, 'options', []);

        if (isset($config['prefix'])) {
            $formattedOptions['prefix'] = $config['prefix'];
        }

        $connector = function () use ($config, $options, $formattedOptions) {
            return $this->createClient(array_merge(
                $config, $options, $formattedOptions
            ));
        };

        return new PhpRedisConnection($connector(), $connector, $config);
    }

    /**
     * Create a new RedisArray connection.
     *
     * @param  array  $config
     * @param  array  $arrayOptions
     * @param  array  $options
     * @return \LaravelPhpRedisArray\Connections\PhpRedisArrayConnection
     *
     * @throws \InvalidArgumentException
     */
    public function connectToArray(array $config, array $arrayOptions, array $options): PhpRedisArrayConnection
    {
        $options = array_merge($options, $arrayOptions, Arr::pull($config, 'options', []));
        if (
            !empty($config['servers_range'])
            && ($shardCount = (int)($config['servers_range']['shard_count'] ?? 0))
            && ($hostMask = $config['servers_range']['host_mask'] ?? null)
            && ($port = $config['servers_range']['port'] ?? null)
        ) {
            $servers = [];
            for ($i = 0; $i < $shardCount; $i++) {
                $servers[] = [
                    'host' => str_replace('{shard}', (string) $i, $hostMask),
                    'port' => $port,
                ];
            }
            $config['servers'] = $servers;
        }

        if (empty($config['servers'])) {
            throw new InvalidArgumentException('Servers array must not be empty, check servers_range');
        }

        return new PhpRedisArrayConnection(
            // @phpstan-ignore argument.type (the connection wraps RedisArray, Laravel documents the client as Redis)
            $this->createRedisArrayInstance(
                array_map($this->buildRedisArrayConnectionString(...), $config['servers']),
                $config,
                $options
            )
        );
    }

    /**
     * Create the Redis client instance.
     *
     * @param  array  $config
     * @return \Redis
     *
     * @throws \LogicException
     */
    protected function createClient(array $config): Redis
    {
        return tap(new Redis(), function (Redis $client) use ($config) {
            // @phpstan-ignore instanceof.alwaysFalse (true when the Redis facade alias shadows the extension class, same check as in Laravel)
            if ($client instanceof RedisFacade) {
                throw new LogicException(
                    extension_loaded('redis')
                        ? 'Please remove or rename the Redis facade alias in your "app" configuration file in order to avoid collision with the PHP Redis extension.'
                        : 'Please make sure the PHP Redis extension is installed and enabled.'
                );
            }

            $this->establishConnection($client, $config);

            if (!empty($config['password'])) {
                $client->auth((string)$config['password']);
            }

            if (!empty($config['database'])) {
                $client->select((int)$config['database']);
            }

            if (!empty($config['prefix'])) {
                $client->setOption(Redis::OPT_PREFIX, (string)$config['prefix']);
            }

            if (!empty($config['read_timeout'])) {
                $client->setOption(Redis::OPT_READ_TIMEOUT, (string)$config['read_timeout']);
            }

            if (array_key_exists('serializer', $config)) {
                $client->setOption(Redis::OPT_SERIALIZER, (string)$config['serializer']);
            }

            if (array_key_exists('compression', $config)) {
                $client->setOption(Redis::OPT_COMPRESSION, (string)$config['compression']);
            }

            if (array_key_exists('compression_level', $config)) {
                $client->setOption(Redis::OPT_COMPRESSION_LEVEL, (string)$config['compression_level']);
            }

            if (empty($config['scan'])) {
                $client->setOption(Redis::OPT_SCAN, (string)Redis::SCAN_RETRY);
            }
        });
    }

    /**
     * Build a RedisArray connection string from the given server configuration.
     *
     * @param  array  $server
     * @return string
     */
    protected function buildRedisArrayConnectionString(array $server): string
    {
        return $server['host'] . ':' . $server['port'];
    }

    /**
     * Create the RedisArray client instance.
     *
     * @param  array  $servers
     * @param  array  $config
     * @param  array  $options
     * @return \RedisArray
     *
     * @throws \InvalidArgumentException
     */
    protected function createRedisArrayInstance(array $servers, array $config, array $options): RedisArray
    {
        $redisArray = new RedisArray($servers, $config['array_options'] ?? []);
        if (!empty($options['password'])) {
            // @TODO: Remove after this will be implemented
            // https://github.com/phpredis/phpredis/issues/1508
            throw new InvalidArgumentException('RedisArray does not support authorization');
            //$client->auth((string) $options['password']);
        }

        if (isset($options['database'])) {
            $redisArray->select((int)$options['database']);
        }

        if (!empty($options['prefix'])) {
            $redisArray->setOption(Redis::OPT_PREFIX, $options['prefix']);
        }

        if (!empty($config['read_timeout'])) {
            $redisArray->setOption(Redis::OPT_READ_TIMEOUT, $config['read_timeout']);
        }

        if (!empty($config['scan'])) {
            $redisArray->setOption(Redis::OPT_SCAN, $config['scan']);
        }

        if (!empty($config['name'])) {
            $redisArray->client('SETNAME', $config['name']);
        }

        if (array_key_exists('serializer', $config)) {
            $redisArray->setOption(Redis::OPT_SERIALIZER, '2');
        }

        if (array_key_exists('compression', $config)) {
            $redisArray->setOption(Redis::OPT_COMPRESSION, $config['compression']);
        }

        if (array_key_exists('compression_level', $config)) {
            $redisArray->setOption(Redis::OPT_COMPRESSION_LEVEL, $config['compression_level']);
        }

        return $redisArray;
    }
}
