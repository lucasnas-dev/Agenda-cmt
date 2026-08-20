<?php
declare(strict_types=1);

$host = '127.0.0.1';
$dbname = 'agenda_medica';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);

    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $dbError = str_contains($e->getMessage(), 'Unknown database')
        || str_contains($e->getMessage(), '1049')
        || str_contains($e->getMessage(), 'Connection refused')
        || str_contains($e->getMessage(), 'SQLSTATE[HY000] [2002]');

    if ($dbError) {
        die(
            '<div style="font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;">'
            . '<strong>Erro de conexão com o banco de dados.</strong><br>'
            . 'Verifique se o MySQL do XAMPP está iniciado e se o banco <strong>agenda_medica</strong> foi criado/importado.<br><br>'
            . 'Passos:<br>'
            . '1. Abra o XAMPP e inicie o módulo MySQL.<br>'
            . '2. Acesse <a href="http://localhost/phpmyadmin" target="_blank" rel="noreferrer">phpMyAdmin</a>.<br>'
            . '3. Importe o arquivo <strong>schema.sql</strong>.<br>'
            . '4. Acesse <a href="http://localhost/Agenda-cmt/login.php">http://localhost/Agenda-cmt/login.php</a>.<br><br>'
            . '<small>Detalhe técnico: ' . $message . '</small>'
            . '</div>'
        );
    }

    die(
        '<div style="font-family: Arial, sans-serif; padding: 20px; line-height: 1.6;">'
        . '<strong>Erro de conexão com o banco de dados.</strong><br>'
        . '<small>Detalhe técnico: ' . $message . '</small>'
        . '</div>'
    );
}
