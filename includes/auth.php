<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_user(): ?array
{
    $user = $_SESSION['admin_user'] ?? null;
    return is_array($user) ? $user : null;
}

function admin_logged_in(): bool
{
    return admin_user() !== null;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        $next = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        header('Location: login.php?next=' . rawurlencode($next));
        exit;
    }
}

function admin_login(string $email, string $password): bool
{
    $pdo = db();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare('SELECT id, email, password_hash, display_name FROM admin_users WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('Moleqra admin login failed: ' . $e->getMessage());
        return false;
    }
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int)$user['id'],
        'email' => (string)$user['email'],
        'display_name' => (string)$user['display_name'],
    ];
    $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}
