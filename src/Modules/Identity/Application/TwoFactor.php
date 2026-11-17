<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Totp;
use App\Modules\Identity\Domain\UserRepository;
use App\Security\Secrets;
use App\Session\Session;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Two-step sign-in with an authenticator app (TOTP), plus ten one-time
 * recovery codes for a lost phone.
 *
 * The secret is encrypted with APP_KEY before it is stored, so it can't be
 * turned on without one. Recovery codes are stored as hashes. A code that
 * worked can't be used again (the last step is remembered).
 */
final class TwoFactor
{
    private const SETUP_KEY = 'two_factor_setup';
    public const RECOVERY_CODES = 10;

    public function __construct(
        private UserRepository $users,
        private Secrets $secrets,
        private Session $session,
        private Clock $clock,
    ) {
    }

    public function available(): bool
    {
        return $this->secrets->available();
    }

    public function enabled(int $userId): bool
    {
        return $this->users->twoFactor($userId) !== null;
    }

    /** A new secret for the setup screen, kept (encrypted) in the session until confirmed. */
    public function begin(): string
    {
        $this->assertAvailable();
        $current = $this->pendingSecret();
        if ($current !== null) {
            return $current;
        }
        $secret = Totp::newSecret();
        $this->session->put(self::SETUP_KEY, $this->secrets->encrypt($secret));

        return $secret;
    }

    public function pendingSecret(): ?string
    {
        $sealed = $this->session->get(self::SETUP_KEY);

        return is_string($sealed) && $this->available() ? $this->secrets->decrypt($sealed) : null;
    }

    /**
     * Turns it on when the code from the app matches the pending secret.
     *
     * @return list<string> the recovery codes, shown once
     */
    public function confirm(int $userId, string $code): array
    {
        $this->assertAvailable();
        $secret = $this->pendingSecret();
        $step = $secret !== null ? Totp::verify($secret, $code, $this->clock->now()->getTimestamp()) : null;
        if ($secret === null || $step === null) {
            $this->fail('code', 'That code is not right. Check the time on your phone and try the newest code.');
        }
        [$codes, $hashes] = $this->newRecoveryCodes();
        $this->users->setTwoFactor($userId, $this->secrets->encrypt($secret), json_encode(['codes' => $hashes, 'last' => $step], JSON_THROW_ON_ERROR));
        $this->session->forget(self::SETUP_KEY);

        return $codes;
    }

    public function disable(int $userId): void
    {
        $this->users->setTwoFactor($userId, null, null);
    }

    /** @return list<string> new recovery codes; the old ones stop working */
    public function regenerateRecoveryCodes(int $userId): array
    {
        $stored = $this->stored($userId) ?? $this->fail('code', 'Two-step sign-in is off.');
        [$codes, $hashes] = $this->newRecoveryCodes();
        $this->users->setRecovery($userId, json_encode(['codes' => $hashes, 'last' => $stored['last']], JSON_THROW_ON_ERROR));

        return $codes;
    }

    public function recoveryCodesLeft(int $userId): int
    {
        return count($this->stored($userId)['codes'] ?? []);
    }

    /** A code from the app, or one of the recovery codes (used up when it works). */
    public function verify(int $userId, string $code): bool
    {
        $stored = $this->stored($userId);
        if ($stored === null) {
            return false;
        }
        $secret = $this->secrets->decrypt($stored['secret']);
        $step = $secret !== null ? Totp::verify($secret, $code, $this->clock->now()->getTimestamp()) : null;
        if ($step !== null && $step > $stored['last']) {
            $this->users->setRecovery($userId, json_encode(['codes' => $stored['codes'], 'last' => $step], JSON_THROW_ON_ERROR));

            return true;
        }

        $hash = hash('sha256', strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
        foreach ($stored['codes'] as $i => $known) {
            if (hash_equals($known, $hash)) {
                unset($stored['codes'][$i]);
                $this->users->setRecovery($userId, json_encode(['codes' => array_values($stored['codes']), 'last' => $stored['last']], JSON_THROW_ON_ERROR));

                return true;
            }
        }

        return false;
    }

    /** @return array{secret: string, codes: list<string>, last: int}|null */
    private function stored(int $userId): ?array
    {
        $row = $this->users->twoFactor($userId);
        if ($row === null) {
            return null;
        }
        $data = json_decode($row['recovery'], true);

        return [
            'secret' => $row['secret'],
            'codes'  => is_array($data) && is_array($data['codes'] ?? null) ? array_values(array_map('strval', $data['codes'])) : [],
            'last'   => is_array($data) ? (int) ($data['last'] ?? 0) : 0,
        ];
    }

    /** @return array{0: list<string>, 1: list<string>} codes like "a1b2c-d3e4f" and their hashes */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $raw = bin2hex(random_bytes(5));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }

        return [$codes, array_map(static fn (string $c) => hash('sha256', str_replace('-', '', $c)), $codes)];
    }

    private function assertAvailable(): void
    {
        if (!$this->available()) {
            $this->fail('code', 'Two-step sign-in needs APP_KEY in the .env file. Ask whoever runs the app to add one (php bin/app-key.php).');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
