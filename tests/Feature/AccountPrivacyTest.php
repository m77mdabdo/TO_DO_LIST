<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Application\TwoFactor;
use App\Modules\Identity\Domain\Totp;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Support\Clock;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Personal settings, signed-in devices, two-step sign-in, "download my data" and deleting the account. */
final class AccountPrivacyTest extends TestCase
{
    public function testNameAndLanguageAreSavedAndTheLanguageComesBackOnSignIn(): void
    {
        $app = new App();
        $app->signUp('Sara Ali', 'sara@example.com');

        $app->post('/account/profile', ['name' => 'Sara A.', 'locale' => 'ar']);
        $this->assertTrue(str_contains($app->page('/account'), 'Sara A.'));

        $app->post('/logout');
        $app->post('/locale/en');
        $app->loginAs('sara@example.com');
        $this->assertTrue(str_contains($app->page('/account'), 'lang="ar"'), 'The saved language is used after signing in.');

        $app->post('/account/profile', ['name' => 'x', 'locale' => 'fr']);
        $this->assertSame('Sara A.', (string) $app->pdo()->query("SELECT name FROM users WHERE email = 'sara@example.com'")->fetchColumn());
    }

    public function testSigningOutOtherDevicesEndsTheirSessions(): void
    {
        $app = new App();
        $app->signUp('Sara Ali', 'sara@example.com');
        $this->assertSame(1, (int) $app->pdo()->query('SELECT COUNT(*) FROM user_sessions')->fetchColumn());

        $app->loginAs('sara@example.com');
        $page = $app->page('/account/security');
        $this->assertTrue(str_contains($page, 'Signed-in devices'));
        $this->assertTrue(str_contains($page, 'This device'));

        // A second sign-in row, as if from a phone.
        $app->pdo()->exec("INSERT INTO user_sessions (user_id, token_hash, ip, user_agent, created_at, last_seen_at)
            SELECT id, 'phone', '10.0.0.9', 'Mozilla/5.0 (iPhone) Safari/604.1', '2026-01-20 09:00:00', '2026-01-20 09:00:00' FROM users WHERE email = 'sara@example.com'");
        $this->assertTrue(str_contains($app->page('/account/security'), 'Safari · iOS'));

        $app->post('/account/sessions/others');
        $this->assertSame(1, (int) $app->pdo()->query('SELECT COUNT(*) FROM user_sessions')->fetchColumn());
        $this->assertSame(200, $app->get('/account/security')->status, 'This device stays signed in.');

        $app->post('/account/sessions/all');
        $this->assertSame(0, (int) $app->pdo()->query('SELECT COUNT(*) FROM user_sessions')->fetchColumn());
        $this->assertSame(302, $app->get('/account/security')->status);
    }

    public function testADeletedSessionRowSignsThatBrowserOut(): void
    {
        $app = new App();
        $app->signUp('Sara Ali', 'sara@example.com');
        $this->assertSame(200, $app->get('/account')->status);

        $app->pdo()->exec('DELETE FROM user_sessions');
        $app->container->get(\App\Modules\Identity\Application\AuthService::class)->refresh();
        $this->assertSame(302, $app->get('/account')->status);
    }

    public function testTwoStepSignInWithTheAppAndWithARecoveryCode(): void
    {
        $app = new App();
        $app->signUp('Sara Ali', 'sara@example.com');
        $clock = $app->container->get(Clock::class);

        $app->post('/account/two-factor/start');
        $secret = (string) $app->container->get(TwoFactor::class)->pendingSecret();
        $this->assertTrue(str_contains($app->page('/account/security'), Totp::grouped($secret)));
        $app->post('/account/two-factor/confirm', ['code' => '000000']);
        $this->assertNull($app->pdo()->query("SELECT two_factor_enabled_at FROM users")->fetchColumn() ?: null);

        $app->post('/account/two-factor/confirm', ['code' => Totp::code($secret, $clock->now()->getTimestamp())]);
        $page = $app->page('/account/security');
        preg_match_all('#<code>([0-9a-f]{5}-[0-9a-f]{5})</code>#', $page, $m);
        $this->assertCount(10, $m[1]);
        $stored = (string) $app->pdo()->query('SELECT two_factor_secret FROM users')->fetchColumn();
        $this->assertFalse(str_contains($stored, $secret), 'The secret is stored encrypted.');

        // Password alone is not enough now.
        $app->loginAs('sara@example.com');
        $this->assertSame(302, $app->get('/account')->status);
        $this->assertSame(200, $app->get('/login/two-factor')->status);
        $app->post('/login/two-factor', ['code' => '123456']);
        $this->assertSame(302, $app->get('/account')->status);

        $clock->travel('+1 minute');
        $app->post('/login/two-factor', ['code' => Totp::code($secret, $clock->now()->getTimestamp())]);
        $this->assertSame(200, $app->get('/account')->status);

        // The same code can't be used twice; a recovery code works once.
        $code = Totp::code($secret, $clock->now()->getTimestamp());
        $app->loginAs('sara@example.com');
        $app->post('/login/two-factor', ['code' => $code]);
        $this->assertSame(302, $app->get('/account')->status);
        $app->post('/login/two-factor', ['code' => $m[1][0]]);
        $this->assertSame(200, $app->get('/account')->status);
        $app->loginAs('sara@example.com');
        $app->post('/login/two-factor', ['code' => $m[1][0]]);
        $this->assertSame(302, $app->get('/account')->status);

        // Five wrong codes end the attempt.
        for ($i = 0; $i < 5; $i++) {
            $app->post('/login/two-factor', ['code' => '000000']);
        }
        $this->assertSame(302, $app->get('/login/two-factor')->status, 'Back to the password.');
    }

    public function testDownloadMyDataHoldsOnlyMyThings(): void
    {
        $f = new BoardFixture(new App());
        $f->colleague('mona@example.com');
        $f->app->loginAs('sara@example.com');

        $response = $f->app->get('/account/export.json');
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('sara@example.com', $data['profile']['email']);
        $this->assertSame('owner', $data['workspaces'][0]['role']);
        $this->assertArrayHasKey('comments', $data);
        $this->assertArrayHasKey('notifications', $data);
        $this->assertFalse(str_contains($response->body, 'mona@example.com'));
        $this->assertFalse(str_contains($response->body, '"password"'));
    }

    public function testTheSoleOwnerHandsOverBeforeDeletingAndTheAccountIsEmptied(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $mona = $f->colleague('mona@example.com');
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $app->loginAs('sara@example.com');

        $this->assertTrue(str_contains($app->page('/account'), 'hand over the workspaces you own alone'));
        $app->post('/account/delete', ['password' => 'secret123', 'confirm' => 'DELETE']);
        $this->assertSame(200, $app->get('/account')->status, 'Not deleted while the workspace has no other owner.');

        $app->post('/workspaces/' . $ws->publicId . '/owner', ['user_id' => (string) $mona]);
        $app->post('/account/delete', ['password' => 'wrong-one', 'confirm' => 'DELETE']);
        $this->assertSame(200, $app->get('/account')->status, 'A wrong password changes nothing.');

        $app->post('/account/delete', ['password' => 'secret123', 'confirm' => 'DELETE']);
        $this->assertSame(302, $app->get('/account')->status);
        $row = $app->pdo()->query('SELECT name, email, deleted_at FROM users WHERE id = ' . $f->owner)->fetch();
        $this->assertSame('Deleted user', (string) $row['name']);
        $this->assertFalse(str_contains((string) $row['email'], 'sara'));
        $this->assertSame(0, (int) $app->pdo()->query('SELECT COUNT(*) FROM workspace_members WHERE user_id = ' . $f->owner)->fetchColumn());
        $this->assertSame(1, (int) $app->pdo()->query('SELECT COUNT(*) FROM boards WHERE id = ' . $f->board->id)->fetchColumn(), 'Shared work stays.');

        $app->post('/login', ['email' => 'sara@example.com', 'password' => 'secret123']);
        $this->assertSame(302, $app->get('/account')->status, 'The old password no longer signs in.');
    }

    public function testNotificationKindsCanBeMutedOrKeptOutOfEmail(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $mona = $f->colleague('mona@example.com');
        $app->loginAs('mona@example.com');
        $this->assertTrue(str_contains($app->page('/notifications'), 'Someone adds me to a card'));
        // Keep everything except "added to a card" in the app; email nothing but mentions.
        $types = array_keys(\App\Modules\Notification\Contract\Messages::LABELS);
        $app->post('/notifications/settings', ['detailed' => '1', 'email' => '1', 'shown' => array_values(array_diff($types, ['card.assigned'])), 'emailed' => ['comment.mentioned']]);

        $app->loginAs('sara@example.com');
        $card = $app->container->get(\App\Modules\Card\Application\CardService::class)->createCard($f->owner, $f->board->id, $f->todo->id, 'Plan launch');
        $sent = count($app->sentMail());
        $app->container->get(\App\Modules\Card\Application\CardService::class)->assign($f->owner, $card, $mona);
        $this->assertSame(0, (int) $app->pdo()->query("SELECT COUNT(*) FROM notifications WHERE type = 'card.assigned'")->fetchColumn());
        $this->assertCount($sent, $app->sentMail());
    }
}
