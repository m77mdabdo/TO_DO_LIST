# syntax=docker/dockerfile:1.7
#
# Two images from one file:
#   app  PHP-FPM with the code (also runs the scheduler)
#   web  nginx: serves assets/ and passes everything else to app
#
# Use compose.yaml rather than building these by hand. See "Run with Docker" in the README.

ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-fpm-alpine AS app

# pdo_sqlite, mbstring, curl, openssl, fileinfo and iconv come with the base image.
# fcgi is only for the health check (cgi-fcgi talks to PHP-FPM directly).
RUN apk add --no-cache fcgi libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip opcache \
 && apk del .build-deps \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

WORKDIR /var/www/html

# The code belongs to root and is read-only for the app user; only the data folders are writable.
COPY . .
RUN mkdir -p storage/logs storage/mail storage/attachments storage/backups storage/imports \
             storage/sessions storage/scheduler database/data \
 && chown -R www-data:www-data storage database/data \
 && chmod 0755 docker/php/entrypoint.sh

ENV APP_AUTO_MIGRATE=0 \
    LOG_CHANNEL=stderr \
    DB_SQLITE_PATH=/var/www/html/database/data/todo.sqlite \
    FPM_MAX_CHILDREN=10 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

USER www-data
EXPOSE 9000
ENTRYPOINT ["sh", "/var/www/html/docker/php/entrypoint.sh"]
CMD ["php-fpm"]


FROM nginxinc/nginx-unprivileged:1.27-alpine AS web

ENV PHP_UPSTREAM=app:9000 \
    TRUSTED_PROXIES=

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY --chmod=755 docker/nginx/15-trusted-proxies.sh /docker-entrypoint.d/15-trusted-proxies.sh
COPY assets /var/www/html/assets

EXPOSE 8080
