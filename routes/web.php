<?php

declare(strict_types=1);

use App\Controllers\BoardController;
use App\Controllers\LoginController;
use App\Controllers\RegisterController;
use App\Controllers\TaskController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Router;

return static function (Router $router): void {
    $router->group([RedirectIfAuthenticated::class], function (Router $router): void {
        $router->get('/login', [LoginController::class, 'show']);
        $router->post('/login', [LoginController::class, 'store']);
        $router->get('/register', [RegisterController::class, 'show']);
        $router->post('/register', [RegisterController::class, 'store']);
    });

    $router->group([Authenticate::class], function (Router $router): void {
        $router->get('/', [BoardController::class, 'index']);
        $router->post('/logout', [LoginController::class, 'destroy']);

        $router->post('/tasks', [TaskController::class, 'store']);
        $router->post('/tasks/clear-done', [TaskController::class, 'clearDone']);
        $router->get('/tasks/export', [TaskController::class, 'export']);
        $router->get('/tasks/{id}/edit', [TaskController::class, 'edit']);
        $router->post('/tasks/{id}', [TaskController::class, 'update']);
        $router->post('/tasks/{id}/move', [TaskController::class, 'move']);
        $router->post('/tasks/{id}/delete', [TaskController::class, 'destroy']);
    });
};
