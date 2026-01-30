<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/autoload.php';

// legacy Classes\ namespace, same rules as App.php
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
