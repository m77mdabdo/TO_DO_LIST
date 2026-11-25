#!/bin/sh
# Starts the app or scheduler container: wait for the database, apply migrations
# (only where RUN_MIGRATIONS=1, the app service), then run the given command.
set -eu

php /var/www/html/docker/php/wait-for-db.php

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php /var/www/html/database/migrate.php
fi

exec "$@"
