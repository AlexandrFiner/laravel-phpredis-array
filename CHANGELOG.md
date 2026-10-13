# Changelog

All notable changes to `laravel-phpredis-array` will be documented in this file.

## 1.0.0

- Array connections run `eval()` and `evalsha()` on the node of the script's keys. RedisArray routed EVAL
  by the script text, so `Cache::add()`, lock release and `RateLimiter` ran on one node regardless of the key:
  rate limiter counters were left without TTL and did not block. Scripts without keys keep the RedisArray
  routing, scripts with keys on different nodes throw `InvalidArgumentException`.

## 0.1.0

- Non-array phpredis connections rebuild their client after any `RedisException` in `command()` and `pipeline()`.
  A read timeout in the middle of a reply makes phpredis throw "protocol error" with the socket still open,
  and Laravel kept reusing that out-of-sync client.
