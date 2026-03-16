<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BoardController;
use App\Controllers\TaskController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Routing\Router;

return static function (Router $router): void {
    $router->group([RedirectIfAuthenticated::class], static function (Router $router): void {
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);
        $router->get('/register', [AuthController::class, 'showRegister']);
        $router->post('/register', [AuthController::class, 'register']);
    });

    $router->post('/logout', [AuthController::class, 'logout']);

    $router->group([Authenticate::class], static function (Router $router): void {
        $router->get('/', [BoardController::class, 'index']);
        $router->post('/board/clear-done', [TaskController::class, 'clearDone']);

        $router->post('/tasks', [TaskController::class, 'store']);
        $router->get('/tasks/{id}/edit', [TaskController::class, 'edit']);
        $router->post('/tasks/{id}', [TaskController::class, 'update']);
        $router->post('/tasks/{id}/move', [TaskController::class, 'move']);
        $router->post('/tasks/{id}/delete', [TaskController::class, 'destroy']);
    });
};
