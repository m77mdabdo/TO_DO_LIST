<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Card\Application\CardService;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class ActivityTest extends TestCase
{
    public function testBoardAndCardEventsLandInTheLog(): void
    {
        $f = new BoardFixture(new App());
        $cards = $f->app->container->get(CardService::class);
        $card = $cards->createCard($f->owner, $f->board->id, $f->todo->id, 'Design');
        $cards->move($f->owner, $card, $f->doing->id, null);

        $feed = $f->app->container->get(ActivityFeed::class);
        $types = array_map(static fn ($e) => $e->type, $feed->forBoard($f->board->id));

        $this->assertSame(['card.moved', 'card.created', 'list.created', 'list.created', 'list.created', 'board.created'], $types);
        [$moved] = $feed->forCard($card->id);
        $this->assertSame(['from' => 'To do', 'to' => 'Doing'], $moved->data);
    }

    public function testPagesWithAKeysetCursor(): void
    {
        $f = new BoardFixture(new App());
        $feed = $f->app->container->get(ActivityFeed::class);

        $first = $feed->forBoard($f->board->id, null, 2);
        $second = $feed->forBoard($f->board->id, end($first)->id, 2);
        $third = $feed->forBoard($f->board->id, end($second)->id, 2);

        $this->assertSame(['list.created', 'list.created'], array_map(static fn ($e) => $e->type, $first));
        $this->assertSame(['list.created', 'board.created'], array_map(static fn ($e) => $e->type, $second));
        $this->assertSame([], $third);
    }
}
