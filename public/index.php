<?php

declare(strict_types=1);

/*
 | The only PHP file the web server runs. Everything else lives
 | outside public/, so it can never be opened from the browser.
 */

use App\Http\Kernel;
use App\Http\Request;
use App\Http\UrlGenerator;
use App\Session\NativeSession;

// php -S: let the built-in server send the CSS, JS and images itself
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';

$request = Request::fromGlobals($container->get(UrlGenerator::class)->basePath());

$container->get(NativeSession::class)->start($request->isSecure());

$container->get(Kernel::class)->handle($request)->send(withBody: $request->method() !== 'HEAD');
