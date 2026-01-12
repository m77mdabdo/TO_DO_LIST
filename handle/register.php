<?php

require_once __DIR__ . '/../App.php';

use Classes\Session;
use Classes\Validation\Validation;

$request->requirePostWithToken();

$name     = $request->input('name');
$email    = mb_strtolower($request->input('email'));
$password = (string) $request->post('password', '');

$validation = (new Validation())
    ->validate('name', $name, ['Required', 'Str', 'Min:2', 'Max:60'], 'Name')
    ->validate('email', $email, ['Required', 'Email', 'Max:190'], 'Email')
    ->validate('password', $password, ['Required', 'Min:8', 'Max:72'], 'Password');

$errors = $validation->errors();

if (!isset($errors['password']) && $password !== $request->post('password_confirmation')) {
    $errors['password_confirmation'] = 'Passwords don\'t match.';
}

if (!isset($errors['email']) && $auth->emailTaken($email)) {
    $errors['email'] = 'This email already has an account.';
}

if ($errors) {
    back_with_errors($errors, 'register.php');
}

$auth->register($name, $email, $password);

Session::flash('success', 'Welcome aboard! Add your first task.');
$request->redirect('index.php');
