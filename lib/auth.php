<?php

declare(strict_types=1);

function admin_user(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_admin(): array
{
    $admin = admin_user();
    if (!$admin) {
        redirect(app_url('admin/login.php'));
    }
    return $admin;
}

function attempt_admin_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, email, password_hash FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    $_SESSION['admin_id'] = (int) $row['id'];
    session_regenerate_id(true);
    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id']);
}

function create_or_reset_admin(string $email, string $password): void
{
    $email = strtolower(trim($email));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if ($existing) {
        $upd = db()->prepare('UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$hash, (int) $existing['id']]);
        return;
    }
    $ins = db()->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
    $ins->execute([$email, $hash]);
}
