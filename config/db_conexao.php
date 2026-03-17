<?php
declare(strict_types=1);

// Carrega configuração do ambiente; valores de fallback são definidos apenas
// para desenvolvimento local. Em produção, configure DB_HOST, DB_USER, DB_PASS
// e DB_NAME no ambiente.
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'usuario';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'extintores';

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8');
} catch (mysqli_sql_exception $e) {
    error_log('Erro de conexão com o banco: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}

// Inicializa o token CSRF se a sessão estiver ativa
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
