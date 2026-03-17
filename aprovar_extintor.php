<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Verificar o token CSRF e o código do extintor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: aprovar_extintores.php?message=Erro:+Token+CSRF+inválido.');
        exit();
    }

    if (isset($_POST['codigo'])) {
        $codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);

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
} else {
    // Redirecionar se não for uma requisição POST
    header('Location: aprovar_extintores.php');
    exit();
}

$conn->close();
?>