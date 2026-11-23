<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Core\Container;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Router;
use App\Modules\Identity\Application\AccountCleanup;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\EmailVerification;
use App\Modules\Identity\Application\PasswordReset;
use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Identity\Domain\LoginThrottle;
use App\Modules\Identity\Domain\SecurityLog;
use App\Modules\Identity\Domain\UserRepository;
use App\Modules\Identity\Domain\UserTokens;
use App\Modules\Identity\Http\AccountSecurityController;
use App\Modules\Identity\Http\EmailVerificationController;
use App\Modules\Identity\Http\LoginController;
use App\Modules\Identity\Http\PasswordResetController;
use App\Modules\Identity\Http\RegisterController;
use App\Modules\Identity\Infrastructure\PdoLoginThrottle;
use App\Modules\Identity\Infrastructure\PdoSecurityLog;
use App\Modules\Identity\Infrastructure\PdoUserRepository;
use App\Modules\Identity\Infrastructure\PdoUserTokens;
use App\Modules\Invitation\Contract\Events\InvitationAccepted;
use App\Security\RateLimited;
use App\Security\RateLimitRule;
use App\Security\RateLimits;
use App\View\View;
use App\Shared\Events\EventBus;
use App\Shared\Module\ModuleProvider;
use App\Support\Clock;
use PDO;

/**
 * Accounts, sign up, log in, log out and login throttling, email
 * confirmation, password reset links and the account security log.
 * Publishes: identity.user_registered, identity.user_logged_in.
 * Listens to: security.rate_limited (written to the security log),
 * invitation.accepted (the invited address is confirmed).
 * Offers: Contract\Authenticator, Contract\UserDirectory.
 */
final class IdentityModule implements ModuleProvider
{
    public function name(): string
    {
        return 'identity';
    }

    public function register(Container $container): void
    {
        $container->extend(\App\Shared\Export\PersonalData::class, function (object $data, Container $c): void {
            /** @var \App\Shared\Export\PersonalData $data */
            $db = fn (): \PDO => $c->get(\PDO::class);
            $rows = static function (\PDO $db, string $sql, int $userId): array {
                $stmt = $db->prepare($sql);
                $stmt->execute([$userId]);

                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            };
            $data->add('profile', static function (int $userId) use ($db, $rows): array {
                return ($rows($db(), 'SELECT name, email, locale, created_at, email_verified_at, two_factor_enabled_at FROM users WHERE id = ?', $userId)[0] ?? [])
                    + ['security_events' => $rows($db(), 'SELECT type, ip, detail, created_at FROM security_events WHERE user_id = ? ORDER BY id DESC', $userId),
                       'signed_in_devices' => $rows($db(), 'SELECT ip, user_agent, created_at, last_seen_at FROM user_sessions WHERE user_id = ?', $userId)];
            });
            $data->eraseWith('identity', static fn (int $userId) => \App\Shared\Export\PersonalData::deleteRows($db(), $userId, ['security_events', 'user_tokens']));
        });

        $container->singleton(PdoUserRepository::class, fn (Container $c) => new PdoUserRepository($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(UserRepository::class, fn (Container $c) => $c->get(PdoUserRepository::class));
        $container->singleton(UserDirectory::class, fn (Container $c) => $c->get(PdoUserRepository::class));
        $container->singleton(LoginThrottle::class, fn (Container $c) => new PdoLoginThrottle($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(UserTokens::class, fn (Container $c) => new PdoUserTokens($c->get(PDO::class)));
        $container->singleton(SecurityLog::class, fn (Container $c) => new PdoSecurityLog($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(PasswordReset::class, fn (Container $c) => $c->build(PasswordReset::class));
        $container->singleton(EmailVerification::class, fn (Container $c) => $c->build(EmailVerification::class));
        $container->singleton(AccountCleanup::class, fn (Container $c) => $c->build(AccountCleanup::class));
        $container->singleton(\App\Modules\Identity\Application\DeviceSessions::class, fn (Container $c) => new \App\Modules\Identity\Application\DeviceSessions($c->get(PDO::class), $c->get(Clock::class)));
        $container->singleton(\App\Modules\Identity\Application\TwoFactor::class, fn (Container $c) => $c->build(\App\Modules\Identity\Application\TwoFactor::class));
        $container->singleton(\App\Modules\Identity\Application\AccountSettings::class, fn (Container $c) => $c->build(\App\Modules\Identity\Application\AccountSettings::class));

        // One instance behind both names, so the "current user" is looked up once per request.
        $container->singleton(AuthService::class, fn (Container $c) => $c->build(AuthService::class));
        $container->singleton(Authenticator::class, fn (Container $c) => $c->get(AuthService::class));

        // Requests per window. On top of these, LoginThrottle blocks sign-in after 5 failures for one
        // account from one IP, 20 for one account from anywhere, or 50 from one IP across accounts.
        $container->extend(RateLimits::class, function (object $limits): void {
            /** @var RateLimits $limits */
            $limits->add(new RateLimitRule('login', 'POST', '/login', 10, 60));
            $limits->add(new RateLimitRule('register', 'POST', '/register', 10, 3600));
            $limits->add(new RateLimitRule('password-reset-ip', 'POST', '/forgot-password', 5, 900));
            $limits->add(new RateLimitRule('password-reset-email', 'POST', '/forgot-password', 3, 900, 'field:email'));
            $limits->add(new RateLimitRule('password-reset-submit', 'POST', '/reset-password/*', 10, 900));
            $limits->add(new RateLimitRule('email-link', 'GET', '/verify-email/*', 20, 900));
            $limits->add(new RateLimitRule('verification-resend', 'POST', '/email/verification', 3, 900, 'user'));
            $limits->add(new RateLimitRule('two-factor-login', 'POST', '/login/two-factor', 10, 300));
            $limits->add(new RateLimitRule('two-factor-confirm', 'POST', '/account/two-factor/*', 10, 300, 'user'));
            $limits->add(new RateLimitRule('account-delete', 'POST', '/account/delete', 5, 900, 'user'));
        });

        // Unconfirmed accounts get a banner under the app header.
        $container->extend(View::class, function (object $view): void {
            /** @var View $view */
            $view->addToSlot('header.after', 'identity/verify-banner');
        });
    }

    public function routes(Router $router): void
    {
        $router->group([RedirectIfAuthenticated::class], function (Router $router): void {
            $router->get('/login', [LoginController::class, 'show']);
            $router->post('/login', [LoginController::class, 'store']);
            $router->get('/register', [RegisterController::class, 'show']);
            $router->post('/register', [RegisterController::class, 'store']);
            $router->get('/forgot-password', [PasswordResetController::class, 'showRequest']);
            $router->post('/forgot-password', [PasswordResetController::class, 'request']);
            $router->get('/login/two-factor', [LoginController::class, 'twoFactorForm']);
            $router->post('/login/two-factor', [LoginController::class, 'twoFactor']);
        });

        // Links from emails work whether or not someone is signed in on this browser.
        $router->get('/reset-password/{token:token}', [PasswordResetController::class, 'showReset']);
        $router->post('/reset-password/{token:token}', [PasswordResetController::class, 'reset']);
        $router->get('/verify-email/{token:token}', [EmailVerificationController::class, 'verify']);

        $router->group([Authenticate::class], function (Router $router): void {
            $router->post('/logout', [LoginController::class, 'destroy']);
            $router->post('/email/verification', [EmailVerificationController::class, 'resend']);
            $router->get('/account/security', [AccountSecurityController::class, 'index']);
            $router->post('/account/two-factor/start', [AccountSecurityController::class, 'startTwoFactor']);
            $router->post('/account/two-factor/confirm', [AccountSecurityController::class, 'confirmTwoFactor']);
            $router->post('/account/two-factor/disable', [AccountSecurityController::class, 'disableTwoFactor']);
            $router->post('/account/two-factor/recovery', [AccountSecurityController::class, 'newRecoveryCodes']);
            $router->post('/account/sessions/others', [AccountSecurityController::class, 'endOtherSessions']);
            $router->post('/account/sessions/all', [AccountSecurityController::class, 'endAllSessions']);
            $router->post('/account/sessions/{id}', [AccountSecurityController::class, 'endSession']);
            $router->get('/account', [\App\Modules\Identity\Http\AccountController::class, 'index']);
            $router->post('/account/profile', [\App\Modules\Identity\Http\AccountController::class, 'profile']);
            $router->get('/account/export.json', [\App\Modules\Identity\Http\AccountController::class, 'export']);
            $router->post('/account/delete', [\App\Modules\Identity\Http\AccountController::class, 'delete']);
        });
    }

    public function listeners(EventBus $events, Container $container): void
    {
        $events->listen(RateLimited::name(), static function (RateLimited $e) use ($container): void {
            $container->get(SecurityLog::class)->record('rate_limited', $e->userId, $e->ip, $e->rule);
        });

        // An invitation link only works for the account with the invited email,
        // and it was sent to that address: opening it proves the address too.
        $events->listen(InvitationAccepted::name(), static function (InvitationAccepted $e) use ($container): void {
            $user = $container->get(UserRepository::class)->find($e->userId);
            if ($user !== null && !$user->emailVerified && $user->email === mb_strtolower($e->email)) {
                $container->get(UserRepository::class)->markEmailVerified($user->id);
                $container->get(AuthService::class)->refresh();
            }
        });
    }

    public function migrationsPath(): ?string
    {
        // users and login_attempts live in the core baseline migration.
        return null;
    }
}
