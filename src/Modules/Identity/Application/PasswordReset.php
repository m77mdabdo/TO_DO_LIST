<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Core\Config;
use App\Database\Transactions;
use App\I18n\Translator;
use App\Mail\Mailer;
use App\Mail\Message;
use App\Modules\Identity\Domain\PasswordHasher;
use App\Modules\Identity\Domain\SecretToken;
use App\Modules\Identity\Domain\SecurityLog;
use App\Modules\Identity\Domain\UserRepository;
use App\Modules\Identity\Domain\UserTokens;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

/**
 * "Forgot password": a one-time link by email that lets the owner of the
 * address choose a new password.
 *
 *  - The answer to a request is the same whether the email has an account
 *    or not, so the form can't be used to find out who is registered.
 *  - The link is 256 random bits; only its SHA-256 is stored. It expires
 *    after an hour, works once, and asking again retires the older link.
 *  - A new password signs out every other session of the account.
 */
final class PasswordReset
{
    public const VALID_MINUTES = 60;
    /** Emails per account per hour, on top of the per-IP and per-email request limits. */
    private const MAX_PER_HOUR = 5;

    public function __construct(
        private UserRepository $users,
        private UserTokens $tokens,
        private PasswordHasher $hasher,
        private SecurityLog $log,
        private Mailer $mailer,
        private Translator $translator,
        private Transactions $transactions,
        private Validator $validator,
        private Config $config,
        private Clock $clock,
    ) {
    }

    /** @throws ValidationException only when the email is not an email at all */
    public function request(string $email, string $ip): void
    {
        $email = mb_strtolower(trim($email));
        $result = $this->validator->validate(['email' => $email], ['email' => ['Email', 'required|email|max:190']]);

        if ($result->fails()) {
            throw new ValidationException($result);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            // Logged without the address, so the log can't be mined for addresses either.
            $this->log->record('password_reset_requested', null, $ip, 'unknown email');

            return;
        }

        $now = $this->now();
        if ($this->tokens->countSince($user->id, UserTokens::PASSWORD_RESET, $this->clock->now()->modify('-1 hour')->format('Y-m-d H:i:s')) >= self::MAX_PER_HOUR) {
            $this->log->record('password_reset_requested', $user->id, $ip, 'not sent: hourly limit');

            return;
        }

        $token = SecretToken::generate();
        $this->transactions->run(function () use ($user, $token, $now): void {
            $this->tokens->revokeAll($user->id, UserTokens::PASSWORD_RESET, $now);
            $this->tokens->create($user->id, UserTokens::PASSWORD_RESET, $token->hash(), $this->expiry(), $now);
        });

        $t = fn (string $text, array $replace = []): string => $this->translator->t($text, $replace);
        $body = implode("\n", [
            $t('Hello :name,', ['name' => $user->name]),
            '',
            $t('Someone asked to reset the password of your To Do List account. Open this link to choose a new one:'),
            $this->link('/reset-password/' . $token->value),
            '',
            $t('The link works once and expires in :minutes minutes.', ['minutes' => (string) self::VALID_MINUTES]),
            $t('If it was not you, ignore this email: your password stays the same.'),
        ]);

        $this->mailer->send(new Message($user->email, $user->name, $t('Reset your password'), $body));
        $this->log->record('password_reset_requested', $user->id, $ip);
    }

    /** True when the link can still be used (to show the form or an "expired" page). */
    public function isUsable(string $token): bool
    {
        return $this->tokens->findUsable(UserTokens::PASSWORD_RESET, SecretToken::fromLink($token)->hash(), $this->now()) !== null;
    }

    /**
     * @param array<string, mixed> $input password, password_confirmation
     * @return int the account's id
     * @throws ValidationException
     */
    public function reset(string $token, array $input, string $ip): int
    {
        $hash = SecretToken::fromLink($token)->hash();
        $userId = $this->tokens->findUsable(UserTokens::PASSWORD_RESET, $hash, $this->now());

        if ($userId === null) {
            $this->fail('token', 'This reset link is invalid or has expired. Ask for a new one.');
        }

        $result = $this->validator->validate($input, ['password' => ['Password', 'required|min:8|max:72']]);
        if (!$result->has('password') && ($input['password'] ?? null) !== ($input['password_confirmation'] ?? null)) {
            $result = $result->with('password_confirmation', "Passwords don't match.");
        }
        if ($result->fails()) {
            throw new ValidationException($result);
        }

        $this->transactions->run(function () use ($hash, $userId, $input): void {
            $now = $this->now();
            if (!$this->tokens->consume(UserTokens::PASSWORD_RESET, $hash, $now)) {
                $this->fail('token', 'This reset link is invalid or has expired. Ask for a new one.');
            }
            $this->users->changePassword($userId, $this->hasher->hash((string) $input['password']));
            // Only the owner of the address could open the link, so the address is confirmed too.
            $this->users->markEmailVerified($userId);
            $this->tokens->revokeAll($userId, UserTokens::PASSWORD_RESET, $now);
        });

        $this->log->record('password_reset', $userId, $ip);

        return $userId;
    }

    private function link(string $path): string
    {
        return rtrim((string) $this->config->get('url', ''), '/') . $path;
    }

    private function expiry(): string
    {
        return $this->clock->now()->modify('+' . self::VALID_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
