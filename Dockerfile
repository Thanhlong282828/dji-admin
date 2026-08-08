FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY Modules ./Modules
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --classmap-authoritative

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl pcntl pdo_mysql opcache zip \
    && apk del .build-deps
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-schema.ini
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php artisan about >/dev/null || exit 1
CMD ["php-fpm", "-F"]
