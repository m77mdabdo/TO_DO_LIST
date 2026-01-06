<?php

namespace Classes;

final class Request
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    // trimmed string from POST (or GET), never an array
    public function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function int(string $key): ?int
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // every state-changing handler calls this first
    public function requirePostWithToken(): void
    {
        if (!$this->isPost()) {
            abort(405, 'This action only accepts form submissions.');
        }

        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!Csrf::valid($token)) {
            abort(419, 'Your session expired. Refresh the page and try again.');
        }
    }

    public function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    public function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
