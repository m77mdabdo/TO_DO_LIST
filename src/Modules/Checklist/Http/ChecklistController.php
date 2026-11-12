<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Application\ChecklistService;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

/** Every action answers JSON to fetch() and redirects back to the card otherwise. */
final class ChecklistController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private ChecklistService $checklists,
        private CardQueries $cards,
    ) {
        parent::__construct($view, $url, $session);
    }

    /**
     * Body: title.
     *
     * @param array{card: string} $params
     */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cards->findByPublicId($params['card']) ?? throw AccessDenied::hidden();

        try {
            $this->checklists->addChecklist($user->id, $card, $request->input('title'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($card));
        }

        return $this->done($request, $card);
    }

    /** @param array{id: int} $params */
    public function rename(Request $request, array $params): Response
    {
        return $this->attempt($request, fn (int $userId) => $this->checklists->renameChecklist($userId, $params['id'], $request->input('title')),
            fn (int $userId) => $this->checklists->cardOfChecklist($userId, $params['id']));
    }

    /** @param array{id: int} $params */
    public function destroy(Request $request, array $params): Response
    {
        $card = $this->checklists->deleteChecklist($this->user($request)->id, $params['id']);

        return $this->done($request, $card, 'Checklist deleted.');
    }

    /**
     * Body: text.
     *
     * @param array{id: int} $params checklist id
     */
    public function addItem(Request $request, array $params): Response
    {
        return $this->attempt($request, fn (int $userId) => $this->checklists->addItem($userId, $params['id'], $request->input('text')),
            fn (int $userId) => $this->checklists->cardOfChecklist($userId, $params['id']));
    }

    /**
     * Body: text, assignee_id, due_date.
     *
     * @param array{id: int} $params item id
     */
    public function updateItem(Request $request, array $params): Response
    {
        return $this->attempt($request, fn (int $userId) => $this->checklists->updateItem($userId, $params['id'], $request->only(['text', 'assignee_id', 'due_date'])),
            fn (int $userId) => $this->checklists->cardOfItem($userId, $params['id']));
    }

    /** @param array{id: int} $params item id */
    public function toggleItem(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $done = $this->checklists->toggleItem($user->id, $params['id']);

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'done' => $done])
            : $this->redirect($this->cardPath($this->checklists->cardOfItem($user->id, $params['id'])) . '#checklists');
    }

    /**
     * Body: checklist_id, after_id (the item it now follows; empty = first).
     *
     * @param array{id: int} $params item id
     */
    public function moveItem(Request $request, array $params): Response
    {
        $checklistId = $request->input('checklist_id');
        $afterId = $request->input('after_id');

        return $this->attempt($request, fn (int $userId) => $this->checklists->moveItem(
            $userId,
            $params['id'],
            ctype_digit($checklistId) ? (int) $checklistId : 0,
            ctype_digit($afterId) ? (int) $afterId : null,
        ), fn (int $userId) => $this->checklists->cardOfItem($userId, $params['id']));
    }

    /** @param array{id: int} $params item id */
    public function destroyItem(Request $request, array $params): Response
    {
        return $this->done($request, $this->checklists->deleteItem($this->user($request)->id, $params['id']));
    }

    /**
     * Runs a use case; on a validation error goes back to the card with the errors.
     *
     * @param callable(int): CardSummary $action
     * @param callable(int): CardSummary $cardForErrors
     */
    private function attempt(Request $request, callable $action, callable $cardForErrors): Response
    {
        $userId = $this->user($request)->id;

        try {
            $card = $action($userId);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->cardPath($cardForErrors($userId)));
        }

        return $this->done($request, $card);
    }

    private function done(Request $request, CardSummary $card, ?string $message = null): Response
    {
        return $request->wantsJson()
            ? Response::json(['ok' => true])
            : $this->redirect($this->cardPath($card) . '#checklists', $message);
    }

    private function cardPath(CardSummary $card): string
    {
        return '/cards/' . $card->publicId;
    }
}
