<?php

declare(strict_types=1);

namespace App\Modules\Card\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Application\BulkCardActions;
use App\Modules\Card\Application\CardService;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class CardController extends Controller
{
    private const ACTIVITY_PAGE = 20;

    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private CardService $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private LabelCatalog $labels,
        private ActivityFeed $activity,
        private UserDirectory $users,
        private BulkCardActions $bulk,
        private \App\Modules\Attachment\Contract\AttachmentFiles $files,
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * Body: list_id, title, and optionally template (a template card's public id).
     *
     * @param array{board: string} $params
     */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
        $listId = $request->input('list_id');
        $listId = ctype_digit($listId) ? (int) $listId : 0;

        try {
            if ($request->input('template') !== '') {
                // From a template card of this same board, keeping everything it has.
                [$template] = $this->cards->cardFor($user->id, $request->input('template'));
                if ($template->boardId !== $board->id || !$template->isTemplate || $template->archived) {
                    throw AccessDenied::hidden();
                }
                $title = trim($request->input('title'));
                $card = $this->cards->copyCard($user->id, $template, $board->id, $listId, $title !== '' ? $title : $template->title, CardService::COPY_PARTS);
            } else {
                $card = $this->cards->createCard($user->id, $board->id, $listId, $request->input('title'));
            }
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId);
        }

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'id' => $card->publicId])
            : $this->redirect('/boards/' . $board->publicId);
    }

    /**
     * One action on the chosen cards of a board.
     * Body: cards[] (public ids), action, value, confirm (for delete: the count).
     *
     * @param array{board: string} $params
     */
    public function bulk(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
        $this->gate->authorize($user->id, $board->id, Permission::ViewBoard);
        $ids = array_values(array_filter((array) ($request->body()['cards'] ?? []), 'is_string'));
        $action = $request->input('action');
        $value = $request->input('value_' . $action);
        $confirm = $request->input('confirm');
        $back = '/boards/' . $board->publicId . '?select=1';

        try {
            $count = $this->bulk->apply($user->id, $board, $ids, $action, $value, ctype_digit($confirm) ? (int) $confirm : 0);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back);
        }

        return $this->redirect('/boards/' . $board->publicId, $this->view->t(':count cards updated.', ['count' => $count]));
    }

    /** @param array{card: string} $params */
    public function show(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card']);
        $role = $this->gate->roleOn($user->id, $board->id);
        $before = $request->input('activity_before');
        $activity = $role->allows(Permission::ViewActivity) ? $this->activity->forCard($card->id, ctype_digit($before) ? (int) $before : null, self::ACTIVITY_PAGE) : [];
        $audience = $this->gate->peopleOn($board->id);
        $assigned = $this->cards->membersOf($card);

        return $this->render('cards/show', [
            'title'       => $card->title,
            'card'        => $card,
            'board'       => $board,
            'role'        => $role,
            'lists'       => $this->boards->openLists($board->id),
            'boardLabels' => $this->labels->forBoard($board->id),
            'cardLabels'  => $this->labels->forCards([$card->id])[$card->id] ?? [],
            'activity'    => $activity,
            'people'      => $this->users->findMany([...array_keys($audience), ...array_map(static fn ($a) => $a->actorId, $activity)]),
            'audience'    => $audience,
            'moreActivity' => count($activity) === self::ACTIVITY_PAGE,
            'query'       => ['comments_before' => $request->input('comments_before')],
            'assigned'    => $assigned,
            'copyTargets' => $this->copyTargets($user->id, $board),
            'coverImages' => array_values(array_filter($this->files->forCard($card->id), static fn ($a) => $a->isImage())),
            'linkBoards'  => array_values(array_filter(
                $this->boards->allInWorkspace($board->workspaceId),
                fn ($b) => $b->id !== $board->id && !$b->archived && $this->gate->can($user->id, $b->id, Permission::ViewBoard),
            )),
            'linkedBoard' => $card->linkBoardId !== null && $this->gate->can($user->id, $card->linkBoardId, Permission::ViewBoard) ? $this->boards->find($card->linkBoardId) : null,
        ]);
    }

    /**
     * Body: user_id. Assigns that person, or takes them off when already assigned.
     *
     * @param array{card: string} $params
     */
    public function members(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card] = $this->cards->cardFor($user->id, $params['card'], Permission::EditCards);
        $memberId = $request->input('user_id');

        try {
            $this->cards->toggleMember($user->id, $card, ctype_digit($memberId) ? (int) $memberId : 0);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/cards/' . $card->publicId);
        }

        return $request->wantsJson()
            ? Response::json(['ok' => true])
            : $this->redirect('/cards/' . $card->publicId);
    }

    /**
     * Body: on (1 makes it a template, 0 a plain card again).
     *
     * @param array{card: string} $params
     */
    /**
     * Body: cover, cover_size, sticker, kind, link_board.
     *
     * @param array{card: string} $params
     */
    public function looks(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card] = $this->cards->cardFor($user->id, $params['card'], Permission::EditCards);

        try {
            $this->cards->setLooks($user->id, $card, $request->only(['cover', 'cover_size', 'sticker', 'kind', 'link_board']));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/cards/' . $card->publicId . '#looks');
        }

        return $this->redirect('/cards/' . $card->publicId . '#looks', 'Card look saved.');
    }

    public function template(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card] = $this->cards->cardFor($user->id, $params['card'], Permission::EditCards);
        $on = $request->input('on') === '1';
        $this->cards->setTemplate($user->id, $card, $on);

        return $this->redirect('/cards/' . $card->publicId, $on ? 'The card is now a template.' : 'The card is no longer a template.');
    }

    /**
     * Copies the card, or makes a new card from a template.
     * Body: target (board public id:list id) or list_id (same board), title, keep[].
     *
     * @param array{card: string} $params
     */
    public function copy(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card']);
        $back = $this->back($request, '/cards/' . $card->publicId);
        $toBoard = $board;
        $listId = $request->input('list_id');
        if (preg_match('/^([0-9A-HJKMNP-TV-Z]{26}):(\d+)$/', $request->input('target'), $m) === 1) {
            // A board the user can't see is treated like a missing one.
            $toBoard = $this->boards->findByPublicId($m[1]);
            if ($toBoard === null || $toBoard->workspaceId !== $board->workspaceId || !$this->gate->can($user->id, $toBoard->id, Permission::ViewBoard)) {
                throw AccessDenied::hidden();
            }
            $listId = $m[2];
        }
        $keep = array_values(array_filter((array) ($request->body()['keep'] ?? []), 'is_string'));
        $title = $request->input('title');

        try {
            $copy = $this->cards->copyCard($user->id, $card, $toBoard->id, ctype_digit($listId) ? (int) $listId : 0, $title !== '' ? $title : $card->title, $keep);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $back);
        }

        return $request->input('open') === '1'
            ? $this->redirect('/cards/' . $copy->publicId, 'Card copied.')
            : $this->redirect($back, $card->isTemplate ? 'Card created from the template.' : 'Card copied.');
    }

    /** @param array{card: string} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card] = $this->cards->cardFor($user->id, $params['card'], Permission::EditCards);

        try {
            $this->cards->update($user->id, $card, $request->only(['title', 'description', 'start_date', 'due_date']));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/cards/' . $card->publicId);
        }

        return $this->redirect('/cards/' . $card->publicId, 'Card saved.');
    }

    /** @param array{card: string} $params */
    public function complete(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card'], Permission::EditCards);
        $done = $request->input('done') === '1';
        $this->cards->setComplete($user->id, $card, $done);

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'done' => $done])
            : $this->redirect($this->back($request, '/boards/' . $board->publicId));
    }

    /**
     * Body: list_id, after_id (card it now follows; empty = top of the list).
     *
     * @param array{card: string} $params
     */
    public function move(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card'], Permission::MoveCards);
        $listId = $request->input('list_id');
        $after = $this->afterId($request);

        try {
            $this->cards->move($user->id, $card, ctype_digit($listId) ? (int) $listId : 0, $after);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/cards/' . $card->publicId);
        }

        return $request->wantsJson()
            ? Response::json(['ok' => true])
            : $this->redirect($this->back($request, '/boards/' . $board->publicId));
    }

    /** @param array{card: string} $params */
    public function archive(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card'], Permission::ArchiveItems);
        $this->cards->archive($user->id, $card);

        return $this->redirect('/boards/' . $board->publicId, 'Card archived.');
    }

    /** @param array{card: string} $params */
    public function restore(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card'], Permission::ArchiveItems);

        try {
            $this->cards->archive($user->id, $card, false);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId . '/archived');
        }

        return $this->redirect('/boards/' . $board->publicId . '/archived', 'Card restored.');
    }

    /** @param array{card: string} $params */
    public function destroy(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card'], Permission::DeleteItems);

        try {
            $this->cards->delete($user->id, $card);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId . '/archived');
        }

        return $this->redirect('/boards/' . $board->publicId . '/archived', 'Card deleted for good.');
    }

    private function afterId(Request $request): ?int
    {
        $after = $request->input('after_id');

        return ctype_digit($after) ? (int) $after : null;
    }

    /** Only our own paths from the "back" field, never an outside URL. */
    private function back(Request $request, string $default): string
    {
        $back = $request->input('back');

        return preg_match('#^/(?!/)[A-Za-z0-9/_-]*$#', $back) ? $back : $default;
    }

    /**
     * Where a card of $board can be copied: open lists of the open boards of
     * the same workspace that the user may edit, this board first.
     *
     * @return list<array{board: \App\Modules\Board\Contract\BoardSummary, lists: list<\App\Modules\Board\Contract\ListSummary>}>
     */
    private function copyTargets(int $userId, \App\Modules\Board\Contract\BoardSummary $board): array
    {
        $targets = [];
        foreach ($this->boards->allInWorkspace($board->workspaceId) as $candidate) {
            if ($candidate->archived || !$this->gate->can($userId, $candidate->id, Permission::EditCards)) {
                continue;
            }
            $lists = $this->boards->openLists($candidate->id);
            if ($lists !== []) {
                $targets[$candidate->id === $board->id ? 0 : $candidate->id] = ['board' => $candidate, 'lists' => $lists];
            }
        }
        ksort($targets);

        return array_values($targets);
    }
}
