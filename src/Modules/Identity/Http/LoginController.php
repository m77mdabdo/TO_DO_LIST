<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Intended;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Domain\SecurityLog;
use App\Modules\Identity\Domain\UserRepository;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class LoginController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private AuthService $auth,
        private SecurityLog $log,
        private UserRepository $users,
    )
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
            $user = $this->auth->login(
                ['email' => $request->input('email'), 'password' => $request->raw('password')],
                $request->ip,
            );
        } catch (ValidationException $e) {
            if (isset($e->errors()['login'])) {
                // Tied to the account when there is one, so its owner sees the attempt.
                $this->log->record('login_failed', $this->users->findByEmail($request->input('email'))?->id, $request->ip);
            }

            return $this->backWithErrors($e, $request, '/login');
        }

        $this->log->record('login', $user->id, $request->ip);

        return $this->redirect(Intended::pull($this->session));
    }

    public function destroy(Request $request): Response
    {
        $user = $this->auth->user();
        if ($user !== null) {
            $this->log->record('logout', $user->id, $request->ip);
        }
        $this->auth->logout();

        return $this->redirect('/login');
    }
}
