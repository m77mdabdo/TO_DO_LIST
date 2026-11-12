<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Intended;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Application\AuthService;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class LoginController extends Controller
{
    public function __construct(View $view, Url $url, Session $session, private AuthService $auth)
    {
        parent::__construct($view, $url, $session);
    }

    public function show(): Response
    {
        return $this->render('auth/login', ['title' => 'Log in', 'bodyClass' => 'page-auth', 'inlineErrors' => true]);
    }

    public function store(Request $request): Response
    {
        try {
            $this->auth->login(
                ['email' => $request->input('email'), 'password' => $request->raw('password')],
                $request->ip,
            );
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/login');
        }

        return $this->redirect(Intended::pull($this->session));
    }

    public function destroy(): Response
    {
        $this->auth->logout();

        return $this->redirect('/login');
    }
}
