FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip \
 && docker-php-ext-install pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/balay.ini /usr/local/etc/php/conf.d/balay.ini

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html
