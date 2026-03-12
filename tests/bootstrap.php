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
