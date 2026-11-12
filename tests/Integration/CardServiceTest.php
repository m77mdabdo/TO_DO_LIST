<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Shared\Events\EventBus;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class CardServiceTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
    }

    public function testCardsAreAppendedToTheirList(): void
    {
        $a = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Write brief');
        $b = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Book venue', ['due_date' => '2026-02-01']);

        $byList = $this->queries()->openCardsByList($this->f->board->id);

        $this->assertSame([$a->id, $b->id], array_map(static fn ($c) => $c->id, $byList[$this->f->todo->id]));
        $this->assertSame('2026-02-01', $byList[$this->f->todo->id][1]->dueDate());
    }

    public function testMovingBetweenListsKeepsTheDroppedOrder(): void
    {
        $a = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'A');
        $b = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->doing->id, 'B');
        $c = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->doing->id, 'C');
        $moves = [];
        $this->f->app->container->get(EventBus::class)->listen(CardMoved::name(), function (CardMoved $e) use (&$moves): void { $moves[] = $e; });

        $this->cards()->move($this->f->owner, $a, $this->f->doing->id, $b->id);

        $doing = $this->queries()->openCardsByList($this->f->board->id)[$this->f->doing->id];
        $this->assertSame(['B', 'A', 'C'], array_map(static fn ($card) => $card->title, $doing));
        $this->assertSame($this->f->todo->id, $moves[0]->fromListId);
    }

    public function testCannotMoveIntoAListOfAnotherBoardOrAfterAForeignCard(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'A');
        $other = new BoardFixture($this->f->app, email: 'omar@example.com');
        $foreign = $this->cards()->createCard($other->owner, $other->board->id, $other->todo->id, 'Theirs');
        $this->f->app->loginAs('sara@example.com');

        $this->assertThrows(ValidationException::class, fn () => $this->cards()->move($this->f->owner, $card, $other->todo->id, null));
        $this->assertThrows(ValidationException::class, fn () => $this->cards()->move($this->f->owner, $card, $this->f->doing->id, $foreign->id));
    }

    public function testViewersCannotEditOrMoveCards(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'A');
        $viewer = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $viewer, BoardRole::Viewer);

        $this->assertThrows(AccessDenied::class, fn () => $this->cards()->update($viewer, $card, ['title' => 'Changed']));
        $this->assertThrows(AccessDenied::class, fn () => $this->cards()->move($viewer, $card, $this->f->done->id, null));
        $this->assertThrows(AccessDenied::class, fn () => $this->cards()->createCard($viewer, $this->f->board->id, $this->f->todo->id, 'Nope'));
        [$seen] = $this->cards()->cardFor($viewer, $card->publicId);
        $this->assertSame('A', $seen->title);
    }

    public function testStrangersGetNotFoundForAnyCardId(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Secret');
        $stranger = $this->f->app->signUp('Omar', 'omar@example.com');

        try {
            $this->cards()->cardFor($stranger, $card->publicId);
            $this->assertTrue(false, 'Expected a denial.');
        } catch (AccessDenied $e) {
            $this->assertSame(404, $e->status);
        }
    }

    public function testArchiveRestoreAndDeleteRules(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Old');
        $member = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $member, BoardRole::Member);

        $this->assertThrows(ValidationException::class, fn () => $this->cards()->delete($this->f->owner, $card));
        $this->cards()->archive($member, $card);
        $archived = $this->queries()->find($card->id);
        $this->assertTrue($archived->archived);
        $this->assertCount(1, $this->queries()->archivedCards($this->f->board->id));
        $this->assertThrows(AccessDenied::class, fn () => $this->cards()->delete($member, $archived));

        $this->cards()->delete($this->f->owner, $archived);
        $this->assertNull($this->queries()->find($card->id));
    }

    public function testCompletingAndEditingACard(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Draft');

        $this->cards()->update($this->f->owner, $card, ['title' => 'Final draft', 'description' => 'Two pages', 'due_date' => '2026-03-01']);
        $this->cards()->setComplete($this->f->owner, $this->queries()->find($card->id), true);

        $saved = $this->queries()->find($card->id);
        $this->assertSame('Final draft', $saved->title);
        $this->assertSame('Two pages', $saved->description);
        $this->assertSame('2026-03-01', $saved->dueDate());
        $this->assertTrue($saved->isComplete());
    }

    public function testStartDateMustNotBeAfterTheDueDate(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Plan');

        $this->assertThrows(ValidationException::class, fn () => $this->cards()->update($this->f->owner, $card, ['title' => 'Plan', 'start_date' => '2026-03-05', 'due_date' => '2026-03-01']));

        $this->cards()->update($this->f->owner, $card, ['title' => 'Plan', 'start_date' => '2026-02-20', 'due_date' => '2026-03-01']);
        $this->assertSame('2026-02-20', $this->queries()->find($card->id)->startDate());
    }

    public function testOnlyPeopleWhoSeeTheBoardCanBeAssigned(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Plan');
        $outsider = $this->f->colleague('omar@example.com');

        // A private board: a workspace member who isn't on it can't be assigned.
        $this->assertThrows(ValidationException::class, fn () => $this->cards()->toggleMember($this->f->owner, $card, $outsider));
        $this->assertSame([], $this->cards()->membersOf($card));

        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $outsider, BoardRole::Viewer);
        $this->f->app->container->get(\App\Modules\Access\Application\Gate::class)->forget($outsider, $this->f->board->id);

        $this->assertTrue($this->cards()->toggleMember($this->f->owner, $card, $outsider));
        $this->assertTrue($this->cards()->toggleMember($this->f->owner, $card, $this->f->owner));
        // Ordered by when they were added (same second here, so by user id).
        $this->assertSame([$this->f->owner, $outsider], $this->queries()->membersForCards([$card->id])[$card->id]);

        // The same call again takes them off.
        $this->assertFalse($this->cards()->toggleMember($this->f->owner, $card, $outsider));
        $this->assertSame([$this->f->owner], $this->cards()->membersOf($card));

        // A viewer may not assign anyone.
        $this->assertThrows(AccessDenied::class, fn () => $this->cards()->toggleMember($outsider, $card, $outsider));
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }

    private function queries(): CardQueries
    {
        return $this->f->app->container->get(CardQueries::class);
    }
}
