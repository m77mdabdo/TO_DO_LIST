<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class RegisterController extends Controller
{
    public function __construct(View $view, Url $url, Session $session, private AuthService $auth)
    {
        parent::__construct($view, $url, $session);
    }

    public function show(): Response
    {
        return $this->render('auth/register', ['title' => 'Create account', 'bodyClass' => 'page-auth', 'inlineErrors' => true]);
    }

    public function store(Request $request): Response
    {
        try {
            $this->auth->register([
                'name'                  => $request->input('name'),
                'email'                 => $request->input('email'),
                'password'              => $request->raw('password'),
                'password_confirmation' => $request->raw('password_confirmation'),
            ]);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/register');
        }

        return $this->redirect('/', 'Welcome aboard! Add your first task.');
    }
}
