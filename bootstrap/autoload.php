<?php

declare(strict_types=1);

/*
 | PSR-4 style autoloading without Composer:
 |   App\Modules\Card\CardModule  ->  src/Modules/Card/CardModule.php
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
