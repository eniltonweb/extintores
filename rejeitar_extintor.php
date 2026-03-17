<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: aprovar_extintores.php?message=Erro:+Método+inválido.');
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: aprovar_extintores.php?message=Erro:+Falha+na+validação+de+segurança.');
    exit();
}

if (isset($_POST['codigo'])) {
    $codigo_extintor = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);

    // Buscar o id do extintor
    $sql_buscar_id = "SELECT id FROM bd_extintores WHERE codigo = ?";
    $stmt_buscar_id = $conn->prepare($sql_buscar_id);
    $stmt_buscar_id->bind_param("s", $codigo_extintor);
    $stmt_buscar_id->execute();
    $result_buscar_id = $stmt_buscar_id->get_result();

    if ($result_buscar_id->num_rows > 0) {
        $row = $result_buscar_id->fetch_assoc();
        $extintor_id = $row['id'];

        // Primeiro, excluir os registros relacionados na tabela auditoria_logs
        $sql_excluir_auditoria = "DELETE FROM auditoria_logs WHERE extintor_id = ?";
        $stmt_excluir_auditoria = $conn->prepare($sql_excluir_auditoria);
        $stmt_excluir_auditoria->bind_param("i", $extintor_id);
        $stmt_excluir_auditoria->execute();

        // Em seguida, excluir o extintor da tabela bd_extintores
        $sql_excluir_extintor = "DELETE FROM bd_extintores WHERE id = ?";
        $stmt_excluir_extintor = $conn->prepare($sql_excluir_extintor);
        $stmt_excluir_extintor->bind_param("i", $extintor_id);

        if ($stmt_excluir_extintor->execute()) {
            // Redirecionar com mensagem de sucesso
            header('Location: aprovar_extintores.php?message=Extintor+rejeitado+e+removido+com+sucesso.');
            exit();
        } else {
            // Redirecionar com mensagem de erro
            header('Location: aprovar_extintores.php?message=Erro:+Não+foi+possível+remover+o+extintor.');
            exit();
        }
    } else {
        // Redirecionar com mensagem de erro se o extintor não for encontrado
        header('Location: aprovar_extintores.php?message=Erro:+Extintor+não+encontrado.');
        exit();
    }
} else {
    // Redirecionar com mensagem de erro se o código não for fornecido
    header('Location: aprovar_extintores.php?message=Erro:+Código+do+extintor+não+fornecido.');
    exit();
}

$conn->close();
?>