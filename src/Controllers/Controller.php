<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

abstract class Controller
{
    public function __construct(
        protected View $view,
        protected Url $url,
        protected Session $session,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = []): Response
    {
        return new Response($this->view->render($template, $data));
    }

    protected function redirect(string $path, ?string $message = null): Response
    {
        if ($message !== null) {
            $this->session->flash('success', $message);
        }

        return Response::redirect($this->url->to($path));
    }

    protected function backWithErrors(ValidationException $e, Request $request, string $path): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'message' => current($e->errors()), 'errors' => $e->errors()], 422);
        }

        $this->view->form->keep($e->errors(), $request->body());

        return Response::redirect($this->url->to($path));
    }

    protected function user(Request $request): User
    {
        return $request->attribute('user');
    }
}
