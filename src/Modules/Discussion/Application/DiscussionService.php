<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Application;

use App\Database\Transactions;
use App\Http\UploadedFile;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Attachment\Contract\AttachmentFiles;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Discussion\Contract\Comment;
use App\Modules\Discussion\Contract\Events\CommentChanged;
use App\Modules\Discussion\Contract\Reactions;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Discussion\Contract\Events\VoteChanged;
use App\Modules\Discussion\Domain\DiscussionRepository;
use App\Modules\Discussion\Domain\Mentions;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

/**
 * Comments and votes on cards.
 *
 * Two checks apply: the role must allow it (Comment / Vote), and the
 * board's setting must admit the person ("members" leaves viewers out,
 * "off" turns it off). Authors edit their own comments; deleting someone
 * else's needs ModerateComments (board managers).
 *
 * Besides @Name, a comment may say @card (everyone on the card) or @board
 * (everyone who can see the board). Only people who can see the board are
 * ever notified; mentioned workspace members who can't are reported back.
 */
final class DiscussionService implements \App\Modules\Discussion\Contract\CommentWriter
{
    public const BODY_MAX = 5000;
    public const PAGE = 20;

    /** Seconds a "is writing" mark stays visible after the last keystroke. */
    public const TYPING_SECONDS = 8;

    public function __construct(
        private DiscussionRepository $discussion,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private UserDirectory $users,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
        private CardQueries $cards,
        private AttachmentFiles $files,
        private WorkspaceDirectory $workspaces,
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

    /** May the user send a file with a comment on this card? */
    public function canAttach(int $userId, CardSummary $card): bool
    {
        return $this->files->canAdd($userId, $card);
    }

    /**
     * Posts a comment, with an optional file that also becomes one of the
     * card's attachments. With a file the text may be empty.
     */
    public function post(int $actorId, CardSummary $card, string $body, ?UploadedFile $file = null): int
    {
        $board = $this->boardOf($actorId, $card->boardId);
        if (!$this->canComment($actorId, $board) || $card->archived) {
            throw AccessDenied::forbidden();
        }

        $hasFile = $file !== null && $file->error !== UPLOAD_ERR_NO_FILE;
        if ($hasFile && !$this->files->canAdd($actorId, $card)) {
            throw new ValidationException(new ValidationResult(['file' => 'You can comment here, but not attach files. Ask a board member to attach it.']));
        }
        $body = $hasFile && trim($body) === '' ? '' : $this->validBody($body);
        $named = $this->mentionsIn($body, $board->id, $actorId);
        $mentions = array_values(array_unique([...$named, ...$this->groupMentions($body, $card, $actorId)]));

        return $this->transactions->run(function () use ($actorId, $card, $body, $named, $mentions, $file, $hasFile): int {
            $attachment = $hasFile ? $this->files->upload($actorId, $card, $file) : null;
            $id = $this->discussion->addComment($card->id, $card->boardId, $actorId, $body, $named, $attachment?->id);
            $this->discussion->stopTyping($card->id, $actorId);
            $excerpt = $body !== '' ? mb_substr($body, 0, 140) : '📎 ' . $attachment?->name;
            $this->events->publish(new CommentPosted($id, $card->id, $card->boardId, $actorId, $card->title, $excerpt, $mentions, $this->clock->now()));

            return $id;
        });
    }

    /**
     * Workspace members the text @mentions who can't see the board, so no
     * notification reached them, and why.
     *
     * @return array{names: list<string>, reason: string}|null null when everyone mentioned was reachable
     */
    public function unreachedMentions(int $boardId, string $body, int $authorId): ?array
    {
        $board = $this->boards->find($boardId);
        if ($board === null || !str_contains($body, '@')) {
            return null;
        }

        $onBoard = $this->gate->peopleOn($boardId);
        $outside = [];
        foreach ($this->workspaces->members($board->workspaceId) as ['userId' => $userId]) {
            if ($userId !== $authorId && !isset($onBoard[$userId])) {
                $outside[] = $userId;
            }
        }
        if ($outside === []) {
            return null;
        }

        // Matched among everyone, so "@Mona" still means the Mona on the board when there is one.
        $everyone = [];
        foreach ($this->users->findMany([...array_keys($onBoard), ...$outside]) as $user) {
            $everyone[$user->id] = $user->name;
        }
        $names = [];
        foreach (Mentions::find($body, $everyone) as $userId) {
            if (in_array($userId, $outside, true)) {
                $names[] = $everyone[$userId];
            }
        }

        return $names === [] ? null : [
            'names'  => $names,
            'reason' => $board->visibility === BoardVisibility::Private
                ? 'This board is private and they are not members of it. Add them to the board, then mention them again.'
                : 'They are guests of the workspace and are not on this board. Add them to the board, then mention them again.',
        ];
    }

    /** Only the author may edit, and only while they may still comment. */
    public function edit(int $actorId, int $commentId, string $body): Comment
    {
        $comment = $this->visibleComment($actorId, $commentId);
        $board = $this->boardOf($actorId, $comment->boardId);

        if ($comment->authorId !== $actorId || !$this->canComment($actorId, $board)) {
            throw AccessDenied::forbidden();
        }

        $body = $comment->attachmentId !== null && trim($body) === '' ? '' : $this->validBody($body);
        $this->transactions->run(function () use ($comment, $body, $board, $actorId): void {
            $this->discussion->editComment($comment->id, $body, $this->mentionsIn($body, $board->id, $actorId));
            $this->events->publish(new CommentChanged($comment->id, $comment->cardId, $comment->boardId, $actorId, 'edited', null, $this->clock->now()));
        });

        return $comment;
    }

    public function delete(int $actorId, int $commentId): Comment
    {
        $comment = $this->visibleComment($actorId, $commentId);

        if (!$this->canDelete($actorId, $comment) || ($this->boards->find($comment->boardId)?->archived ?? true)) {
            throw AccessDenied::forbidden();
        }

        $this->transactions->run(function () use ($comment, $actorId): void {
            $this->discussion->deleteComment($comment->id);
            $this->events->publish(new CommentChanged($comment->id, $comment->cardId, $comment->boardId, $actorId, 'deleted', null, $this->clock->now()));
        });

        return $comment;
    }

    /**
     * Adds the reaction, or takes it back when the user already gave it.
     * Anyone who may comment on the card may react. Returns the comment as it
     * is now, and whether the reaction is on.
     *
     * @return array{0: Comment, 1: bool}
     */
    public function react(int $actorId, int $commentId, string $emoji): array
    {
        $comment = $this->visibleComment($actorId, $commentId);
        if (!Reactions::exists($emoji)) {
            throw new ValidationException(new ValidationResult(['emoji' => 'Choose one of the reactions shown.']));
        }
        $board = $this->boardOf($actorId, $comment->boardId);
        $card = $this->cards->find($comment->cardId);
        if (!$this->canComment($actorId, $board) || $card === null || $card->archived) {
            throw AccessDenied::forbidden();
        }

        $on = $this->transactions->run(function () use ($comment, $actorId, $emoji): bool {
            $on = $this->discussion->toggleReaction($comment->id, $actorId, $emoji);
            $this->events->publish(new CommentChanged($comment->id, $comment->cardId, $comment->boardId, $actorId, $on ? 'reacted' : 'unreacted', $emoji, $this->clock->now()));

            return $on;
        });

        return [$this->discussion->findComment($comment->id) ?? $comment, $on];
    }

    /** Shows the user as writing a comment on the card for a few seconds. */
    public function typing(int $actorId, CardSummary $card): void
    {
        $board = $this->boardOf($actorId, $card->boardId);
        if (!$this->canComment($actorId, $board) || $card->archived) {
            throw AccessDenied::forbidden();
        }

        $this->discussion->touchTyping($card->id, $actorId, $this->clock->now()->modify('-1 minute')->format('Y-m-d H:i:s'));
    }

    /** @return list<int> the other people writing a comment on the card right now */
    public function typingOn(int $viewerId, CardSummary $card): array
    {
        $this->gate->authorize($viewerId, $card->boardId, Permission::ViewBoard);
        $since = $this->clock->now()->modify('-' . self::TYPING_SECONDS . ' seconds')->format('Y-m-d H:i:s');

        return array_values(array_filter(
            $this->discussion->typingOn($card->id, $since),
            fn (int $userId) => $userId !== $viewerId && $this->gate->can($userId, $card->boardId, Permission::ViewBoard),
        ));
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

    /**
     * @card: the people on the card. @board: everyone who can see the board.
     *
     * @return list<int>
     */
    private function groupMentions(string $body, CardSummary $card, int $authorId): array
    {
        if (!str_contains($body, '@')) {
            return [];
        }

        $ids = [];
        if (preg_match('/@board(?![\p{L}\p{N}_])/iu', $body)) {
            $ids = array_keys($this->gate->peopleOn($card->boardId));
        } elseif (preg_match('/@card(?![\p{L}\p{N}_])/iu', $body)) {
            $ids = $this->cards->membersForCards([$card->id])[$card->id] ?? [];
        }

        return array_values(array_filter($ids, static fn (int $id) => $id !== $authorId));
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
