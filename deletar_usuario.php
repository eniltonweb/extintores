<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

// Verificar se o usuário está logado e se tem permissão para acessar esta página
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Erro CSRF detectado.');
    }

    $id = intval($_POST['id']);

    // Obter o nome do usuário antes de deletar
    $sql_user = "SELECT username FROM usuarios WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    if (!$stmt_user) {
        echo "Erro ao preparar a consulta: " . $conn->error;
        exit();
    }
    $stmt_user->bind_param("i", $id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows > 0) {
        $user = $result_user->fetch_assoc();
        $username = $user['username'];

        // Deletar registros associados na tabela auditoria_logs
        $sql_auditoria = "DELETE FROM auditoria_logs WHERE user_id = ?";
        $stmt_auditoria = $conn->prepare($sql_auditoria);
        if (!$stmt_auditoria) {
            echo "Erro ao preparar a consulta de deleção de auditoria: " . $conn->error;
            exit();
        }
        $stmt_auditoria->bind_param("i", $id);
        if (!$stmt_auditoria->execute()) {
            echo "Erro ao deletar registros de auditoria: " . $stmt_auditoria->error;
            exit();
        }
        $stmt_auditoria->close();

        // Deletar usuário
        $sql = "DELETE FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "Erro ao preparar a consulta de deleção: " . $conn->error;
            exit();
        }
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            auditoria('Deletar um usuário', null, $_SESSION['user_id'], $_SESSION['user_level'], 'Usuário ' . $username . ' deletado com sucesso.');
            header('Location: registrar_usuario.php?message=Usuário deletado com sucesso');
            exit();
        } else {
            echo "Erro ao deletar usuário: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Usuário não encontrado.";
    }

    $stmt_user->close();
} else {
    echo "Requisição inválida ou ID do usuário não fornecido.";
}

$conn->close();
?>