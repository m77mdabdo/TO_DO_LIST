<?php

require_once __DIR__ . '/../App.php';

use Classes\Session;

$request->requirePostWithToken();
$user = require_login();

$id = $request->int('id');

if (!$id || !$tasks->delete($user['id'], $id)) {
    abort(404, "We couldn't find that task.");
}

if ($request->wantsJson()) {
    $request->json(['ok' => true, 'stats' => $tasks->stats($tasks->board($user['id']))]);
}

Session::flash('success', 'Task deleted.');
$request->redirect('index.php');
