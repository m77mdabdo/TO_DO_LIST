<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Label\Contract\LabelCatalog;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class BoardTemplatesTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App(), BoardVisibility::Workspace);
    }

    public function testABuiltInTemplateGivesListsAndLabelsInTheUsersLanguage(): void
    {
        $response = $this->f->app->post('/boards', ['template' => 'builtin:software', 'name' => '', 'visibility' => 'private']);
        $board = $this->boards()->boardFor($this->f->owner, substr((string) $response->header('Location'), 8));

        $this->assertSame('Software sprint', $board->name);
        $this->assertSame(['Backlog', 'Ready', 'In progress', 'Review', 'Done'], array_map(static fn ($l) => $l->name, $this->boards()->listsOf($board)));
        $this->assertSame(['Blocked', 'Bug', 'Chore', 'Feature'], $this->labelNames($board->id));
        $this->assertSame(BoardRole::Manager, $this->f->app->container->get(AccessGate::class)->roleOn($this->f->owner, $board->id));

        $arabic = new BoardFixture(new App(locale: 'ar'), BoardVisibility::Private, 'mona@example.com');
        $board = $arabic->app->container->get(BoardService::class)->createFromTemplate($arabic->owner, $arabic->workspaceId, 'kanban', '');
        $this->assertSame('كانبان بسيط', $board->name);
    }

    public function testACopyTakesListsCardsLabelsAndChecklistsButNotTheConversation(): void
    {
        $c = $this->f->app->container;
        $card = $c->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->doing->id, 'Write copy', ['description' => 'Short and clear', 'due_date' => '2026-02-01']);
        $label = $c->get(LabelCatalog::class)->ensureLabel($this->f->owner, $this->f->board->id, 'Content', 'green');
        $c->get(LabelCatalog::class)->attach($this->f->owner, $card->id, $label->id);
        $list = $c->get(ChecklistService::class)->addChecklist($this->f->owner, $card, 'Steps');
        $c->get(ChecklistService::class)->addItem($this->f->owner, $list, 'Draft');
        $c->get(DiscussionService::class)->post($this->f->owner, $card, 'Not copied');
        $mona = $this->f->colleague('mona@example.com');
        $c->get(BoardMembers::class)->add($this->f->board->id, $mona, BoardRole::Viewer);
        $this->f->app->loginAs('sara@example.com');

        $response = $this->f->app->post('/boards/' . $this->f->board->publicId . '/copy', ['name' => 'Launch again', 'with_cards' => '1', 'with_members' => '1']);
        $copy = $this->boards()->boardFor($this->f->owner, substr((string) $response->header('Location'), 8));

        $this->assertSame('Launch again', $copy->name);
        $this->assertSame(['To do', 'Doing', 'Done'], array_map(static fn ($l) => $l->name, $this->boards()->listsOf($copy)));
        $cards = $c->get(CardQueries::class)->openCardsByList($copy->id);
        $copied = array_merge([], ...array_values($cards))[0];
        $this->assertSame(['Write copy', 'Short and clear', '2026-02-01'], [$copied->title, $copied->description, $copied->dueDate()]);
        $this->assertSame('Doing', $this->boards()->listsOf($copy)[array_search($copied->listId, array_map(static fn ($l) => $l->id, $this->boards()->listsOf($copy)), true)]->name);
        $this->assertSame(['Content'], array_map(static fn ($l) => $l->name, $c->get(LabelCatalog::class)->forCards([$copied->id])[$copied->id] ?? []));
        $this->assertSame(['Draft'], array_map(static fn ($i) => $i->text, $c->get(ChecklistService::class)->forCard($copied->id)[0]->items));
        $this->assertSame([], $c->get(DiscussionService::class)->comments($copied->id));
        $this->assertSame(BoardRole::Viewer, $c->get(AccessGate::class)->roleOn($mona, $copy->id));

        // The original is untouched.
        $this->assertCount(1, $c->get(DiscussionService::class)->comments($card->id));
    }

    public function testSavedTemplatesAreOfferedWhenStartingABoard(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/template', ['on' => '1']);
        $this->assertTrue($this->boards()->boardFor($this->f->owner, $this->f->board->publicId)->isTemplate);

        $home = $this->f->app->page('/');
        $this->assertTrue(str_contains($home, 'value="' . $this->f->board->publicId . '"'));

        $response = $this->f->app->post('/boards', ['template' => $this->f->board->publicId, 'name' => 'Q3 launch']);
        $board = $this->boards()->boardFor($this->f->owner, substr((string) $response->header('Location'), 8));
        $this->assertSame('Q3 launch', $board->name);
        $this->assertFalse($board->isTemplate, 'A board made from a template is a normal board.');
        $this->assertSame(['To do', 'Doing', 'Done'], array_map(static fn ($l) => $l->name, $this->boards()->listsOf($board)));

        // A viewer can not turn someone's board into a template.
        $mona = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $mona, BoardRole::Viewer);
        $this->assertSame(403, $this->f->app->post('/boards/' . $this->f->board->publicId . '/template', ['on' => '0'])->status);
    }

    private function boards(): BoardService
    {
        return $this->f->app->container->get(BoardService::class);
    }

    /** @return list<string> */
    private function labelNames(int $boardId): array
    {
        $names = array_map(static fn ($l) => $l->name, $this->f->app->container->get(LabelCatalog::class)->forBoard($boardId));
        sort($names);

        return $names;
    }
}
