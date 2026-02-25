<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Auth\Authenticator;
use App\Auth\PdoUserRepository;
use App\Security\Csrf;
use App\Session\ArraySession;
use App\Support\FrozenClock;
use Tests\Support\Database;
use Tests\TestCase;

final class AuthenticatorTest extends TestCase
{
    private ArraySession $session;
    private PdoUserRepository $users;
    private Authenticator $auth;

    public function setUp(): void
    {
        $this->session = new ArraySession();
        $this->users = new PdoUserRepository(Database::fresh(), new FrozenClock());
        $this->auth = new Authenticator($this->session, $this->users, new Csrf($this->session));
    }

    public function testGuestsAreNotLoggedIn(): void
    {
        $this->assertFalse($this->auth->check());
        $this->assertNull($this->auth->user());
    }

    public function testLoginKeepsTheUserInTheSession(): void
    {
        $user = $this->users->create('Sara', 'sara@example.com', 'hash');
        $this->auth->login($user);

        $fresh = new Authenticator($this->session, $this->users, new Csrf($this->session));

        $this->assertSame($user->id, $fresh->user()?->id);
    }

    public function testLoginRefreshesTheCsrfToken(): void
    {
        $csrf = new Csrf($this->session);
        $before = $csrf->token();

        $this->auth->login($this->users->create('Sara', 'sara@example.com', 'hash'));

        $this->assertFalse($csrf->matches($before));
    }

    public function testLogoutForgetsTheUser(): void
    {
        $this->auth->login($this->users->create('Sara', 'sara@example.com', 'hash'));
        $this->auth->logout();

        $this->assertFalse($this->auth->check());
        $this->assertNull($this->session->get('user_id'));
    }

    public function testADeletedUserIsLoggedOut(): void
    {
        $this->session->set('user_id', 999);

        $this->assertFalse($this->auth->check());
    }
}
