<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Discussion\Contract\Comment;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Discussion\Contract\Events\VoteChanged;
use App\Modules\Discussion\Domain\DiscussionRepository;
use App\Modules\Discussion\Domain\Mentions;
use App\Modules\Identity\Contract\UserDirectory;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\Validator;

/**
 * Comments and votes on cards.
 *
 * Two checks apply: the role must allow it (Comment / Vote), and the
 * board's setting must admit the person ("members" leaves viewers out,
 * "off" turns it off). Authors edit their own comments; deleting someone
 * else's needs ModerateComments (board managers).
 */
final class DiscussionService
{
    public const BODY_MAX = 5000;
    public const PAGE = 20;

    public function __construct(
        private DiscussionRepository $discussion,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private UserDirectory $users,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
    ) {
    }

    /** @return list<Comment> newest first */
    public function comments(int $cardId, ?int $beforeId = null): array
    {
        return $this->discussion->commentsOn($cardId, $beforeId, self::PAGE);
    }

    /** @return list<int> */
    public function voters(int $cardId): array
    {
        return $this->discussion->votersOf($cardId);
    }

    /**
     * @param list<int> $cardIds
     * @return array<int, array{comments: int, votes: int}>
     */
    public function countsForCards(array $cardIds): array
    {
        return $this->discussion->countsForCards($cardIds);
    }

    public function canComment(int $userId, BoardSummary $board): bool
    {
        return !$board->archived
            && $this->gate->can($userId, $board->id, Permission::Comment)
            && $board->commenting->admits($this->gate->can($userId, $board->id, Permission::EditCards));
    }

    public function canVote(int $userId, BoardSummary $board): bool
    {
        return !$board->archived
            && $this->gate->can($userId, $board->id, Permission::Vote)
            && $board->voting->admits($this->gate->can($userId, $board->id, Permission::EditCards));
    }

    public function canDelete(int $userId, Comment $comment): bool
    {
        return $comment->authorId === $userId || $this->gate->can($userId, $comment->boardId, Permission::ModerateComments);
    }

    public function post(int $actorId, CardSummary $card, string $body): int
    {
        $board = $this->boardOf($actorId, $card->boardId);
        if (!$this->canComment($actorId, $board) || $card->archived) {
            throw AccessDenied::forbidden();
        }

        $body = $this->validBody($body);
        $mentions = $this->mentionsIn($body, $board->id, $actorId);

        return $this->transactions->run(function () use ($actorId, $card, $body, $mentions): int {
            $id = $this->discussion->addComment($card->id, $card->boardId, $actorId, $body, $mentions);
            $this->events->publish(new CommentPosted($id, $card->id, $card->boardId, $actorId, $card->title, mb_substr($body, 0, 140), $mentions, $this->clock->now()));

            return $id;
        });
    }

    /** Only the author may edit, and only while they may still comment. */
    public function edit(int $actorId, int $commentId, string $body): Comment
    {
        $comment = $this->visibleComment($actorId, $commentId);
        $board = $this->boardOf($actorId, $comment->boardId);

        if ($comment->authorId !== $actorId || !$this->canComment($actorId, $board)) {
            throw AccessDenied::forbidden();
        }

        $body = $this->validBody($body);
        $this->discussion->editComment($comment->id, $body, $this->mentionsIn($body, $board->id, $actorId));

        return $comment;
    }

    public function delete(int $actorId, int $commentId): Comment
    {
        $comment = $this->visibleComment($actorId, $commentId);

        if (!$this->canDelete($actorId, $comment) || ($this->boards->find($comment->boardId)?->archived ?? true)) {
            throw AccessDenied::forbidden();
        }

        $this->discussion->deleteComment($comment->id);

        return $comment;
    }

    /** Votes for the card, or takes the vote back. Returns whether the user now votes for it. */
    public function toggleVote(int $actorId, CardSummary $card): bool
    {
        $board = $this->boardOf($actorId, $card->boardId);
        if (!$this->canVote($actorId, $board) || $card->archived) {
            throw AccessDenied::forbidden();
        }

        return $this->transactions->run(function () use ($actorId, $card): bool {
            $added = !$this->discussion->removeVote($card->id, $actorId);
            if ($added) {
                $this->discussion->addVote($card->id, $actorId);
            }
            $this->events->publish(new VoteChanged($card->id, $card->boardId, $actorId, $added, $card->title, $this->clock->now()));

            return $added;
        });
    }

    /** The comment, if the user can see its board; a 404 otherwise. */
    public function visibleComment(int $userId, int $commentId): Comment
    {
        $comment = $this->discussion->findComment($commentId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $comment->boardId, Permission::ViewBoard);

        return $comment;
    }

    private function boardOf(int $userId, int $boardId): BoardSummary
    {
        $this->gate->authorize($userId, $boardId, Permission::ViewBoard);

        return $this->boards->find($boardId) ?? throw AccessDenied::hidden();
    }

    /** @return list<int> people on the board mentioned in $body, never the author */
    private function mentionsIn(string $body, int $boardId, int $authorId): array
    {
        $people = [];
        foreach ($this->users->findMany(array_keys($this->gate->peopleOn($boardId))) as $user) {
            $people[$user->id] = $user->name;
        }

        return array_values(array_filter(Mentions::find($body, $people), static fn (int $id) => $id !== $authorId));
    }

    private function validBody(string $body): string
    {
        $result = $this->validator->validate(['body' => $body], ['body' => ['Comment', 'required|max:' . self::BODY_MAX]]);
        if ($result->fails()) {
            throw new ValidationException($result);
        }

        return trim($body);
    }
}
