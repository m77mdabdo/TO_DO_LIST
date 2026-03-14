<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\User;
use App\Http\HttpException;
use App\Http\Redirector;
use App\Http\Request;
use App\Http\Response;
use App\Task\Status;
use App\Task\TaskNotFound;
use App\Task\TaskService;
use App\Validation\ValidationException;
use App\View\View;

/**
 * Translates HTTP into TaskService calls. Every action answers with a redirect,
 * or with JSON when the board calls it through fetch().
 */
final class TaskController
{
    private const FIELDS = ['title', 'notes', 'priority', 'due_date', 'category'];

    public function __construct(
        private TaskService $tasks,
        private View $view,
        private Redirector $redirect,
    ) {
    }

    public function store(Request $request): Response
    {
        try {
            $this->tasks->create($this->userId($request), $request->only(self::FIELDS));
        } catch (ValidationException $e) {
            return $this->redirect->withErrors('/', $e->errors(), $request->body());
        }

        return $this->redirect->withSuccess('/', 'Task added.');
    }

    public function edit(Request $request): Response
    {
        $userId = $this->userId($request);

        return Response::html($this->view->render('tasks/edit', [
            'title'        => 'Edit task',
            'bodyClass'    => 'page-form',
            'inlineErrors' => true,
            'task'         => $this->tasks->find($userId, $this->taskId($request)),
            'categories'   => $this->tasks->categories($userId),
        ]));
    }

    public function update(Request $request): Response
    {
        $id = $this->taskId($request);

        try {
            $this->tasks->update($this->userId($request), $id, $request->only(self::FIELDS));
        } catch (ValidationException $e) {
            return $this->redirect->withErrors("/tasks/$id/edit", $e->errors(), $request->body());
        }

        return $this->redirect->withSuccess('/', 'Changes saved.');
    }

    public function move(Request $request): Response
    {
        $userId = $this->userId($request);
        $status = Status::tryFrom($request->input('status')) ?? throw HttpException::unprocessable('That move is not allowed.');
        $task   = $this->tasks->move($userId, $this->taskId($request), $status);

        if ($request->wantsJson()) {
            return Response::json([
                'ok'    => true,
                'html'  => $this->view->partial('tasks/card', ['task' => $task]),
                'stats' => $this->tasks->stats($userId),
            ]);
        }

        return $this->redirect->withSuccess('/', $status === Status::Done
            ? 'Nice, task finished!'
            : 'Task moved to ' . $status->label() . '.');
    }

    public function destroy(Request $request): Response
    {
        $userId = $this->userId($request);
        $this->tasks->delete($userId, $this->taskId($request));

        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'stats' => $this->tasks->stats($userId)]);
        }

        return $this->redirect->withSuccess('/', 'Task deleted.');
    }

    public function clearDone(Request $request): Response
    {
        $count = $this->tasks->clearDone($this->userId($request));

        return $this->redirect->withSuccess('/', $count === 1 ? 'Cleared 1 finished task.' : "Cleared $count finished tasks.");
    }

    private function userId(Request $request): int
    {
        /** @var User $user */
        $user = $request->attribute('user');

        return $user->id;
    }

    private function taskId(Request $request): int
    {
        $id = filter_var($request->attribute('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? throw new TaskNotFound() : $id;
    }
}
