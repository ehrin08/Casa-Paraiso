FROM composer:2 AS composer

FROM node:24-bookworm-slim AS node

FROM php:8.2-apache-bookworm

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local /usr/local

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libicu-dev libzip-dev unzip default-mysql-client \
       libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install bcmath gd intl pcntl pdo_mysql zip \
    && a2enmod headers rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/app/entrypoint.sh /usr/local/bin/casa-entrypoint
RUN chmod +x /usr/local/bin/casa-entrypoint

ENTRYPOINT ["casa-entrypoint"]
CMD ["apache2-foreground"]
