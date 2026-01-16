<?php

require_once __DIR__ . '/../App.php';

use Classes\Session;
use Classes\TaskRepository;

$request->requirePostWithToken();
$user = require_login();

$id     = $request->int('id');
$status = $request->input('status');

if (!$id || !isset(TaskRepository::STATUSES[$status])) {
    abort(422, 'That move is not allowed.');
}

if (!$tasks->find($user['id'], $id)) {
    abort(404, "We couldn't find that task.");
}

$tasks->move($user['id'], $id, $status);

// The board moves cards with fetch() and swaps in the fresh card html.
if ($request->wantsJson()) {
    $task = $tasks->find($user['id'], $id);
    ob_start();
    require BASE_PATH . '/inc/task_card.php';
    $html = ob_get_clean();

    $request->json([
        'ok'    => true,
        'html'  => $html,
        'stats' => $tasks->stats($tasks->board($user['id'])),
    ]);
}

Session::flash('success', $status === 'done' ? 'Nice, task finished!' : 'Task moved to ' . TaskRepository::STATUSES[$status] . '.');
$request->redirect('index.php');
