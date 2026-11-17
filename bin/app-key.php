<?php

declare(strict_types=1);

/*
 | Prints a new random APP_KEY line. Put it in .env yourself (it is never
 | written anywhere by this script), and keep it: changing it later makes
 | the integrations' saved addresses and two-step sign-in keys unreadable.
 |
 |   php bin/app-key.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap/autoload.php';

echo 'APP_KEY=' . App\Security\Secrets::newKey() . "\n";
