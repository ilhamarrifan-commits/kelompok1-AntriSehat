# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.4

############################################
# Stage 1: vendor - dependency produksi saja
############################################
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
    --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN rm -f bootstrap/cache/*.php
RUN composer dump-autoload --optimize --no-dev --no-scripts

############################################
# Stage 2: test - dipakai Jenkins: docker build --target test .
# Build GAGAL jika ada test yang gagal (quality gate)
############################################
FROM php:${PHP_VERSION}-cli AS test
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize
ENV APP_ENV=testing
# APP_KEY acak khusus untuk test; test memakai SQLite in-memory (lihat phpunit.xml)
RUN APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" php artisan test

############################################
# Stage 3: runtime - image akhir yang di-push ke Docker Hub
############################################
FROM php:${PHP_VERSION}-apache AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl procps \
    && docker-php-ext-install pdo_mysql bcmath \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY --from=vendor /app ./
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
