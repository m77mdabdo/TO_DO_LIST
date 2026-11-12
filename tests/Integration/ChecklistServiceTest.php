<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Application\ChecklistService;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class ChecklistServiceTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
        $this->card = $this->f->app->container->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch');
    }

    public function testItemsAreKeptInOrderAndCountTowardsProgress(): void
    {
        $list = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Before launch');
        foreach (['Copy', 'Images', 'Domain'] as $text) {
            $this->checklists()->addItem($this->f->owner, $list, $text);
        }
        $items = $this->checklists()->forCard($this->card->id)[0]->items;

        $this->checklists()->toggleItem($this->f->owner, $items[1]->id);

        $checklist = $this->checklists()->forCard($this->card->id)[0];
        $this->assertSame(['Copy', 'Images', 'Domain'], array_map(static fn ($i) => $i->text, $checklist->items));
        $this->assertSame(33, $checklist->percent());
        $this->assertSame([$this->card->id => ['done' => 1, 'total' => 3]], $this->checklists()->progressForCards([$this->card->id]));
    }

    public function testItemsMoveWithinAndBetweenChecklistsOfTheSameCardOnly(): void
    {
        $first = $this->checklists()->addChecklist($this->f->owner, $this->card, 'First');
        $second = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Second');
        $this->checklists()->addItem($this->f->owner, $first, 'A');
        $this->checklists()->addItem($this->f->owner, $first, 'B');
        $this->checklists()->addItem($this->f->owner, $second, 'C');
        [$one, $two] = $this->checklists()->forCard($this->card->id);

        $this->checklists()->moveItem($this->f->owner, $one->items[0]->id, $first, $one->items[1]->id); // A after B
        $this->checklists()->moveItem($this->f->owner, $one->items[1]->id, $second, null);              // B to the top of Second

        [$one, $two] = $this->checklists()->forCard($this->card->id);
        $this->assertSame(['A'], array_map(static fn ($i) => $i->text, $one->items));
        $this->assertSame(['B', 'C'], array_map(static fn ($i) => $i->text, $two->items));

        $otherCard = $this->f->app->container->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Other');
        $foreign = $this->checklists()->addChecklist($this->f->owner, $otherCard, 'Elsewhere');
        $this->assertThrows(ValidationException::class, fn () => $this->checklists()->moveItem($this->f->owner, $one->items[0]->id, $foreign, null));
    }

    public function testItemAssigneeMustSeeTheBoard(): void
    {
        $list = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Tasks');
        $this->checklists()->addItem($this->f->owner, $list, 'Call the client');
        $item = $this->checklists()->forCard($this->card->id)[0]->items[0];
        $stranger = $this->f->colleague('omar@example.com'); // not on this private board

        $this->assertThrows(ValidationException::class, fn () => $this->checklists()->updateItem($this->f->owner, $item->id, ['text' => 'Call', 'assignee_id' => (string) $stranger]));

        $this->checklists()->updateItem($this->f->owner, $item->id, ['text' => 'Call the client today', 'assignee_id' => (string) $this->f->owner, 'due_date' => '2026-02-01']);
        $saved = $this->checklists()->forCard($this->card->id)[0]->items[0];
        $this->assertSame('Call the client today', $saved->text);
        $this->assertSame($this->f->owner, $saved->assigneeId);
        $this->assertSame('2026-02-01', $saved->dueDate());
    }

    public function testViewersAndOutsidersCannotChangeChecklists(): void
    {
        $list = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Tasks');
        $viewer = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $viewer, BoardRole::Viewer);
        $outsider = $this->f->app->signUp('Omar', 'omar@example.com');

        $this->assertThrows(AccessDenied::class, fn () => $this->checklists()->addItem($viewer, $list, 'Sneaky'));
        $this->assertThrows(AccessDenied::class, fn () => $this->checklists()->addItem($outsider, $list, 'Sneaky'));
        $this->assertThrows(AccessDenied::class, fn () => $this->checklists()->deleteChecklist($outsider, $list));
        $this->assertSame([], $this->checklists()->forCard($this->card->id)[0]->items);
    }

    public function testCompletingAnItemIsRecordedInTheActivity(): void
    {
        $list = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Tasks');
        $this->checklists()->addItem($this->f->owner, $list, 'Print flyers');
        $this->checklists()->toggleItem($this->f->owner, $this->checklists()->forCard($this->card->id)[0]->items[0]->id);

        $types = array_map(static fn ($a) => $a->type, $this->f->app->container->get(ActivityFeed::class)->forCard($this->card->id));

        $this->assertSame(['checklist.item_completed', 'checklist.added', 'card.created'], $types);
    }

    public function testDeletingAChecklistRemovesItsItems(): void
    {
        $list = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Tasks');
        $this->checklists()->addItem($this->f->owner, $list, 'One');

        $this->checklists()->deleteChecklist($this->f->owner, $list);

        $this->assertSame([], $this->checklists()->forCard($this->card->id));
        $this->assertSame([], $this->checklists()->progressForCards([$this->card->id]));
    }

    private function checklists(): ChecklistService
    {
        return $this->f->app->container->get(ChecklistService::class);
    }
}
