<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Contract\WorkspaceRole;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class SearchAndFilterTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        // The test clock is 2026-01-20.
        $this->f = new BoardFixture(new App(), BoardVisibility::Workspace);
        $cards = $this->f->app->container->get(CardService::class);
        $label = $this->f->app->container->get(LabelCatalog::class)->ensureLabel($this->f->owner, $this->f->board->id, 'Design', 'purple');

        $logo = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Logo ideas', ['due_date' => '2026-01-10']);
        $this->f->app->container->get(LabelCatalog::class)->attach($this->f->owner, $logo->id, $label->id);
        $cards->toggleMember($this->f->owner, $logo, $this->f->owner);
        $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Pricing page', ['description' => 'Three plans, 100% clear', 'due_date' => '2026-01-22']);
        $done = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->done->id, 'Pick fonts');
        $cards->setComplete($this->f->owner, $done, true);
    }

    public function testBoardFiltersCombine(): void
    {
        $board = '/boards/' . $this->f->board->publicId;

        $this->assertSame(['Logo ideas'], $this->titles($board . '?label=' . $this->label()));
        $this->assertSame(['Logo ideas'], $this->titles($board . '?member=me'));
        $this->assertSame(['Pricing page', 'Pick fonts'], $this->titles($board . '?member=none'));
        $this->assertSame(['Logo ideas'], $this->titles($board . '?due=overdue'));
        $this->assertSame(['Pricing page'], $this->titles($board . '?due=week'));
        $this->assertSame(['Pick fonts'], $this->titles($board . '?status=done'));
        $this->assertSame(['Pricing page'], $this->titles($board . '?q=PLANS&status=open'));
        $this->assertSame(['Logo ideas', 'Pricing page', 'Pick fonts'], $this->titles($board . '?due=bogus&member=%27'));
        $this->assertTrue(str_contains($this->f->app->page($board . '?label=' . $this->label()), 'Showing 1 of 3 cards.'));
    }

    public function testSearchFindsCardsAndBoardsTheUserCanSeeOnly(): void
    {
        $secret = $this->f->app->container->get(BoardService::class)->createBoard($this->f->owner, $this->f->workspaceId, 'Logo secrets', BoardVisibility::Private);
        $list = $this->f->app->container->get(BoardService::class)->addList($this->f->owner, $secret->id, 'Ideas');
        $this->f->app->container->get(CardService::class)->createCard($this->f->owner, $secret->id, $list->id, 'Logo for the secret launch');

        $owner = $this->f->app->page('/search?q=logo');
        $this->assertTrue(str_contains($owner, 'Logo for the secret launch'));
        $this->assertTrue(str_contains($owner, 'Logo secrets'));

        $this->f->colleague('mona@example.com', WorkspaceRole::Member);
        $member = $this->f->app->page('/search?q=logo');
        $this->assertTrue(str_contains($member, 'Logo ideas'));
        $this->assertFalse(str_contains($member, 'secret'));
    }

    public function testSearchLooksInCommentsChecklistsAttachmentsAndOptionallyTheArchive(): void
    {
        $c = $this->f->app->container;
        $cards = $c->get(CardService::class);
        $talk = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Kickoff');
        $c->get(\App\Modules\Discussion\Application\DiscussionService::class)->post($this->f->owner, $talk, 'Remember the zebra stripes on the banner');
        $steps = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch steps');
        $list = $c->get(\App\Modules\Checklist\Application\ChecklistService::class)->addChecklist($this->f->owner, $steps, 'Before launch');
        $c->get(\App\Modules\Checklist\Application\ChecklistService::class)->addItem($this->f->owner, $list, 'Order flamingo stickers');
        $files = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Brand files');
        $c->get(\App\Modules\Attachment\Application\AttachmentService::class)->addLink($this->f->owner, $files, 'https://example.com/walrus', 'Walrus moodboard');
        $old = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Old zebra poster');
        $cards->archive($this->f->owner, $old);

        $page = $this->f->app->page('/search?q=zebra');
        $this->assertTrue(str_contains($page, 'Kickoff'));
        $this->assertTrue(str_contains($page, 'In a comment'));
        $this->assertFalse(str_contains($page, 'Old zebra poster'), 'Archived cards stay out unless asked for.');
        $this->assertTrue(str_contains($this->f->app->page('/search?q=zebra&archived=1'), 'Old zebra poster'));

        $this->assertTrue(str_contains($this->f->app->page('/search?q=flamingo'), 'Launch steps'));
        $this->assertTrue(str_contains($this->f->app->page('/search?q=walrus'), 'Brand files'));

        // A board the person can not see is never searched, even when named.
        $secret = $c->get(BoardService::class)->createBoard($this->f->owner, $this->f->workspaceId, 'Private', BoardVisibility::Private);
        $secretList = $c->get(BoardService::class)->addList($this->f->owner, $secret->id, 'Ideas');
        $hidden = $cards->createCard($this->f->owner, $secret->id, $secretList->id, 'Hidden');
        $c->get(\App\Modules\Discussion\Application\DiscussionService::class)->post($this->f->owner, $hidden, 'zebra secrets');
        $this->f->colleague('mona@example.com', WorkspaceRole::Member);
        $this->assertFalse(str_contains($this->f->app->page('/search?q=zebra&board=' . $secret->publicId), 'Hidden'));
        $this->assertTrue(str_contains($this->f->app->page('/search?q=zebra'), 'Kickoff'));
    }

    public function testLikeWildcardsAreMatchedLiterally(): void
    {
        $queries = $this->f->app->container->get(CardQueries::class);

        $this->assertSame(['Pricing page'], array_map(static fn ($c) => $c->title, $queries->search([$this->f->board->id], '100%')));
        $this->assertSame([], $queries->search([$this->f->board->id], '%_'));
        $this->assertSame([], $queries->search([], 'Logo'));
    }

    /** @return list<string> card titles on the page, in board order */
    private function titles(string $path): array
    {
        preg_match_all('#class="card-title"[^>]*>([^<]+)<#', $this->f->app->page($path), $m);

        return $m[1];
    }

    private function label(): int
    {
        return $this->f->app->container->get(LabelCatalog::class)->forBoard($this->f->board->id)[0]->id;
    }
}
