<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

// Classes\Validation\Required -> classes/validation/Required.php
spl_autoload_register(function (string $class): void {
    $parts = explode('\\', $class);
    if (array_shift($parts) !== 'Classes') {
        return;
    }
    $file = array_pop($parts) . '.php';
    $dir  = $parts ? strtolower(implode('/', $parts)) . '/' : '';
    $path = BASE_PATH . '/classes/' . $dir . $file;

    if (is_file($path)) {
        require $path;
    }
});

$config = require is_file(BASE_PATH . '/config.local.php')
    ? BASE_PATH . '/config.local.php'
    : BASE_PATH . '/config.php';

date_default_timezone_set($config['timezone']);

require_once BASE_PATH . '/inc/helpers.php';

use Classes\Auth;
use Classes\Database;
use Classes\Request;
use Classes\Session;
use Classes\TaskRepository;

Session::start();
send_security_headers();

$db      = Database::connect($config['db']);
$request = new Request();
$auth    = new Auth($db);
$tasks   = new TaskRepository($db);
