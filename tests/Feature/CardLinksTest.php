<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class CardLinksTest extends TestCase
{
    public function testCardsLinkBothWaysAndHideWhatAViewerCannotOpen(): void
    {
        $f = new BoardFixture(new App(), BoardVisibility::Private);
        $cards = $f->app->container->get(CardService::class);
        $boards = $f->app->container->get(BoardService::class);
        $a = $cards->createCard($f->owner, $f->board->id, $f->todo->id, 'Design');
        $secretBoard = $boards->createBoard($f->owner, $f->workspaceId, 'Secret plans', BoardVisibility::Private);
        $secretList = $boards->addList($f->owner, $secretBoard->id, 'Ideas');
        $b = $cards->createCard($f->owner, $secretBoard->id, $secretList->id, 'Hidden budget');

        $f->app->post('/cards/' . $a->publicId . '/connections', ['target' => 'https://todo.test/cards/' . $b->publicId . '#x']);
        $page = $f->app->page('/cards/' . $a->publicId);
        $this->assertTrue(str_contains($page, 'Hidden budget'));
        $this->assertTrue(str_contains($page, 'Secret plans'));
        $this->assertTrue(str_contains($f->app->page('/cards/' . $b->publicId), 'Design'), 'The link shows on both cards.');

        // Twice, to itself, garbage: refused with a message.
        $f->app->post('/cards/' . $a->publicId . '/connections', ['target' => $b->publicId]);
        $f->app->post('/cards/' . $a->publicId . '/connections', ['target' => $a->publicId]);
        $f->app->post('/cards/' . $a->publicId . '/connections', ['target' => 'https://example.com/']);
        $this->assertSame(1, (int) $f->app->pdo()->query('SELECT COUNT(*) FROM card_links')->fetchColumn());

        // Mona can open the first board only: she sees that a link exists, not what it is.
        $mona = $f->colleague('mona@example.com');
        $f->app->container->get(BoardMembers::class)->add($f->board->id, $mona, BoardRole::Member);
        $f->app->container->get(AccessGate::class)->forget($mona, $f->board->id);
        $cards->update($f->owner, $cards->cardFor($f->owner, $a->publicId)[0], ['title' => 'Design', 'description' => 'See https://todo.test/cards/' . $b->publicId . ' and https://todo.test/cards/' . $a->publicId]);
        $f->app->loginAs('mona@example.com');
        $page = $f->app->page('/cards/' . $a->publicId);
        $this->assertFalse(str_contains($page, 'Hidden budget'));
        $this->assertFalse(str_contains($page, 'Secret plans'));
        $this->assertTrue(str_contains($page, 'A card you cannot open.'));
        $this->assertTrue(str_contains($page, 'class="card-chip"'), 'Her own card shows as a chip.');

        // She can't link to the secret card either: to her it doesn't exist.
        $f->app->post('/cards/' . $a->publicId . '/connections', ['target' => $b->publicId]);
        $this->assertSame(1, (int) $f->app->pdo()->query('SELECT COUNT(*) FROM card_links')->fetchColumn());
        $this->assertSame(404, $f->app->get('/cards/' . $b->publicId)->status);

        // Someone who can edit the card removes the link.
        $f->app->loginAs('sara@example.com');
        $id = (int) $f->app->pdo()->query('SELECT id FROM card_links')->fetchColumn();
        $f->app->post('/cards/' . $a->publicId . '/connections/' . $id . '/delete');
        $this->assertSame(0, (int) $f->app->pdo()->query('SELECT COUNT(*) FROM card_links')->fetchColumn());
    }
}
