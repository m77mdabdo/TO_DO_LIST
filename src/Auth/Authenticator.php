<?php

declare(strict_types=1);

namespace App\Auth;

use App\Security\Csrf;
use App\Session\Session;

/**
 * Knows who is logged in for the current session.
 */
final class Authenticator
{
    private const KEY = 'user_id';

    private ?User $user = null;

    public function __construct(
        private Session $session,
        private UserRepository $users,
        private Csrf $csrf,
    ) {
    }

    public function user(): ?User
    {
        $id = $this->session->get(self::KEY);

        if (!is_int($id)) {
            return null;
        }

        if ($this->user?->id !== $id) {
            $this->user = $this->users->find($id);
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function login(User $user): void
    {
        // a fresh id stops session fixation, a fresh token stops an old form being replayed
        $this->session->regenerate();
        $this->csrf->refresh();
        $this->session->set(self::KEY, $user->id);
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
        $this->session->invalidate();
    }
}
