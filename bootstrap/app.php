<?php

declare(strict_types=1);

/*
 | Builds the service container for the web app and the CLI scripts.
 |
 |   $container = require __DIR__ . '/../bootstrap/app.php';
 */

use App\Bootstrap\Services;
use App\Http\UrlGenerator;
use App\Support\Container;

$root = dirname(__DIR__);

require_once __DIR__ . '/autoload.php';

$config = require is_file($root . '/config/local.php') ? $root . '/config/local.php' : $root . '/config/app.php';

date_default_timezone_set($config['timezone']);

$container = new Container();
Services::register($container, $config, $root, PHP_SAPI === 'cli' ? '' : UrlGenerator::detectBasePath($_SERVER));

return $container;
