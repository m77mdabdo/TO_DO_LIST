<?php

require_once __DIR__ . '/../App.php';

use Classes\Validation\Validation;

$request->requirePostWithToken();

$email    = $request->input('email');
$password = (string) $request->post('password', '');

if ($auth->tooManyAttempts($request->ip())) {
    back_with_errors(['login' => 'Too many attempts. Please wait 15 minutes and try again.'], 'login.php');
}

$validation = (new Validation())
    ->validate('email', $email, ['Required', 'Email'], 'Email')
    ->validate('password', $password, ['Required'], 'Password');

if ($validation->fails()) {
    back_with_errors($validation->errors(), 'login.php');
}

if (!$auth->attempt($email, $password, $request->ip())) {
    back_with_errors(['login' => 'That email and password don\'t match.'], 'login.php');
}

$request->redirect('index.php');
