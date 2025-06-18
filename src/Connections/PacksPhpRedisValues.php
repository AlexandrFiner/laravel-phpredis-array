<?php

namespace LaravelPhpRedisArray\Connections;

use Redis;
use RedisArray;
use RuntimeException;

trait PacksPhpRedisValues
{
    public function withoutSerializationOrCompression(callable $callback)
    {
        $client = $this->client;

        $oldSerializer = null;

        if ($this->serialized()) {
            $oldSerializer = $this->getUnifiedOption($client, Redis::OPT_SERIALIZER);
            $client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        }

        $oldCompressor = null;

        if ($this->compressed()) {
            $oldCompressor = $this->getUnifiedOption($client, Redis::OPT_COMPRESSION);
            $client->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE);
        }

        try {
            return $callback();
        } finally {
            if ($oldSerializer !== null) {
                $client->setOption(Redis::OPT_SERIALIZER, $oldSerializer);
            }

            if ($oldCompressor !== null) {
                $client->setOption(Redis::OPT_COMPRESSION, $oldCompressor);
            }
        }
    }

    public function serialized(): bool
    {
        return defined('Redis::OPT_SERIALIZER') &&
            $this->getUnifiedOption($this->client, Redis::OPT_SERIALIZER) !== Redis::SERIALIZER_NONE;
    }

    public function compressed(): bool
    {
        return defined('Redis::OPT_COMPRESSION') &&
            $this->getUnifiedOption($this->client, Redis::OPT_COMPRESSION) !== Redis::COMPRESSION_NONE;
    }

    private function getUnifiedOption(RedisArray $client, int $option)
    {
        $options = array_unique(array_values($client->getOption($option)));
        if (count($options) !== 1) {
            throw new RuntimeException('RedisArray must have the same configuration on all nodes.');
        }
        return $options[0];
    }
}
