<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\FormState;
use App\Http\Redirector;
use App\Http\UrlGenerator;
use App\Session\ArraySession;
use Tests\TestCase;

final class RedirectorTest extends TestCase
{
    public function testRedirectsWithAFlashMessage(): void
    {
        $session = new ArraySession();
        $redirect = new Redirector(new UrlGenerator('/app'), $session, new FormState($session));

        $response = $redirect->withSuccess('/', 'Task added.');

        $this->assertSame('/app/', $response->header('Location'));
        $this->assertSame('Task added.', $session->pull('success'));
    }

    public function testRedirectsBackWithErrorsAndInput(): void
    {
        $session = new ArraySession();
        $redirect = new Redirector(new UrlGenerator(), $session, new FormState($session));

        $redirect->withErrors('/register', ['email' => 'Taken.'], ['email' => 'a@b.co', 'password' => 'x']);
        $form = new FormState($session);

        $this->assertSame('Taken.', $form->error('email'));
        $this->assertSame('a@b.co', $form->old('email'));
        $this->assertSame('', $form->old('password'));
    }
}
