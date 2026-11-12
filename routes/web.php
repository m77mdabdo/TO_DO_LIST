<?php

declare(strict_types=1);

use App\Controllers\BoardController;
use App\Controllers\LocaleController;
use App\Controllers\TaskController;
use App\Http\Middleware\Authenticate;
use App\Http\Router;

return static function (Router $router): void {
    $router->post('/locale/{locale:slug}', [LocaleController::class, 'update']);

    $router->group([Authenticate::class], function (Router $router): void {
        $router->get('/', [BoardController::class, 'index']);

        $router->post('/tasks', [TaskController::class, 'store']);
        $router->post('/tasks/clear-done', [TaskController::class, 'clearDone']);
        $router->get('/tasks/export', [TaskController::class, 'export']);
        $router->get('/tasks/{id}/edit', [TaskController::class, 'edit']);
        $router->post('/tasks/{id}', [TaskController::class, 'update']);
        $router->post('/tasks/{id}/move', [TaskController::class, 'move']);
        $router->post('/tasks/{id}/delete', [TaskController::class, 'destroy']);
    });
};
