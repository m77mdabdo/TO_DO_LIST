<?php

require_once __DIR__ . '/../App.php';

use Classes\Session;

$request->requirePostWithToken();
$user = require_login();

$count = $tasks->clearDone($user['id']);

Session::flash('success', $count === 1 ? 'Cleared 1 finished task.' : "Cleared $count finished tasks.");
$request->redirect('index.php');
