FROM php:8.3-fpm-alpine

RUN apk add --no-cache $PHPIZE_DEPS libzip-dev oniguruma-dev icu-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip opcache \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
