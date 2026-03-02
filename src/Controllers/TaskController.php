<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\Session\Session;
use App\Task\Status;
use App\Task\TaskService;
use App\Validation\ValidationException;
use App\View\View;

final class TaskController extends Controller
{
    private const FIELDS = ['title', 'notes', 'priority', 'due_date', 'category'];

    public function __construct(View $view, Url $url, Session $session, private TaskService $tasks)
    {
        parent::__construct($view, $url, $session);
    }

    public function store(Request $request): Response
    {
        try {
            $this->tasks->create($this->user($request)->id, $request->only(self::FIELDS));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, '/');
        }

        return $this->redirect('/', 'Task added.');
    }

    /** @param array{id: int} $params */
    public function edit(Request $request, array $params): Response
    {
        $userId = $this->user($request)->id;

        return $this->render('tasks/edit', [
            'title'      => 'Edit task',
            'bodyClass'  => 'page-form',
            'inlineErrors' => true,
            'task'       => $this->tasks->find($userId, $params['id']),
            'categories' => $this->tasks->categories($userId),
        ]);
    }

    /** @param array{id: int} $params */
    public function update(Request $request, array $params): Response
    {
        try {
            $this->tasks->update($this->user($request)->id, $params['id'], $request->only(self::FIELDS));
        } catch (ValidationException $e) {
            return $this->backWithErrors($e, $request, "/tasks/{$params['id']}/edit");
        }

        return $this->redirect('/', 'Changes saved.');
    }

    /** @param array{id: int} $params */
    public function move(Request $request, array $params): Response
    {
        $status = Status::tryFrom($request->input('status')) ?? throw new HttpException(422, 'That move is not allowed.');
        $userId = $this->user($request)->id;
        $task   = $this->tasks->move($userId, $params['id'], $status);

        // The board moves cards with fetch() and swaps in the fresh card html.
        if ($request->wantsJson()) {
            return Response::json([
                'ok'    => true,
                'html'  => $this->view->partial('partials/task-card', ['task' => $task]),
                'stats' => $this->tasks->stats($userId),
            ]);
        }

        return $this->redirect('/', $status === Status::Done ? 'Nice, task finished!' : "Task moved to {$status->label()}.");
    }

    /** @param array{id: int} $params */
    public function destroy(Request $request, array $params): Response
    {
        $userId = $this->user($request)->id;
        $this->tasks->delete($userId, $params['id']);

        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'stats' => $this->tasks->stats($userId)]);
        }

        return $this->redirect('/', 'Task deleted.');
    }

    public function clearDone(Request $request): Response
    {
        $count = $this->tasks->clearDone($this->user($request)->id);

        return $this->redirect('/', $count === 1 ? 'Cleared 1 finished task.' : "Cleared $count finished tasks.");
    }
}
