<?php

/*
 | App configuration.
 | To override locally without touching this file, copy it to config.local.php
 | (ignored by git) or set the matching environment variables.
 */

return [
    'app_name' => getenv('APP_NAME') ?: 'Todo Board',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Riyadh',

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
