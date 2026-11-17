<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Card\Application\CardService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class PublicBoardLinkTest extends TestCase
{
    public function testALinkWorksOnlyWhileTheRulesAllowItAndShowsNoPeopleOrComments(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $card = $app->container->get(CardService::class)->createCard($f->owner, $f->board->id, $f->todo->id, 'Write the press release');
        $app->post('/cards/' . $card->publicId . '/comments', ['body' => 'Secret comment']);
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $share = '/boards/' . $f->board->publicId . '/share';

        // Off by default in the workspace rules.
        $this->assertTrue(str_contains($app->page($share), 'don&#039;t allow public links'));
        $app->post($share);
        $this->assertSame(0, (int) $app->pdo()->query('SELECT COUNT(*) FROM board_public_links')->fetchColumn());

        $app->post('/workspaces/' . $ws->publicId . '/rules', ['public_links' => '1', 'invite_approval' => '1']);
        $app->post($share);
        preg_match('#https://todo\.test(/shared/[A-Za-z0-9_-]{43})#', $app->page($share), $m);
        $path = $m[1];
        $this->assertFalse(str_contains($app->page($share), $path), 'The link is shown only once.');

        $app->post('/logout');
        $response = $app->get($path);
        $this->assertSame(200, $response->status);
        $this->assertSame('noindex, nofollow', $response->header('X-Robots-Tag'));
        $this->assertTrue(str_contains($response->body, 'Write the press release'));
        $this->assertFalse(str_contains($response->body, 'Secret comment'));
        $this->assertFalse(str_contains($response->body, 'sara@example.com'));
        $this->assertFalse(str_contains($response->body, '/cards/'), 'No way into the card pages.');
        $this->assertSame(404, $app->get('/shared/' . str_repeat('a', 43))->status);

        // The owners turn public links off: every link stops at once.
        $app->loginAs('sara@example.com');
        $app->post('/workspaces/' . $ws->publicId . '/rules', ['invite_approval' => '1']);
        $this->assertSame(404, $app->get($path)->status);

        // Back on, a new link replaces the old one, and turning it off ends it.
        $app->post('/workspaces/' . $ws->publicId . '/rules', ['public_links' => '1', 'invite_approval' => '1']);
        $this->assertSame(200, $app->get($path)->status);
        $app->post($share);
        $this->assertSame(404, $app->get($path)->status);
        $app->post($share . '/delete');
        $this->assertSame(0, (int) $app->pdo()->query('SELECT COUNT(*) FROM board_public_links')->fetchColumn());

        $actions = $app->pdo()->query("SELECT action FROM audit_log WHERE action LIKE 'board.public_link%'")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertTrue(in_array('board.public_link_created', $actions, true) && in_array('board.public_link_revoked', $actions, true));
    }

    public function testOnlyBoardManagersShare(): void
    {
        $f = new BoardFixture(new App());
        $f->colleague('mona@example.com');
        $f->app->loginAs('mona@example.com');

        $this->assertSame(404, $f->app->get('/boards/' . $f->board->publicId . '/share')->status);
        $this->assertFalse(in_array($f->app->post('/boards/' . $f->board->publicId . '/share')->status, [200, 302], true));
    }
}
