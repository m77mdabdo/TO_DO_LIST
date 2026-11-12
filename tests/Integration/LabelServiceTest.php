<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Card\Application\CardService;
use App\Modules\Label\Application\LabelService;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class LabelServiceTest extends TestCase
{
    public function testLabelsAreSharedPerBoardAndToggledOnCards(): void
    {
        $f = new BoardFixture(new App());
        $labels = $f->app->container->get(LabelService::class);
        $cards = $f->app->container->get(CardService::class);
        $a = $cards->createCard($f->owner, $f->board->id, $f->todo->id, 'A');
        $b = $cards->createCard($f->owner, $f->board->id, $f->todo->id, 'B');

        $urgent = $labels->ensureLabel($f->owner, $f->board->id, 'Urgent', 'red');
        $this->assertSame($urgent->id, $labels->ensureLabel($f->owner, $f->board->id, 'Urgent', 'red')->id);

        $this->assertTrue($labels->toggle($f->owner, $a->id, $urgent->id));
        $labels->attach($f->owner, $b->id, $urgent->id);
        $this->assertFalse($labels->toggle($f->owner, $b->id, $urgent->id));

        $byCard = $labels->forCards([$a->id, $b->id]);
        $this->assertSame(['Urgent'], array_map(static fn ($l) => $l->name, $byCard[$a->id]));
        $this->assertSame(false, isset($byCard[$b->id]));
    }

    public function testALabelFromAnotherBoardCannotBeAttached(): void
    {
        $f = new BoardFixture(new App());
        $other = new BoardFixture($f->app, email: 'omar@example.com');
        $labels = $f->app->container->get(LabelService::class);
        $card = $f->app->container->get(CardService::class)->createCard($f->owner, $f->board->id, $f->todo->id, 'A');
        $foreign = $labels->ensureLabel($other->owner, $other->board->id, 'Theirs', 'blue');

        $this->assertThrows(AccessDenied::class, fn () => $labels->toggle($f->owner, $card->id, $foreign->id));
        $this->assertThrows(AccessDenied::class, fn () => $labels->ensureLabel($f->owner, $other->board->id, 'Sneak', 'red'));
    }
}
