<?php

/*
 | App configuration.
 | To override locally without touching this file, copy it to config.local.php
 | (ignored by git) or set the matching environment variables.
 */

return [
    'app_name' => getenv('APP_NAME') ?: 'Todo Board',
    // Encrypts secrets saved in the database (integrations, two-step sign-in).
    // Make one with `php bin/app-key.php`, put it in .env, and don't change it.
    'key'      => getenv('APP_KEY') ?: '',
    // The address people open the app at, used for links in emails (invitations).
    'url'      => getenv('APP_URL') ?: 'http://localhost:8000',
    // Interface language: "ar" (right-to-left, the default) or "en".
    'locale'   => getenv('APP_LOCALE') ?: 'ar',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Riyadh',

    // Show error details on the error page. Keep it off on a real server.
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),

    // Apply pending database migrations on the first request.
    // Fine locally; on a real server run `php database/migrate.php` on deploy instead.
    'auto_migrate' => filter_var(getenv('APP_AUTO_MIGRATE') ?: true, FILTER_VALIDATE_BOOL),

    // Email for invitations and notifications.
    // Uploaded files. Keep this folder outside the web root, or blocked like storage/ is.
    'attachments' => [
        'path' => getenv('ATTACHMENTS_PATH') ?: __DIR__ . '/storage/attachments',
    ],

    // Imports waiting for their preview (CSV, Trello). Deleted once run or cancelled.
    'imports' => [
        'path' => getenv('IMPORTS_PATH') ?: __DIR__ . '/storage/imports',
    ],

    // Outgoing webhooks (sent by bin/webhooks.php). Addresses inside private
    // networks are refused unless this is on (only for local testing).
    'webhooks' => [
        'allow_private_networks' => filter_var(getenv('WEBHOOKS_ALLOW_PRIVATE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],

    // Account security. Security events older than this are deleted by bin/cleanup.php.
    'security' => [
        'log_days' => (int) (getenv('SECURITY_LOG_DAYS') ?: 180),
    ],

    // Request limits per rule name, e.g. ['login' => ['max' => 20, 'seconds' => 60]].
    // The rules and their defaults are listed in docs/trello-parity-roadmap.md (batch 1).
    'rate_limits' => [],

    // Database backups made by bin/backup.php.
    'backups' => [
        'path' => getenv('BACKUPS_PATH') ?: __DIR__ . '/storage/backups',
    ],

    // "log" writes each email to storage/mail (nothing is sent), "smtp" sends through
    // the server below, "mail" uses PHP's mail(). Put real credentials in .env, never here.
    'mail' => [
        'driver'       => getenv('MAIL_MAILER') ?: 'log',
        'host'         => getenv('MAIL_HOST') ?: '',
        'port'         => (int) (getenv('MAIL_PORT') ?: 587),
        'username'     => getenv('MAIL_USERNAME') ?: '',
        'password'     => getenv('MAIL_PASSWORD') ?: '',
        // "tls" (STARTTLS, port 587), "ssl" (port 465) or "" for none
        'encryption'   => getenv('MAIL_ENCRYPTION') !== false ? (string) getenv('MAIL_ENCRYPTION') : 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@localhost',
        'from_name'    => getenv('MAIL_FROM_NAME') ?: 'To Do List',
    ],

    'db' => [
        // "sqlite" works out of the box, "mysql" for XAMPP / shared hosting
        'driver'      => getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => getenv('DB_SQLITE_PATH') ?: __DIR__ . '/database/todo.sqlite',

        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'to_do_list',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
];
