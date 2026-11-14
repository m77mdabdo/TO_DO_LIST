<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\EmailVerification;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class EmailVerificationController extends Controller
{
    public function __construct(View $view, Url $url, Session $session, private EmailVerification $verification, private AuthService $auth)
    {
        parent::__construct($view, $url, $session);
    }

    /** Opened from the email, signed in or not (and maybe on another device). */
    public function verify(Request $request, array $params): Response
    {
        $userId = $this->verification->verify((string) $params['token'], $request->ip);

        if ($userId === null) {
            $user = $this->auth->user();

            if ($user?->emailVerified) {
                return $this->redirect('/', 'Your email is already confirmed.');
            }

            return new Response($this->view->render('auth/link-invalid', [
                'title'        => 'This link no longer works',
                'bodyClass'    => 'page-auth',
                'inlineErrors' => true,
                'message'      => 'This confirmation link is invalid or has expired. Sign in and ask for a new one.',
                'action'       => $user !== null ? null : ['/login', 'Log in'],
                'resend'       => $user !== null,
            ]), 410);
        }

        $this->auth->refresh();

        return $this->redirect($this->auth->check() ? '/' : '/login', 'Your email is confirmed. Thank you!');
    }

    public function resend(Request $request): Response
    {
        $user = $this->user($request);

        if ($user->emailVerified) {
            return $this->redirect('/', 'Your email is already confirmed.');
        }

        try {
            $this->verification->resend($user, $request->ip);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/');
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }

        return $this->redirect('/', 'We sent a new confirmation link to your email.');
    }
}
