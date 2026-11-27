# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# 1. Shared PHP 8.4 base
# Laravel 13 requires PHP >= 8.4. The official FPM image already provides
# ctype/dom/fileinfo/mbstring/openssl/sqlite3/pdo_sqlite/opcache; the extra
# extensions below are the ones the project actually needs on top of that.
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS php-base

RUN apk add --no-cache \
        nginx \
        supervisor \
        su-exec \
        gettext \
        curl \
        sqlite \
        icu-libs \
        libzip \
        libpq \
    && apk add --no-cache --virtual .php-build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip \
    && apk del .php-build-deps \
    && for ext in ctype dom fileinfo filter hash iconv json libxml mbstring \
                  openssl pcre phar session tokenizer xml xmlwriter \
                  sqlite3 pdo_sqlite; do \
           php -m | grep -qi "^${ext}$" || { echo "Missing PHP extension: ${ext}" >&2; exit 1; }; \
       done \
    && php -m | grep -qi 'Zend OPcache' \
    && mkdir -p /etc/nginx/templates /etc/nginx/http.d /etc/nginx/conf.d /run/nginx /var/www/html

# ---------------------------------------------------------------------------
# 2. Composer production dependencies only (no dev packages in the image).
# ---------------------------------------------------------------------------
FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader

COPY . .
RUN php artisan package:discover --no-interaction

# ---------------------------------------------------------------------------
# 3. Frontend build. Vite 8 requires Node ^20.19 or >= 22.12.
# Only manifest/resources are copied so the build does not depend on PHP.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build

# ---------------------------------------------------------------------------
# 4. Runtime image: nginx + PHP-FPM under supervisor as PID 1.
# ---------------------------------------------------------------------------
FROM php-base AS runtime

WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/pcdr.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0755 /usr/local/bin/entrypoint.sh \
    && rm -f public/storage \
    && php artisan storage:link --force \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/app/public \
        storage/logs \
        bootstrap/cache \
        database \
    && rm -f database/database.sqlite* \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R ug+rwX storage bootstrap/cache database \
    && chmod 0664 database/database.sqlite

# Generic defaults; any host (Render, Hostinger, plain Docker) can override.
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    CACHE_STORE=database \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=database \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

# Render uses its own HTTP health check; this is useful for local Docker runs.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-8080}/up" > /dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/pcdr.conf"]
