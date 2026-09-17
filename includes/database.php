<?php

declare(strict_types=1);

function db(): ?PDO
{
    static $pdo = false;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if ($pdo === null) {
        return null;
    }

    $configFile = __DIR__ . '/../config/database.php';
    if (!is_file($configFile)) {
        $pdo = null;
        return null;
    }

    $cfg = require $configFile;
    try {
        $pdo = new PDO(
            (string)($cfg['dsn'] ?? ''),
            (string)($cfg['username'] ?? ''),
            (string)($cfg['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (Throwable $e) {
        error_log('Moleqra database connection failed: ' . $e->getMessage());
        $pdo = null;
        return null;
    }
}

function db_ready(): bool
{
    return db() instanceof PDO;
}
