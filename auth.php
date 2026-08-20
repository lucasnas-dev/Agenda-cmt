<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Usuário';
}

function log_action(PDO $pdo, int $userId, string $action, string $entityType, int $entityId): void
{
    $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $action, $entityType, $entityId]);
}
