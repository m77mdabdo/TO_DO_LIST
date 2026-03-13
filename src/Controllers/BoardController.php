<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Http\Request;
use App\Http\Response;
use App\Support\Clock;
use App\Task\BoardStats;
use App\Task\TaskService;
use App\View\View;

final class BoardController
{
    public function __construct(
        private TaskService $tasks,
        private View $view,
        private Clock $clock,
    ) {
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user  = $request->attribute('user');
        $board = $this->tasks->board($user->id);

        return Response::html($this->view->render('tasks/board', [
            'title'      => 'Board',
            'bodyClass'  => 'page-board',
            'user'       => $user,
            'board'      => $board,
            'stats'      => BoardStats::of($board, $this->clock->now()),
            'categories' => $this->tasks->categories($user->id),
        ]));
    }
}
