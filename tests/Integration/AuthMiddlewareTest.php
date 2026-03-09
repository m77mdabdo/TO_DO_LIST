<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\FormState;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Redirector;
use App\Http\Request;
use App\Http\Response;
use App\Http\UrlGenerator;
use Tests\Support\Auth;
use Tests\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private Auth $app;
    private Redirector $redirect;

    public function setUp(): void
    {
        $this->app = new Auth();
        $this->redirect = new Redirector(new UrlGenerator(), $this->app->session, new FormState($this->app->session));
    }

    public function testGuestsAreSentToTheLoginPage(): void
    {
        $response = $this->authenticate(new Request('GET', '/'));

        $this->assertSame('/login', $response->header('Location'));
    }

    public function testGuestsGetA401ForJsonRequests(): void
    {
        $response = $this->authenticate(new Request('POST', '/tasks/1/move', headers: ['accept' => 'application/json']));

        $this->assertSame(401, $response->status());
    }

    public function testTheUserIsAddedToTheRequest(): void
    {
        $user = $this->app->users->create('Sara', 'sara@example.com', 'hash');
        $this->app->auth->login($user);

        $response = $this->authenticate(new Request('GET', '/'));

        $this->assertSame('Sara', $response->body());
    }

    public function testLoggedInUsersSkipTheLoginPage(): void
    {
        $middleware = new RedirectIfAuthenticated($this->app->auth, $this->redirect);
        $page = fn () => Response::html('login form');

        $this->assertSame('login form', $middleware->process(new Request('GET', '/login'), $page)->body());

        $this->app->auth->login($this->app->users->create('Sara', 'sara@example.com', 'hash'));

        $this->assertSame('/', $middleware->process(new Request('GET', '/login'), $page)->header('Location'));
    }

    private function authenticate(Request $request): Response
    {
        return (new Authenticate($this->app->auth, $this->redirect))
            ->process($request, fn (Request $r) => Response::html($r->attribute('user')->name));
    }
}
