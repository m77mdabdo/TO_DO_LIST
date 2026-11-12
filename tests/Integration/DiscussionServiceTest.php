<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Shared\Events\EventBus;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class DiscussionServiceTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;
    private int $member;
    private int $viewer;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
        $this->card = $this->f->app->container->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch');

        $this->member = $this->f->colleague('mona@example.com');
        $this->viewer = $this->f->colleague('omar@example.com');
        $members = $this->f->app->container->get(BoardMembers::class);
        $members->add($this->f->board->id, $this->member, BoardRole::Member);
        $members->add($this->f->board->id, $this->viewer, BoardRole::Viewer);
    }

    public function testCommentsArePagedNewestFirst(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->discussion()->post($this->f->owner, $this->card, "Note $i");
        }

        $first = $this->discussion()->comments($this->card->id);
        $second = $this->discussion()->comments($this->card->id, end($first)->id);

        $this->assertCount(20, $first);
        $this->assertSame('Note 25', $first[0]->body);
        $this->assertSame(['Note 5', 'Note 4', 'Note 3', 'Note 2', 'Note 1'], array_map(static fn ($c) => $c->body, $second));
    }

    public function testMentionsAreFoundAndAnnounced(): void
    {
        $posted = [];
        $this->f->app->container->get(EventBus::class)->listen(CommentPosted::name(), function (CommentPosted $e) use (&$posted): void { $posted[] = $e; });

        $this->discussion()->post($this->f->owner, $this->card, 'Can @mona check this? cc @Sara (that is me) and @nobody');

        $this->assertSame([$this->member], $posted[0]->mentionedUserIds);
        $this->assertSame([$this->member], $this->discussion()->comments($this->card->id)[0]->mentions);
    }

    public function testBoardSettingDecidesWhetherViewersMayCommentAndVote(): void
    {
        // Defaults: everyone comments, only members vote.
        $this->discussion()->post($this->viewer, $this->card, 'Looks good');
        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->toggleVote($this->viewer, $this->card));
        $this->assertTrue($this->discussion()->toggleVote($this->member, $this->card));

        $this->settings(commenting: 'members', voting: 'everyone');

        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->post($this->viewer, $this->card, 'Again'));
        $this->assertTrue($this->discussion()->toggleVote($this->viewer, $this->card));

        $this->settings(commenting: 'off', voting: 'off');
        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->post($this->f->owner, $this->card, 'Nope'));
        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->toggleVote($this->f->owner, $this->card));
    }

    public function testOneVotePerPersonAndItCanBeTakenBack(): void
    {
        $this->assertTrue($this->discussion()->toggleVote($this->member, $this->card));
        $this->assertTrue($this->discussion()->toggleVote($this->f->owner, $this->card));
        $this->assertFalse($this->discussion()->toggleVote($this->member, $this->card));

        $this->assertSame([$this->f->owner], $this->discussion()->voters($this->card->id));
        $this->assertSame([$this->card->id => ['comments' => 0, 'votes' => 1]], $this->discussion()->countsForCards([$this->card->id]));
    }

    public function testOnlyTheAuthorEditsAndManagersMayDeleteOthersComments(): void
    {
        $id = $this->discussion()->post($this->member, $this->card, 'First take');

        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->edit($this->f->owner, $id, 'Changed'));
        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->delete($this->viewer, $id));

        $this->discussion()->edit($this->member, $id, 'Second take');
        $this->assertSame('Second take', $this->discussion()->comments($this->card->id)[0]->body);
        $this->assertTrue($this->discussion()->comments($this->card->id)[0]->editedAt !== null);

        $this->discussion()->delete($this->f->owner, $id); // the board manager moderates
        $this->assertSame([], $this->discussion()->comments($this->card->id));
    }

    public function testOutsidersGetNotFoundAndEmptyCommentsAreRefused(): void
    {
        $id = $this->discussion()->post($this->f->owner, $this->card, 'Private note');
        $stranger = $this->f->app->signUp('Laila', 'laila@example.com');

        $this->assertThrows(AccessDenied::class, fn () => $this->discussion()->visibleComment($stranger, $id));
        $this->assertSame(404, $this->catchStatus(fn () => $this->discussion()->post($stranger, $this->card, 'Hi')));
        $this->assertThrows(ValidationException::class, fn () => $this->discussion()->post($this->f->owner, $this->card, '   '));
    }

    private function settings(string $commenting, string $voting): void
    {
        $board = $this->f->app->container->get(BoardDirectory::class)->find($this->f->board->id);
        $this->f->app->container->get(BoardService::class)->updateBoard($this->f->owner, $board, [
            'name' => $board->name, 'color' => $board->color, 'visibility' => $board->visibility->value,
            'commenting' => $commenting, 'voting' => $voting,
        ]);
    }

    private function catchStatus(callable $action): int
    {
        try {
            $action();
        } catch (AccessDenied $e) {
            return $e->status;
        }

        return 0;
    }

    private function discussion(): DiscussionService
    {
        return $this->f->app->container->get(DiscussionService::class);
    }
}
