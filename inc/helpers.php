<?php

use Classes\Session;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

// Builds links from the app root, so pages and handle/* both resolve the same way
// and the app keeps working inside any XAMPP sub folder.
function url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        if (basename($dir) === 'handle') {
            $dir = dirname($dir);
        }
        $base = rtrim($dir, '/') . '/';
    }

    return $base . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/assets/' . $path;
    $version = is_file($file) ? filemtime($file) : 1;

    return url('assets/' . $path) . '?v=' . $version;
}

function send_security_headers(): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function abort(int $code, string $message = 'Page not found.'): never
{
    http_response_code($code);

    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message]);
        exit;
    }

    $errorCode = $code;
    $errorMessage = $message;
    require BASE_PATH . '/404.php';
    exit;
}

function require_login(): array
{
    global $auth, $request;

    if (!$auth->check()) {
        if ($request->wantsJson()) {
            $request->json(['ok' => false, 'message' => 'Please log in again.'], 401);
        }
        $request->redirect('login.php');
    }

    return $auth->user();
}

function back_with_errors(array $errors, string $to, array $except = ['_token', 'password', 'password_confirmation']): never
{
    global $request;

    Session::flash('errors', $errors);
    Session::flash('old', array_diff_key($_POST, array_flip($except)));
    $request->redirect($to);
}

/* ---------- view helpers ---------- */

function old(string $key, string $default = ''): string
{
    static $old = null;
    $old ??= Session::pull('old', []);

    return isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default;
}

function errors(): array
{
    static $errors = null;
    $errors ??= Session::pull('errors', []);

    return $errors;
}

function category_class(?string $category): string
{
    return 'cat-' . (crc32(mb_strtolower((string) $category)) % 8);
}

function due_state(?string $due, string $status = 'todo'): ?string
{
    if (!$due || $status === 'done') {
        return null;
    }

    $diff = (int) (new DateTime('today'))->diff(new DateTime($due))->format('%r%a');

    return match (true) {
        $diff < 0   => 'overdue',
        $diff === 0 => 'today',
        $diff <= 3  => 'soon',
        default     => 'later',
    };
}

function human_due(string $due): string
{
    $diff = (int) (new DateTime('today'))->diff(new DateTime($due))->format('%r%a');

    return match (true) {
        $diff === 0  => 'Today',
        $diff === 1  => 'Tomorrow',
        $diff === -1 => 'Yesterday',
        $diff < -1   => abs($diff) . ' days late',
        $diff <= 6   => (new DateTime($due))->format('l'),
        default      => (new DateTime($due))->format('M j'),
    };
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }

    $seconds = time() - strtotime($datetime);

    return match (true) {
        $seconds < 60     => 'just now',
        $seconds < 3600   => floor($seconds / 60) . 'm ago',
        $seconds < 86400  => floor($seconds / 3600) . 'h ago',
        $seconds < 604800 => floor($seconds / 86400) . 'd ago',
        default           => date('M j', strtotime($datetime)),
    };
}

function greeting(): string
{
    $hour = (int) date('G');

    return match (true) {
        $hour < 5  => 'Working late',
        $hour < 12 => 'Good morning',
        $hour < 17 => 'Good afternoon',
        default    => 'Good evening',
    };
}

function icon(string $name, string $class = ''): string
{
    static $paths = [
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
        'edit'     => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'right'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'left'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>',
        'flag'     => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.5"/>',
        'sparkle'  => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'fire'     => '<path d="M12 21a7 7 0 0 0 7-7c0-4-3-6-4-9-1 2-2 3-3 3 0-2-1-4-3-5 0 4-4 6-4 11a7 7 0 0 0 7 7Z"/>',
        'broom'    => '<path d="M14 4 9 13M6 14h7l1 7H5l1-7ZM8 17v4M11 17v4"/>',
        'keyboard' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
    ];

    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
}
