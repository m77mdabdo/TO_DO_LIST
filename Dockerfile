# Optional: run the app in a container. Nothing in the app needs Docker;
# XAMPP or `php -S` work just as well.
#
#   docker compose up -d          # app on http://localhost:8080 with SQLite
#   docker compose --profile mysql up -d   # with a MySQL container instead
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libsqlite3-dev \
 && docker-php-ext-install pdo_mysql pdo_sqlite zip \
 && a2enmod rewrite headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && rm -rf /var/lib/apt/lists/*

# Uploads up to the app's 10 MB limit.
RUN { echo 'upload_max_filesize = 12M'; echo 'post_max_size = 14M'; echo 'expose_php = Off'; } > /usr/local/etc/php/conf.d/todo.ini

WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/logs storage/mail storage/attachments storage/backups database/data \
 && chown -R www-data:www-data storage database

EXPOSE 80
