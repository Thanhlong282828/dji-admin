FROM php:8.4-cli-alpine AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache git unzip
WORKDIR /app
COPY composer.json composer.lock ./
COPY app ./app
COPY Modules ./Modules
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --classmap-authoritative \
    --ignore-platform-reqs

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype curl clamav-scanner clamav-db \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl pcntl pdo_mysql opcache zip \
    && apk del .build-deps
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-schema.ini
COPY docker/php/zz-runtime-pool.conf /usr/local/etc/php-fpm.d/zz-runtime-pool.conf
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && test -x /usr/bin/clamscan \
    && find /var/lib/clamav -maxdepth 1 -type f \( -name '*.cvd' -o -name '*.cld' \) -print -quit | grep -q . \
    && php -r "foreach (['bcmath', 'gd', 'intl', 'pcntl', 'pdo_mysql', 'zip'] as \$extension) { if (! extension_loaded(\$extension)) { fwrite(STDERR, \"Missing runtime extension: {\$extension}\\n\"); exit(1); } }" \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
RUN php artisan package:discover --ansi
HEALTHCHECK --interval=30s --timeout=8s --start-period=20s --retries=3 CMD php artisan operations:health-check >/dev/null || exit 1
CMD ["php-fpm", "-F"]
