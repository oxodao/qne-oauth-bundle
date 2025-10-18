FROM php:8.4-cli-bookworm

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libxml2-dev zip unzip git; \
    docker-php-ext-install -j"$(nproc)" xml; \
    rm -rf /var/lib/apt/lists/*


CMD ["php", "-a"]
