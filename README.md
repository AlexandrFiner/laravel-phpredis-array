# laravel-phpredis-array

fork of https://github.com/akalongman/laravel-lodash

## Installation

You can install the package via composer:

```bash
composer require alexandrfiner/laravel-phpredis-array
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-phpredis-array-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-phpredis-array-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-phpredis-array-views"
```
## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
