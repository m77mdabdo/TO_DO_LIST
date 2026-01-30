<?php

require_once __DIR__ . '/../App.php';
require_once __DIR__ . '/taskRules.php';

use Classes\Session;

$request->requirePostWithToken();
$user = require_login();

$id = $request->int('id');

if (!$id || !$tasks->find($user['id'], $id)) {
    abort(404, "We couldn't find that task.");
}

$validation = validate_task($request);

if ($validation->fails()) {
    back_with_errors($validation->errors(), 'edit.php?id=' . $id);
}

$tasks->update($user['id'], $id, task_input($request));

Session::flash('success', 'Changes saved.');
$request->redirect('index.php');
