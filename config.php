<?php

/*
 | App configuration.
 | To override locally without touching this file, copy it to config.local.php
 | (ignored by git) or set the matching environment variables.
 */

// A yes/no setting from the environment. Unset or empty means the default; "0", "false",
// "off" and "no" all mean no (a plain `getenv() ?: true` would read "0" as unset).
$flag = static function (string $name, bool $default): bool {
    $value = getenv($name);

    return $value === false || $value === '' ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
};

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
    'debug' => $flag('APP_DEBUG', false),

    // Apply pending database migrations on the first request.
    // Fine locally; on a real server run `php database/migrate.php` on deploy instead.
    'auto_migrate' => $flag('APP_AUTO_MIGRATE', true),

    // Where unexpected errors are written: "file" (storage/logs) or "stderr" (containers,
    // so `docker compose logs` shows them).
    'log' => getenv('LOG_CHANNEL') === 'stderr' ? 'stderr' : 'file',

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
        'allow_private_networks' => $flag('WEBHOOKS_ALLOW_PRIVATE', false),
    ],

    // Account security. Security events older than this are deleted by bin/cleanup.php.
    'security' => [
        'log_days' => (int) (getenv('SECURITY_LOG_DAYS') ?: 180),
    ],

    // Request limits per rule name, e.g. ['login' => ['max' => 20, 'seconds' => 60]].
    // The rules and their defaults are listed in docs/trello-parity-roadmap.md (batch 1).
    'rate_limits' => [],

    // Database backups made by bin/backup.php: the newest `keep` of each kind
    // are kept, and none older than `keep_days` (0 = no age limit). The newest
    // backup is never removed.
    'backups' => [
        'path'      => getenv('BACKUPS_PATH') ?: __DIR__ . '/storage/backups',
        'keep'      => (int) (getenv('BACKUPS_KEEP') ?: 14),
        'keep_days' => (int) (getenv('BACKUPS_KEEP_DAYS') ?: 0),
    ],

    // How long bin/cleanup.php keeps records (days).
    'retention' => [
        'audit_days'         => (int) (getenv('AUDIT_LOG_DAYS') ?: 730),
        'notification_days'  => (int) (getenv('NOTIFICATIONS_DAYS') ?: 180),
        'idle_session_days'  => (int) (getenv('IDLE_SESSION_DAYS') ?: 30),
    ],

    // A board's ZIP export (JSON + attached files) is refused above this size.
    'exports' => [
        'zip_max_mb' => (int) (getenv('EXPORT_ZIP_MAX_MB') ?: 200),
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
