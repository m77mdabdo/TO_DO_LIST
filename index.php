<?php

declare(strict_types=1);

/*
 | Front controller: every request comes through here.
 |   Apache/XAMPP: .htaccess rewrites pretty URLs to this file.
 |   PHP's server:  php -S localhost:8000 index.php
 */

use App\Core\Config;
use App\Http\Kernel;
use App\Http\Request;
use App\Http\Url;
use App\Session\NativeSession;

if (PHP_SAPI === 'cli-server') {
    $path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Only files under assets/ are served directly; everything else goes through the app.
    if (str_starts_with($path, '/assets/') && is_file(__DIR__ . $path)) {
        return false;
    }
}

require __DIR__ . '/bootstrap/autoload.php';

$config   = Config::load(__DIR__);
// PHP's built-in server serves from the root, and sets SCRIPT_NAME to the request path
// for URLs with a dot (like /export.json), which would confuse the detection.
$basePath = PHP_SAPI === 'cli-server' ? '' : Url::detectBasePath();
$session  = new NativeSession('todo_session', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$app      = (require __DIR__ . '/bootstrap/app.php')($config, $session, $basePath);

$app->get(Kernel::class)
    ->handle(Request::fromGlobals($basePath))
    ->send();
