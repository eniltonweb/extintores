<?php
declare(strict_types=1);

// Carrega configuração do ambiente; valores de fallback são definidos apenas
// para desenvolvimento local. Em produção, configure DB_HOST, DB_USER, DB_PASS
// e DB_NAME no ambiente.
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'usuario';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'extintores';

$conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    error_log('Erro de conexão com o banco: ' . $conn->connect_error);
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}

$conn->set_charset('utf8');
