<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\PasswordReset;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class PasswordResetController extends Controller
{
    /** The same words for every address, registered or not. */
    public const SENT = 'If an account uses that email, we sent it a link to reset the password. Check your inbox and spam folder.';

    public function __construct(View $view, Url $url, Session $session, private PasswordReset $resets, private AuthService $auth)
    {
        parent::__construct($view, $url, $session);
    }

    public function showRequest(): Response
    {
        return $this->render('auth/forgot-password', ['title' => 'Forgot your password?', 'bodyClass' => 'page-auth', 'inlineErrors' => true]);
    }

    public function request(Request $request): Response
    {
        try {
            $this->resets->request($request->input('email'), $request->ip);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/forgot-password');
        }

        return $this->redirect('/forgot-password', self::SENT);
    }

    /** @param array{token: string} $params */
    public function showReset(Request $request, array $params): Response
    {
        if (!$this->resets->isUsable($params['token'])) {
            return $this->invalid();
        }

        return $this->render('auth/reset-password', [
            'title'        => 'Choose a new password',
            'bodyClass'    => 'page-auth',
            'inlineErrors' => true,
            'token'        => $params['token'],
        ]);
    }

    /** @param array{token: string} $params */
    public function reset(Request $request, array $params): Response
    {
        try {
            $userId = $this->resets->reset($params['token'], [
                'password'              => $request->raw('password'),
                'password_confirmation' => $request->raw('password_confirmation'),
            ], $request->ip);
        } catch (ValidationException $e) {
            if (isset($e->errors()['token'])) {
                return $this->invalid();
            }

            return $this->backWithErrors($e, $request, '/reset-password/' . $params['token']);
        }

        // Every other session of the account was signed out by the new password.
        $this->auth->signIn($userId);

        return $this->redirect('/', 'Your password was changed. Other devices were signed out.');
    }

    private function invalid(): Response
    {
        return new Response($this->view->render('auth/link-invalid', [
            'title'        => 'This link no longer works',
            'bodyClass'    => 'page-auth',
            'inlineErrors' => true,
            'message'      => 'This reset link is invalid or has expired. Ask for a new one.',
            'action'       => ['/forgot-password', 'Send a new link'],
        ]), 410);
    }
}
