# Changelog

All notable changes to `laravel-phpredis-array` will be documented in this file.

## 0.0.4

- Non-array phpredis connections rebuild their client after any `RedisException` in `command()` and `pipeline()`.
  A read timeout in the middle of a reply makes phpredis throw "protocol error" with the socket still open,
  and Laravel kept reusing that out-of-sync client.
