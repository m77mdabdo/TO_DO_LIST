<?php

declare(strict_types=1);

namespace App\Modules\Access\Http;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Modules\Access\Application\BoardMemberService;
use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Identity\Contract\UserDirectory;
use App\Session\Session;
use App\Validation\ValidationException;
use App\View\View;

final class BoardMembersController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private BoardMemberService $members,
        private BoardDirectory $boards,
        private Gate $gate,
        private UserDirectory $users,
    ) {
        parent::__construct($view, $url, $session);
    }

    /** @param array{board: string} $params */
    public function index(Request $request, array $params): Response
    {
        $viewer = $this->user($request);
        $board = $this->board($params);
        $role = $this->gate->authorize($viewer->id, $board->id, Permission::ViewBoard);
        $overview = $this->members->overview($board);

        return $this->render('access/members', [
            'title'     => 'Board members',
            'board'     => $board,
            'viewerId'  => $viewer->id,
            'canManage' => $role->allows(Permission::ManageMembers) && !$board->archived,
            'people'    => $this->users->findMany(array_keys($overview['workspaceRoles'] + $overview['explicit'])),
            ...$overview,
        ]);
    }

    /** Adds someone, or changes their role. @param array{board: string} $params */
    public function store(Request $request, array $params): Response
    {
        $board = $this->board($params);

        try {
            $this->members->set($this->user($request)->id, $board, (int) $request->input('user_id'), $request->input('role'));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->path($board));
        }

        return $this->redirect($this->path($board), 'Board members updated.');
    }

    /** @param array{board: string, id: string} $params */
    public function remove(Request $request, array $params): Response
    {
        $board = $this->board($params);

        try {
            $this->members->remove($this->user($request)->id, $board, (int) $params['id']);
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, $this->path($board));
        }

        return $this->redirect($this->path($board), 'Board members updated.');
    }

    /** @param array{board: string} $params */
    private function board(array $params): BoardSummary
    {
        return $this->boards->findByPublicId($params['board']) ?? throw AccessDenied::hidden();
    }

    private function path(BoardSummary $board): string
    {
        return '/boards/' . $board->publicId . '/members';
    }
}
