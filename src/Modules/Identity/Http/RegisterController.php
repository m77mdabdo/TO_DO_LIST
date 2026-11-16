<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Intended;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\EmailVerification;
use App\Modules\Identity\Domain\SecurityLog;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class RegisterController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private AuthService $auth,
        private EmailVerification $verification,
        private SecurityLog $log,
    )
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
            $user = $this->auth->register([
                'name'                  => $request->input('name'),
                'email'                 => $request->input('email'),
                'password'              => $request->raw('password'),
                'password_confirmation' => $request->raw('password_confirmation'),
            ]);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/register');
        }

        $this->log->record('registered', $user->id, $request->ip);
        $this->verification->send($user, $request->ip);

        $back = Intended::pull($this->session, '');

        return $back !== '' ? $this->redirect($back) : $this->redirect('/', 'Welcome aboard! We sent a link to confirm your email.');
    }
}
