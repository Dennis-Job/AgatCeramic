FROM php:8.3-cli-bookworm

# Keep the previously used release without relying on PECL channel availability.
ENV PHPREDIS_VERSION=6.3.0
ENV PHPREDIS_SHA256=cb8f81df1a275599e4f8ddcfec7e1f65ed1953e6f5673649149fd680ebff4cad

RUN apt-get update \
    && apt-get install --yes --no-install-recommends $PHPIZE_DEPS git libjpeg62-turbo-dev libpng-dev libpq-dev libwebp-dev libzip-dev unzip util-linux \
    && curl -fsSL --connect-timeout 10 --max-time 120 --retry 3 \
        -o /tmp/phpredis.tar.gz "https://codeload.github.com/phpredis/phpredis/tar.gz/refs/tags/${PHPREDIS_VERSION}" \
    && echo "${PHPREDIS_SHA256}  /tmp/phpredis.tar.gz" | sha256sum -c - \
    && mkdir /tmp/phpredis \
    && tar -xzf /tmp/phpredis.tar.gz -C /tmp/phpredis --strip-components=1 \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo_pgsql zip /tmp/phpredis \
    && apt-get purge --yes --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/phpredis /tmp/phpredis.tar.gz

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

COPY infrastructure/docker/backend/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY --chmod=755 infrastructure/docker/install-locked-dependencies.sh /usr/local/bin/install-locked-dependencies

# These files intentionally live in the image as rebuild inputs. Runtime uses the
# bind-mounted copies, while a lock change gives Compose a new image to recreate.
COPY backend/composer.json backend/composer.lock /opt/agatceramic/dependency-inputs/backend/

WORKDIR /var/www/backend
