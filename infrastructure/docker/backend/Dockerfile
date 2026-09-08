FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install --yes --no-install-recommends $PHPIZE_DEPS git libjpeg62-turbo-dev libpng-dev libpq-dev libwebp-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge --yes --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

COPY infrastructure/docker/backend/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/backend
