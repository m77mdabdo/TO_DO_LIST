<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Session\Session;
use App\Support\Clock;
use App\Support\Greeting;
use App\Task\BoardStats;
use App\Task\TaskService;
use App\View\View;

final class BoardController extends Controller
{
    public function __construct(
        View $view,
        Url $url,
        Session $session,
        private TaskService $tasks,
        private Greeting $greeting,
        private Clock $clock,
    ) {
        parent::__construct($view, $url, $session);
    }

    public function index(Request $request): Response
    {
        $user  = $this->user($request);
        $board = $this->tasks->board($user->id);

        return $this->render('board', [
            'title'      => 'Board',
            'bodyClass'  => 'page-board',
            'user'       => $user,
            'board'      => $board,
            'stats'      => BoardStats::of($board, $this->clock->now()),
            'categories' => $this->tasks->categories($user->id),
            'greeting'   => $this->greeting->forNow(),
        ]);
    }
}
