<?php

/*
 | App configuration.
 | To override locally without touching this file, copy it to config.local.php
 | (ignored by git) or set the matching environment variables.
 */

return [
    'app_name' => getenv('APP_NAME') ?: 'Todo Board',
    // Interface language: "ar" (right-to-left, the default) or "en".
    'locale'   => getenv('APP_LOCALE') ?: 'ar',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Riyadh',

    // Show error details on the error page. Keep it off on a real server.
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),

    // Apply pending database migrations on the first request.
    // Fine locally; on a real server run `php database/migrate.php` on deploy instead.
    'auto_migrate' => filter_var(getenv('APP_AUTO_MIGRATE') ?: true, FILTER_VALIDATE_BOOL),

    'db' => [
        // "sqlite" works out of the box, "mysql" for XAMPP / shared hosting
        'driver'      => getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => __DIR__ . '/database/todo.sqlite',

        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'to_do_list',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
];
