<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Verificar se o código do extintor foi passado na URL
if (isset($_GET['codigo'])) {
    $codigo = filter_input(INPUT_GET, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);

    // Atualizar o status do extintor para "Aprovado"
    $sql_aprovar = "UPDATE bd_extintores SET status_aprovacao = 'Aprovado' WHERE codigo = ?";
    $stmt_aprovar = $conn->prepare($sql_aprovar);

    if ($stmt_aprovar) {
        $stmt_aprovar->bind_param('s', $codigo);

        if ($stmt_aprovar->execute()) {
            // Registrar na auditoria
            auditoria('Aprovação de Extintor', $codigo, $_SESSION['user_id'], $_SESSION['user_level'], 'Extintor aprovado com sucesso');

            // Redirecionar com mensagem de sucesso
            header('Location: aprovar_extintores.php?message=Extintor+aprovado+com+sucesso.');
            exit();
        } else {
            // Redirecionar com mensagem de erro
            header('Location: aprovar_extintores.php?message=Erro:+Não+foi+possível+aprovar+o+extintor.');
            exit();
        }
        $stmt_aprovar->close();
    } else {
        // Redirecionar com mensagem de erro caso o statement não possa ser criado
        header('Location: aprovar_extintores.php?message=Erro:+Não+foi+possível+preparar+o+statement+para+aprovação+do+extintor.');
        exit();
    }
} else {
    // Redirecionar caso o código do extintor não seja passado
    header('Location: aprovar_extintores.php?message=Erro:+Código+do+extintor+não+encontrado.');
    exit();
}

$conn->close();
?>