<?php

require_once __DIR__ . '/../App.php';

$request->requirePostWithToken();

$auth->logout();

$request->redirect('login.php');
