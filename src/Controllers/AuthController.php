<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authenticator;
use App\Auth\AuthService;
use App\Http\Redirector;
use App\Http\Request;
use App\Http\Response;
use App\Validation\ValidationException;
use App\View\View;

final class AuthController
{
    public function __construct(
        private AuthService $service,
        private Authenticator $auth,
        private View $view,
        private Redirector $redirect,
    ) {
    }

    public function showLogin(): Response
    {
        return $this->page('auth/login', 'Log in');
    }

    public function login(Request $request): Response
    {
        try {
            $this->service->attempt(
                ['email' => $request->input('email'), 'password' => $request->raw('password')],
                $request->ip(),
            );
        } catch (ValidationException $e) {
            return $this->redirect->withErrors('/login', $e->errors(), $request->body());
        }

        return $this->redirect->to('/');
    }

    public function showRegister(): Response
    {
        return $this->page('auth/register', 'Create account');
    }

    public function register(Request $request): Response
    {
        try {
            $this->service->register($request->only(['name', 'email']) + [
                'password'              => $request->raw('password'),
                'password_confirmation' => $request->raw('password_confirmation'),
            ]);
        } catch (ValidationException $e) {
            return $this->redirect->withErrors('/register', $e->errors(), $request->body());
        }

        return $this->redirect->withSuccess('/', 'Welcome aboard! Add your first task.');
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return $this->redirect->to('/login');
    }

    private function page(string $template, string $title): Response
    {
        return Response::html($this->view->render($template, [
            'title'        => $title,
            'bodyClass'    => 'page-auth',
            'inlineErrors' => true,
        ]));
    }
}
