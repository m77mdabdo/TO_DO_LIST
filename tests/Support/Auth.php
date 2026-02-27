<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\Authenticator;
use App\Auth\AuthService;
use App\Auth\PdoLoginThrottle;
use App\Auth\PdoUserRepository;
use App\Security\Csrf;
use App\Session\ArraySession;
use App\Support\FrozenClock;
use App\Validation\Validator;
use PDO;

/**
 * Wires the auth classes against a fresh database for a test.
 */
final class Auth
{
    public readonly PDO $pdo;
    public readonly FrozenClock $clock;
    public readonly ArraySession $session;
    public readonly PdoUserRepository $users;
    public readonly PdoLoginThrottle $throttle;
    public readonly Authenticator $auth;
    public readonly AuthService $service;

    public function __construct()
    {
        $this->pdo      = Database::fresh();
        $this->clock    = new FrozenClock('2026-02-01 10:00:00');
        $this->session  = new ArraySession();
        $this->users    = new PdoUserRepository($this->pdo, $this->clock);
        $this->throttle = new PdoLoginThrottle($this->pdo, $this->clock);
        $this->auth     = new Authenticator($this->session, $this->users, new Csrf($this->session));
        $this->service  = new AuthService($this->users, $this->auth, $this->throttle, new Validator());
    }
}
