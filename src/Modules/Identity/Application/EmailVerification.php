<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Core\Config;
use App\I18n\Translator;
use App\Mail\Mailer;
use App\Mail\Message;
use App\Modules\Identity\Contract\User;
use App\Modules\Identity\Domain\SecretToken;
use App\Modules\Identity\Domain\SecurityLog;
use App\Modules\Identity\Domain\UserRepository;
use App\Modules\Identity\Domain\UserTokens;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;

/**
 * Confirms that a new account's owner really reads its email address.
 *
 * An unconfirmed account can sign in and use its own boards, and sees a
 * banner to confirm. What could reach other people waits for confirmation:
 * sending invitations and creating API tokens (see User::$emailVerified).
 */
final class EmailVerification
{
    public const VALID_HOURS = 48;
    private const MAX_PER_HOUR = 5;

    public function __construct(
        private UserRepository $users,
        private UserTokens $tokens,
        private SecurityLog $log,
        private Mailer $mailer,
        private Translator $translator,
        private Config $config,
        private Clock $clock,
    ) {
    }

    public function send(User $user, string $ip): void
    {
        if ($user->emailVerified) {
            return;
        }

        $now = $this->now();
        $token = SecretToken::generate();
        $this->tokens->revokeAll($user->id, UserTokens::EMAIL_VERIFICATION, $now);
        $this->tokens->create(
            $user->id,
            UserTokens::EMAIL_VERIFICATION,
            $token->hash(),
            $this->clock->now()->modify('+' . self::VALID_HOURS . ' hours')->format('Y-m-d H:i:s'),
            $now,
        );

        $t = fn (string $text, array $replace = []): string => $this->translator->t($text, $replace);
        $body = implode("\n", [
            $t('Hello :name,', ['name' => $user->name]),
            '',
            $t('Please confirm your email address by opening this link:'),
            rtrim((string) $this->config->get('url', ''), '/') . '/verify-email/' . $token->value,
            '',
            $t('The link expires in :hours hours.', ['hours' => (string) self::VALID_HOURS]),
            $t('If you did not create an account, you can ignore this email.'),
        ]);

        $this->mailer->send(new Message($user->email, $user->name, $t('Confirm your email address'), $body));
        $this->log->record('verification_sent', $user->id, $ip);
    }

    /** @throws ValidationException when too many emails were sent in the last hour */
    public function resend(User $user, string $ip): void
    {
        $since = $this->clock->now()->modify('-1 hour')->format('Y-m-d H:i:s');

        if ($this->tokens->countSince($user->id, UserTokens::EMAIL_VERIFICATION, $since) >= self::MAX_PER_HOUR) {
            throw new ValidationException(new ValidationResult(['verification' => 'We already sent several emails. Please wait an hour and try again.']));
        }

        $this->send($user, $ip);
    }

    /** Confirms the address behind the link. Returns the account's id, or null for a bad link. */
    public function verify(string $token, string $ip): ?int
    {
        $hash = SecretToken::fromLink($token)->hash();
        $now = $this->now();
        $userId = $this->tokens->findUsable(UserTokens::EMAIL_VERIFICATION, $hash, $now);

        if ($userId === null || !$this->tokens->consume(UserTokens::EMAIL_VERIFICATION, $hash, $now)) {
            return null;
        }

        $this->users->markEmailVerified($userId);
        $this->log->record('email_verified', $userId, $ip);

        return $userId;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
