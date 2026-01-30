<?php

require_once __DIR__ . '/../App.php';
require_once __DIR__ . '/taskRules.php';

use Classes\Session;

$request->requirePostWithToken();
$user = require_login();

$validation = validate_task($request);

if ($validation->fails()) {
    back_with_errors($validation->errors(), 'index.php');
}

$tasks->create($user['id'], task_input($request));

Session::flash('success', 'Task added.');
$request->redirect('index.php');
