<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

// Simulate POST payload from a previously rendered form
$_POST['csrf_token'] = $_SESSION['csrf_token'];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Gerar token CSRF (simulating login.php code)
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "Erro CSRF detectado.\n";
} else {
    echo "Sucesso CSRF.\n";
}
