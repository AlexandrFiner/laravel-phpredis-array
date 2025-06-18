# laravel-phpredis-array

fork of https://github.com/akalongman/laravel-lodash

## Installation

**1.**  Install via composer

```sh
$ composer require alexandrfiner/laravel-phpredis-array
```

**2.** Replace the default implementation of RedisServiceProvider into your config/app.php

```php
'providers' => ServiceProvider::defaultProviders()
    ->replace([
        \Illuminate\Redis\RedisServiceProvider::class => \LaravelPhpRedisArray\RedisServiceProvider::class
    ])->toArray(),
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
