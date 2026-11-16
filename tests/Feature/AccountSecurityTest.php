<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\ConnectionFactory;
use App\Database\Migrations\MigrationRunner;
use App\Http\Request;
use App\Modules\Identity\Application\PasswordReset;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Support\Clock;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class AccountSecurityTest extends TestCase
{
    public function testSigningUpSendsAConfirmationLinkThatWorksOnce(): void
    {
        $app = new App();
        $app->signUp('Sara Ali', 'sara@example.com', confirm: false);

        $this->assertSame(null, $app->pdo()->query("SELECT email_verified_at FROM users WHERE email = 'sara@example.com'")->fetchColumn());
        $this->assertTrue(str_contains($app->page('/'), 'Please confirm your email'), 'An unconfirmed account sees the banner.');

        $link = $app->linkFromLastEmail('sara@example.com', '/verify-email/');
        $this->assertTrue($link !== null);
        $secret = basename((string) $link);
        $hashes = $app->pdo()->query('SELECT token_hash FROM user_tokens')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame([hash('sha256', $secret)], $hashes, 'Only a hash of the secret is stored.');

        $this->assertSame('/', $app->get((string) $link)->header('Location'));
        $this->assertFalse(str_contains($app->page('/'), 'Please confirm your email'));
        $this->assertSame('/', $app->get((string) $link)->header('Location'), 'Signed in and already confirmed: back to the app.');
        $app->post('/logout');
        $this->assertSame(410, $app->get((string) $link)->status, 'A used link is refused.');
    }

    public function testAnUnconfirmedAccountCannotInviteOrCreateTokens(): void
    {
        $app = new App();
        $f = new BoardFixture($app);
        $app->signUp('Nour', 'nour@example.com', confirm: false);
        $nour = (int) $app->session->get('user_id');
        $workspace = $app->container->get(WorkspaceDirectory::class)->current($nour);

        $app->post('/workspaces/' . $workspace->publicId . '/invitations', ['email' => 'omar@example.com', 'role' => 'member', 'board_role' => 'member']);
        $this->assertSame(0, (int) $app->pdo()->query("SELECT COUNT(*) FROM invitations WHERE email = 'omar@example.com'")->fetchColumn());

        $app->post('/integrations/tokens', ['name' => 'Script', 'scope' => 'read', 'days' => '90', 'boards' => [$f->board->publicId]]);
        $this->assertSame(0, (int) $app->pdo()->query('SELECT COUNT(*) FROM api_tokens WHERE user_id = ' . $nour)->fetchColumn());

        $app->post('/email/verification');
        $app->get((string) $app->linkFromLastEmail('nour@example.com', '/verify-email/'));
        $app->post('/workspaces/' . $workspace->publicId . '/invitations', ['email' => 'omar@example.com', 'role' => 'member', 'board_role' => 'member']);
        $this->assertSame(1, (int) $app->pdo()->query("SELECT COUNT(*) FROM invitations WHERE email = 'omar@example.com'")->fetchColumn(), 'Allowed once confirmed.');
    }

    public function testAResetRequestLooksTheSameForAnyEmail(): void
    {
        $app = new App();
        $app->signUp();
        $app->post('/logout');

        $known = $app->post('/forgot-password', ['email' => 'sara@example.com']);
        $flashKnown = $app->session->pullFlash('success');
        $unknown = $app->post('/forgot-password', ['email' => 'nobody@example.com']);
        $flashUnknown = $app->session->pullFlash('success');

        $this->assertSame($known->status, $unknown->status);
        $this->assertSame($known->header('Location'), $unknown->header('Location'));
        $this->assertSame($flashKnown, $flashUnknown);
        $this->assertCount(1, array_filter($app->sentMail(), static fn ($m) => $m->subject === 'Reset your password'));
        $this->assertSame(null, $app->linkFromLastEmail('nobody@example.com', '/reset-password/'));
    }

    public function testAResetLinkChangesThePasswordOnceAndSignsOutOtherSessions(): void
    {
        $app = new App();
        $id = $app->signUp();
        $oldVersion = $app->session->get('auth_version');
        $app->post('/forgot-password'); // signed in: guests only, nothing happens
        $app->container->get(PasswordReset::class)->request('sara@example.com', '10.0.0.9');
        $link = (string) $app->linkFromLastEmail('sara@example.com', '/reset-password/');

        $this->assertSame(200, $app->get($link)->status);
        $short = $app->post($link, ['password' => 'short', 'password_confirmation' => 'short']);
        $this->assertSame($link, $short->header('Location'), 'A weak password goes back to the form, the link stays usable.');

        $app->post($link, ['password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1']);
        $this->assertSame($id, $app->session->get('user_id'), 'This browser is signed in with the new password.');
        $this->assertTrue($app->session->get('auth_version') !== $oldVersion);

        $this->assertSame(410, $app->get($link)->status, 'The link works once.');

        // A session that started before the change (another device) is signed out.
        $app->session->put('auth_version', $oldVersion);
        $app->container->get(\App\Modules\Identity\Application\AuthService::class)->refresh(); // a new request
        $this->assertSame('/login', $app->get('/')->header('Location'));

        $app->post('/login', ['email' => 'sara@example.com', 'password' => 'secret123']);
        $this->assertNull($app->session->get('user_id'));
        $app->post('/login', ['email' => 'sara@example.com', 'password' => 'brand-new-1']);
        $this->assertSame($id, $app->session->get('user_id'));
    }

    public function testResetLinksExpireAndANewRequestRetiresTheOldLink(): void
    {
        $app = new App();
        $app->signUp();
        $app->post('/logout');

        $app->post('/forgot-password', ['email' => 'sara@example.com']);
        $first = (string) $app->linkFromLastEmail('sara@example.com', '/reset-password/');
        $app->post('/forgot-password', ['email' => 'SARA@example.com ']);
        $second = (string) $app->linkFromLastEmail('sara@example.com', '/reset-password/');

        $this->assertTrue($first !== $second);
        $this->assertSame(410, $app->get($first)->status, 'Asking again retires the older link.');
        $this->assertSame(200, $app->get($second)->status);

        $app->container->get(Clock::class)->travel('+61 minutes');
        $this->assertSame(410, $app->get($second)->status, 'Expired after an hour.');
        $app->post($second, ['password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1']);
        $this->assertNull($app->session->get('user_id'));
    }

    public function testTheSecurityPageListsTheAccountsOwnEvents(): void
    {
        $app = new App();
        $app->signUp();
        $app->post('/logout');
        $app->post('/login', ['email' => 'sara@example.com', 'password' => 'wrong-one']);
        $app->post('/login', ['email' => 'sara@example.com', 'password' => 'secret123']);

        $page = $app->page('/account/security');
        foreach (['Account created', 'Email confirmed', 'Failed sign-in', 'Signed in', 'Signed out'] as $event) {
            $this->assertTrue(str_contains($page, $event), "Shows: $event");
        }

        $app->signUp('Mona', 'mona@example.com');
        $this->assertFalse(str_contains($app->page('/account/security'), 'Failed sign-in'), "Nobody sees another account's events.");
    }

    public function testAcceptingAnInvitationConfirmsTheInvitedAddress(): void
    {
        $app = new App();
        $f = new BoardFixture($app);
        $workspace = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $app->post('/workspaces/' . $workspace->publicId . '/invitations', ['email' => 'omar@example.com', 'role' => 'member', 'board_role' => 'member']);
        $invite = (string) $app->linkFromLastEmail('omar@example.com', '/invitations/');

        $app->signUp('Omar', 'omar@example.com', confirm: false);
        $app->post($invite . '/accept');

        $this->assertTrue(is_string($app->pdo()->query("SELECT email_verified_at FROM users WHERE email = 'omar@example.com'")->fetchColumn()));
    }

    public function testCleanupRemovesUsedLinksAndOldEvents(): void
    {
        $app = new App();
        $app->signUp();
        $pdo = $app->pdo();
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM user_tokens')->fetchColumn());

        $app->container->get(Clock::class)->travel('+200 days');
        $removed = $app->container->get(\App\Modules\Identity\Application\AccountCleanup::class)->run(180);

        $this->assertSame(1, $removed['tokens']);
        $this->assertTrue($removed['security_events'] >= 2);
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM security_events')->fetchColumn());
    }

    public function testUpgradingAnOldDatabaseKeepsItsAccountsWorking(): void
    {
        $dir = App::scratch('old-schema');
        mkdir($dir, 0777, true);
        copy(BASE_PATH . '/database/migrations/2026_01_01_000000_core_baseline.sqlite.sql', $dir . '/2026_01_01_000000_core_baseline.sqlite.sql');

        $pdo = ConnectionFactory::sqlite(':memory:');
        (new MigrationRunner($pdo, [$dir]))->migrate();
        $pdo->exec("INSERT INTO users (name, email, password, created_at) VALUES ('Old', 'old@example.com', 'x', '2025-05-01 09:00:00')");

        (new MigrationRunner($pdo, [BASE_PATH . '/database/migrations']))->migrate();

        $row = $pdo->query("SELECT name, password, email_verified_at, auth_version FROM users WHERE email = 'old@example.com'")->fetch();
        $this->assertSame(['name' => 'Old', 'password' => 'x', 'email_verified_at' => '2025-05-01 09:00:00', 'auth_version' => 1], $row);
    }

    public function testOldSessionsWithoutAnAuthVersionStaySignedIn(): void
    {
        $app = new App();
        $id = $app->signUp();
        $app->session->forget('auth_version');

        $this->assertSame(200, $app->get('/')->status);
        $this->assertSame($id, $app->session->get('user_id'));
    }

    public function testEmailLinksDoNotNeedTheSessionCookieOrCsrfForGet(): void
    {
        $app = new App();
        $app->signUp('Sara', 'sara@example.com', confirm: false);
        $link = (string) $app->linkFromLastEmail('sara@example.com', '/verify-email/');
        $app->post('/logout');

        $response = $app->send(new Request('GET', $link, [], [], [], '10.0.0.7'));
        $this->assertSame('/login', $response->header('Location'));
        $this->assertTrue(is_string($app->pdo()->query("SELECT email_verified_at FROM users WHERE email = 'sara@example.com'")->fetchColumn()));
    }
}
