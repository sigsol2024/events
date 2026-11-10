<?php

declare(strict_types=1);

function setting_get(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }
    $value = $row['setting_value'];
    return $value === null ? $default : (string) $value;
}

function setting_set(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $stmt->execute([$key, $value]);
}

function gate_pin_hash(): ?string
{
    $hash = setting_get('gate_pin_hash');
    return $hash !== null && $hash !== '' ? $hash : null;
}

function generate_gate_pin(): string
{
    $pin = (string) random_int(100000, 999999);
    setting_set('gate_pin_hash', password_hash($pin, PASSWORD_DEFAULT));
    return $pin;
}

function verify_gate_pin(string $pin): bool
{
    $hash = gate_pin_hash();
    if ($hash === null) {
        return false;
    }
    return password_verify($pin, $hash);
}

function record_pin_attempt(string $ticketCode): void
{
    $stmt = db()->prepare('INSERT INTO pin_attempts (ip_address, ticket_code) VALUES (?, ?)');
    $stmt->execute([client_ip(), $ticketCode]);
}

function pin_attempts_blocked(string $ticketCode, int $max = 8, int $windowMinutes = 15): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS c FROM pin_attempts
         WHERE ip_address = ? AND ticket_code = ?
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([client_ip(), $ticketCode, $windowMinutes]);
    return (int) ($stmt->fetch()['c'] ?? 0) >= $max;
}
