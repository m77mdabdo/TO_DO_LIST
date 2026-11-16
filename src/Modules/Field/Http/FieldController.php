<?php

declare(strict_types=1);

namespace App\Modules\Field\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Field\Application\FieldService;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class FieldController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private FieldService $fields,
        private BoardDirectory $boards,
        private CardQueries $cards,
        private AccessGate $gate,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** The board's fields, with forms for the people who may change them. @param array{board: string} $params */
    public function index(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $fields = $this->fields->forBoard($board->id);
        $usage = [];
        foreach ($fields as $field) {
            $usage[$field->id] = $this->fields->valueCount($field->id);
        }

        return $this->render('fields/index', [
            'title'     => 'Custom fields',
            'board'     => $board,
            'role'      => $this->gate->roleOn($user->id, $board->id),
            'fields'    => $fields,
            'usage'     => $usage,
            'canManage' => $this->fields->canManage($user->id, $board),
            'editing'   => $this->session->pullFlash('field.editing'),
        ]);
    }

    /** Body: name, type, options, on_card. @param array{board: string} $params */
    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);

        try {
            $this->fields->create($user->id, $board, $request->only(['name', 'type', 'options', 'on_card']));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->path($board) . '#new-field');
        }

        return $this->redirect($this->path($board), 'Field added.');
    }

    /** Body: name, options, on_card, confirm. @param array{board: string, id: int} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);

        try {
            $this->fields->update($user->id, $board, (int) $params['id'], $request->only(['name', 'options', 'on_card', 'confirm']));
        } catch (ValidationException $e) {
            $this->session->flash('field.editing', (int) $params['id']);

            return $this->backWithErrors($e, $request, $this->path($board) . '#field-' . $params['id']);
        }

        return $this->redirect($this->path($board) . '#field-' . $params['id'], 'Field saved.');
    }

    /** Body: up (1 to move up, else down). @param array{board: string, id: int} $params */
    public function move(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $this->fields->move($user->id, $board, (int) $params['id'], $request->input('up') === '1');

        return $this->redirect($this->path($board) . '#field-' . $params['id']);
    }

    /** @param array{board: string, id: int} $params */
    public function destroy(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->board($user->id, $params['board']);
        $this->fields->delete($user->id, $board, (int) $params['id']);

        return $this->redirect($this->path($board), 'Field deleted.');
    }

    /** Body: field[<id>] for each field (an unticked box sends ""). @param array{card: string} $params */
    public function fill(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $card = $this->cards->findByPublicId($params['card']) ?? throw AccessDenied::hidden();
        $board = $this->board($user->id, '', $card->boardId);
        $input = $request->body()['field'] ?? [];
        $input = is_array($input) ? $input : [];

        try {
            $this->fields->fill($user->id, $board, $card, $input);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/cards/' . $card->publicId . '#fields');
        }

        return $request->wantsJson()
            ? Response::json(['ok' => true])
            : $this->redirect('/cards/' . $card->publicId . '#fields', 'Fields saved.');
    }

    private function board(int $userId, string $publicId, ?int $id = null): BoardSummary
    {
        $board = ($id !== null ? $this->boards->find($id) : $this->boards->findByPublicId($publicId)) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $board->id, Permission::ViewBoard);

        return $board;
    }

    private function path(BoardSummary $board): string
    {
        return '/boards/' . $board->publicId . '/fields';
    }
}
