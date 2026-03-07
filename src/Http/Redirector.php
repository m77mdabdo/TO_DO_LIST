<?php

declare(strict_types=1);

namespace App\Http;

use App\Session\Session;

/**
 * Redirects inside the app, optionally with a flash message or form errors.
 */
final class Redirector
{
    public function __construct(
        private UrlGenerator $urls,
        private Session $session,
        private FormState $form,
    ) {
    }

    public function to(string $path): Response
    {
        return Response::redirect($this->urls->to($path));
    }

    public function withSuccess(string $path, string $message): Response
    {
        $this->session->flash('success', $message);

        return $this->to($path);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     */
    public function withErrors(string $path, array $errors, array $input = []): Response
    {
        $this->form->remember($errors, $input);

        return $this->to($path);
    }
}
