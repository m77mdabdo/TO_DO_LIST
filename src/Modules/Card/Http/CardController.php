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
use App\Modules\Card\Application\CardService;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Label\Contract\LabelCatalog;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class CardController extends Controller
{
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
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * Body: list_id, title.
     *
     * @param array{board: string} $params
     */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
        $listId = $request->input('list_id');

        try {
            $card = $this->cards->createCard($user->id, $board->id, ctype_digit($listId) ? (int) $listId : 0, $request->input('title'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId);
        }

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'id' => $card->publicId])
            : $this->redirect('/boards/' . $board->publicId);
    }

    /** @param array{card: string} $params */
    public function show(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card, $board] = $this->cards->cardFor($user->id, $params['card']);
        $role = $this->gate->roleOn($user->id, $board->id);
        $activity = $role->allows(Permission::ViewActivity) ? $this->activity->forCard($card->id) : [];

        return $this->render('cards/show', [
            'title'       => $card->title,
            'card'        => $card,
            'board'       => $board,
            'role'        => $role,
            'lists'       => $this->boards->openLists($board->id),
            'boardLabels' => $this->labels->forBoard($board->id),
            'cardLabels'  => $this->labels->forCards([$card->id])[$card->id] ?? [],
            'activity'    => $activity,
            'people'      => $this->users->findMany(array_map(static fn ($a) => $a->actorId, $activity)),
        ]);
    }

    /** @param array{card: string} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        [$card] = $this->cards->cardFor($user->id, $params['card'], Permission::EditCards);

        try {
            $this->cards->update($user->id, $card, $request->only(['title', 'description', 'due_date']));
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
}
