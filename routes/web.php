<?php

declare(strict_types=1);

/*
 | App-wide routes that belong to no module. Each module adds its own
 | routes from its provider (see bootstrap/modules.php).
 */

use App\Controllers\LocaleController;
use App\Http\Router;

return static function (Router $router): void {
    $router->post('/locale/{locale:slug}', [LocaleController::class, 'update']);
};
