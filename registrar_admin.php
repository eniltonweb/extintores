<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config/db_conexao.php';

$username = 'admin';
$password = password_hash('admin_password', PASSWORD_DEFAULT);
$nivel_acesso = 'admin';

// Verificar se o usuário administrador já existe
$sql_check = "SELECT * FROM usuarios WHERE username = '$username'";
$result_check = $conn->query($sql_check);

if ($result_check) {
    if ($result_check->num_rows == 0) {
        // Inserir o usuário administrador
        $sql = "INSERT INTO usuarios (username, password, nivel_acesso) VALUES ('$username', '$password', '$nivel_acesso')";
        
        if ($conn->query($sql) === TRUE) {
            echo "Usuário administrador registrado com sucesso.";
        } else {
            echo "Erro ao registrar usuário administrador: " . $conn->error;
        }
    } else {
        echo "Usuário administrador já existe.";
    }
} else {
    echo "Erro ao verificar existência do usuário administrador: " . $conn->error;
}

$conn->close();
?>
