<?php
define('DB_HOST', 'eniltonbd.mysql.dbaas.com.br');
define('DB_USER', 'eniltonbd');
define('DB_PASS', 'Nil2024#'); 
define('DB_NAME', 'eniltonbd');

// Criar conexão
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
