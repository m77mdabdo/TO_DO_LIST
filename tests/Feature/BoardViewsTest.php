<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Board\Contract\BoardStats;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Checklist\Application\ChecklistService;
use App\Support\MonthGrid;
use DateTimeImmutable;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class BoardViewsTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        // The test clock is 2026-01-20.
        $this->f = new BoardFixture(new App(), BoardVisibility::Private);
        $cards = $this->f->app->container->get(CardService::class);
        $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Banana', ['due_date' => '2026-01-25']);
        $late = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->doing->id, 'Apple', ['due_date' => '2026-01-10']);
        $cards->toggleMember($this->f->owner, $late, $this->f->owner);
        $done = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->done->id, 'Cherry');
        $cards->setComplete($this->f->owner, $done, true);
        $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Durian');
    }

    public function testTheTableSortsByAnyColumnAndKeepsFilters(): void
    {
        $base = '/boards/' . $this->f->board->publicId . '/table';
        $this->assertSame(['Banana', 'Durian', 'Apple', 'Cherry'], $this->rows($base), 'By list, then board order.');
        $this->assertSame(['Apple', 'Banana', 'Cherry', 'Durian'], $this->rows($base . '?sort=title'));
        $this->assertSame(['Durian', 'Cherry', 'Banana', 'Apple'], $this->rows($base . '?sort=title&dir=desc'));
        $this->assertSame(['Apple', 'Banana', 'Durian', 'Cherry'], $this->rows($base . '?sort=due'), 'Undated cards come last.');
        $this->assertSame(['Apple'], $this->rows($base . '?due=overdue'));
        $this->assertTrue(str_contains($this->f->app->page($base . '?due=overdue'), 'href="/boards/' . $this->f->board->publicId . '/calendar?due=overdue"'), 'The tabs keep the filters.');
    }

    public function testTheCalendarShowsCardsAndChecklistItemsOnTheirDays(): void
    {
        $card = $this->f->app->container->get(CardQueries::class)->search([$this->f->board->id], 'Durian')[0];
        $checklists = $this->f->app->container->get(ChecklistService::class);
        $list = $checklists->addChecklist($this->f->owner, $card, 'Steps');
        $checklists->addItem($this->f->owner, $list, 'Peel it');
        $item = $checklists->forCard($card->id)[0]->items[0];
        $checklists->updateItem($this->f->owner, $item->id, ['text' => 'Peel it', 'due_date' => '2026-01-28']);

        $page = $this->f->app->page('/boards/' . $this->f->board->publicId . '/calendar?month=2026-01');
        $this->assertTrue(str_contains($page, 'January 2026'));
        $this->assertSame(1, preg_match('#data-day="2026-01-25">25</span>\s*<a [^>]*>Banana#', $page));
        $this->assertSame(1, preg_match('#data-day="2026-01-28">28</span>.*?Peel it#s', $page));
        $this->assertTrue(str_contains($page, 'is-late'), 'Apple is overdue.');

        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId . '/calendar?month=2026-02'), 'February 2026'));
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId . '/calendar?month=nonsense'), 'January 2026'));
    }

    public function testTheDashboardCountsWhatMatters(): void
    {
        $page = $this->f->app->page('/boards/' . $this->f->board->publicId . '/dashboard');
        $this->assertTrue(str_contains($page, '25%'), 'One of four cards is done.');
        $this->assertTrue(str_contains($page, '?due=overdue&amp;status=open'));

        $data = $this->f->app->container->get(CardQueries::class)->openCardsByList($this->f->board->id);
        $stats = BoardStats::from([$this->f->todo, $this->f->doing, $this->f->done], $data, [], [], new DateTimeImmutable('2026-01-20 10:00:00'));
        $this->assertSame([4, 3, 1, 1, 1, 1], [$stats->total, $stats->open, $stats->done, $stats->overdue, $stats->dueSoon, $stats->noDueDate]);
    }

    public function testOtherPeopleCanNotOpenThePrivateBoardsViews(): void
    {
        $this->f->app->signUp('Eve', 'eve@example.com');
        foreach (['table', 'calendar', 'dashboard'] as $view) {
            $this->assertSame(404, $this->f->app->get('/boards/' . $this->f->board->publicId . '/' . $view)->status);
        }
    }

    public function testMonthGridsStartOnTheChosenWeekday(): void
    {
        $grid = MonthGrid::for('2026-11', new DateTimeImmutable('2026-11-14'), 6);
        $this->assertSame('2026-10-31', $grid->first());
        $this->assertSame('2026-12-04', $grid->last());
        $this->assertSame('2026-10', $grid->previous());
        $this->assertSame('2026-11-01', MonthGrid::for('2026-11', new DateTimeImmutable(), 0)->first());
    }

    /** @return list<string> card titles in table order */
    private function rows(string $path): array
    {
        preg_match_all('#<th scope="row"><a [^>]*>([^<]+)</a>#', $this->f->app->page($path), $m);

        return $m[1];
    }
}
