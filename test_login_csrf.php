<?php
session_start();
$_SESSION['csrf_token'] = 'old_token';
$_POST['csrf_token'] = 'old_token';
$_SERVER['REQUEST_METHOD'] = 'POST';

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "Erro CSRF detectado.\n";
    echo "POST token: " . $_POST['csrf_token'] . "\n";
    echo "SESSION token: " . $_SESSION['csrf_token'] . "\n";
} else {
    echo "CSRF validation passed.\n";
}
