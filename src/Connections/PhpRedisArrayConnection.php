<?php

namespace LaravelPhpRedisArray\Connections;

use Illuminate\Redis\Connections\PhpRedisConnection;

class PhpRedisArrayConnection extends PhpRedisConnection
{
    use PacksPhpRedisValues;

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
