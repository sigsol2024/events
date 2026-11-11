<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    global $CONFIG;
    $parts = explode('.', $key);
    $value = $CONFIG;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) config('app_url', ''), '/');
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function format_ngn(float|string $amount): string
{
    $n = (float) $amount;
    if ($n >= 1000000) {
        $m = $n / 1000000;
        $label = rtrim(rtrim(number_format($m, 2, '.', ''), '0'), '.');
        return $label . ' Million';
    }
    if ($n >= 1000 && fmod($n, 1000.0) === 0.0) {
        return number_format($n / 1000, 0) . 'K';
    }
    return '₦' . number_format($n, 2);
}

function money_string(float|string $amount): string
{
    return number_format((float) $amount, 2, '.', '');
}

function is_adult(string $dob, int $minAge = 18): bool
{
    try {
        $birth = new DateTimeImmutable($dob);
        $today = new DateTimeImmutable('today');
        return $birth->diff($today)->y >= $minAge;
    } catch (Throwable) {
        return false;
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sign_token(string $payload): string
{
    $secret = (string) config('app_secret', '');
    return hash_hmac('sha256', $payload, $secret);
}

function make_access_token(): string
{
    return bin2hex(random_bytes(24));
}

function make_ticket_code(): string
{
    return bin2hex(random_bytes(20));
}

function make_booking_reference(): string
{
    return 'TBH-' . strtoupper(bin2hex(random_bytes(5)));
}

function render_layout(string $title, string $content, array $options = []): void
{
    $bodyClass = $options['body_class'] ?? '';
    $showNav = $options['show_nav'] ?? true;
    include dirname(__DIR__) . '/partials/layout.php';
}

function render_admin(string $title, string $content, string $active = 'dashboard'): void
{
    $admin = admin_user();
    if (!$admin) {
        redirect(app_url('admin/login.php'));
    }
    include dirname(__DIR__) . '/admin/_layout.php';
}

function admin_status_class(string $status): string
{
    $safe = preg_replace('/[^a-z]/', '', strtolower($status)) ?: 'pending';
    return 'admin-status admin-status-' . $safe;
}
