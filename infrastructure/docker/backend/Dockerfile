FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install --yes --no-install-recommends $PHPIZE_DEPS git libjpeg62-turbo-dev libpng-dev libpq-dev libwebp-dev libzip-dev unzip util-linux \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge --yes --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

COPY infrastructure/docker/backend/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY --chmod=755 infrastructure/docker/install-locked-dependencies.sh /usr/local/bin/install-locked-dependencies

# These files intentionally live in the image as rebuild inputs. Runtime uses the
# bind-mounted copies, while a lock change gives Compose a new image to recreate.
COPY backend/composer.json backend/composer.lock /opt/agatceramic/dependency-inputs/backend/

WORKDIR /var/www/backend
