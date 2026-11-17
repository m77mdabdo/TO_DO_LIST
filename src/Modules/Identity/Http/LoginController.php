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
                (string) $request->header('User-Agent'),
            );
        } catch (ValidationException $e) {
            if (isset($e->errors()['login'])) {
                // Tied to the account when there is one, so its owner sees the attempt.
                $this->log->record('login_failed', $this->users->findByEmail($request->input('email'))?->id, $request->ip);
            }

            return $this->backWithErrors($e, $request, '/login');
        }

        if ($user === null) {
            return $this->redirect('/login/two-factor');
        }
        $this->log->record('login', $user->id, $request->ip);

        return $this->redirect(Intended::pull($this->session));
    }

    /** The second step of two-step sign-in. */
    public function twoFactorForm(): Response
    {
        if (!$this->auth->pendingTwoFactor()) {
            return $this->redirect('/login');
        }

        return $this->render('auth/two-factor', ['title' => 'Two-step sign-in', 'bodyClass' => 'page-auth', 'inlineErrors' => true]);
    }

    public function twoFactor(Request $request): Response
    {
        if ($request->input('cancel') === '1') {
            $this->auth->cancelTwoFactor();

            return $this->redirect('/login');
        }
        $userId = $this->auth->pendingUserId();
        try {
            $user = $this->auth->completeTwoFactor($request->input('code'), $request->ip, (string) $request->header('User-Agent'));
        } catch (ValidationException $e) {
            $this->log->record('two_factor_failed', $userId, $request->ip);

            return $this->backWithErrors($e, $request, $this->auth->pendingTwoFactor() ? '/login/two-factor' : '/login');
        }
        $this->log->record('login', $user->id, $request->ip, 'two-step');

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
