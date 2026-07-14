FROM php:8.4-cli

# Install the phpredis extension (provides Redis and RedisArray classes).
# "yes no" answers all optional-dependency prompts (igbinary/lzf/zstd/lz4) with "no".
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && yes no | pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app