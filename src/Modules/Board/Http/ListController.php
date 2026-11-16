<?php

declare(strict_types=1);

namespace App\Modules\Board\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Board\Application\BoardService;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class ListController extends Controller
{
    public function __construct(View $view, Url $url, Session $session, private BoardService $boards, private \App\Modules\Board\Domain\BoardPreferences $preferences)
    {
        parent::__construct($view, $url, $session);
    }

    /** @param array{board: string} $params */
    /**
     * Folds a list away (collapsed=1) or opens it again, for this person only.
     *
     * @param array{board: string, id: string} $params
     */
    public function collapse(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);
        $list = $this->boards->listsOf($board);
        $listId = (int) $params['id'];
        if (!in_array($listId, array_map(static fn ($l) => $l->id, $list), true)) {
            throw \App\Modules\Access\Contract\AccessDenied::hidden();
        }
        $collapsed = array_values(array_diff($this->preferences->collapsedLists($user->id, $board->id), [$listId]));
        if ($request->input('collapsed') === '1') {
            $collapsed[] = $listId;
        }
        $this->preferences->saveCollapsedLists($user->id, $board->id, $collapsed);

        return $request->wantsJson()
            ? Response::json(['ok' => true])
            : $this->redirect('/boards/' . $board->publicId . '#list-' . $listId);
    }

    public function store(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);

        try {
            $list = $this->boards->addList($user->id, $board->id, $request->input('name'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId);
        }

        return $request->wantsJson()
            ? Response::json(['ok' => true, 'id' => $list->id, 'name' => $list->name])
            : $this->redirect('/boards/' . $board->publicId);
    }

    /** @param array{board: string, id: int} $params */
    public function update(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);

        try {
            if ($request->input('name') !== '' || !array_key_exists('color', $request->body())) {
                $this->boards->renameList($user->id, $board, $params['id'], $request->input('name'));
            }
            if (array_key_exists('color', $request->body())) {
                $color = $request->input('color');
                $this->boards->setListColor($user->id, $board, $params['id'], $color === '' ? null : $color);
            }
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId);
        }

        return $request->wantsJson() ? Response::json(['ok' => true]) : $this->redirect('/boards/' . $board->publicId);
    }

    /**
     * Body: after_id (the list it now follows; empty = first).
     *
     * @param array{board: string, id: int} $params
     */
    public function move(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);
        $after = $request->input('after_id');

        try {
            $this->boards->moveList($user->id, $board, $params['id'], ctype_digit($after) ? (int) $after : null);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/boards/' . $board->publicId);
        }

        return $request->wantsJson() ? Response::json(['ok' => true]) : $this->redirect('/boards/' . $board->publicId);
    }

    /** @param array{board: string, id: int} $params */
    public function archive(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);
        $this->boards->archiveList($user->id, $board, $params['id']);

        return $this->redirect('/boards/' . $board->publicId, 'List archived.');
    }

    /** @param array{board: string, id: int} $params */
    public function restore(Request $request, array $params): Response
    {
        $user = $this->user($request);
        $board = $this->boards->boardFor($user->id, $params['board']);
        $this->boards->archiveList($user->id, $board, $params['id'], false);

        return $this->redirect('/boards/' . $board->publicId, 'List restored.');
    }
}
