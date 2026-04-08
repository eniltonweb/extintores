<?php
if (!function_exists('aprovar_extintor_logic')) {
    function aprovar_extintor_logic($conn, $session, $post, $server) {
        if (!isset($session['user_id']) || $session['user_level'] != 'admin') {
            return 'Location: index.php';
        }

        if ($server['REQUEST_METHOD'] !== 'POST') {
            return 'Location: aprovar_extintores.php?message=' . urlencode('Erro: Método inválido.');
        }

        if (!isset($post['csrf_token']) || !hash_equals($session['csrf_token'] ?? '', $post['csrf_token'])) {
            return 'Location: aprovar_extintores.php?message=' . urlencode('Erro: Falha na validação de segurança.');
        }

        if (isset($post['codigo'])) {
            // Replicate filter_input behavior manually or use $_POST to support testing easily
            $codigo = filter_var($post['codigo'], FILTER_SANITIZE_SPECIAL_CHARS);

            $sql_aprovar = "UPDATE bd_extintores SET status_aprovacao = 'Aprovado' WHERE codigo = ?";
            $stmt_aprovar = $conn->prepare($sql_aprovar);

            if ($stmt_aprovar) {
                $stmt_aprovar->bind_param('s', $codigo);

                if ($stmt_aprovar->execute()) {
                    if (function_exists('auditoria')) {
                        auditoria('Aprovação de Extintor', $codigo, $session['user_id'], $session['user_level'], 'Extintor aprovado com sucesso');
                    }
                    $stmt_aprovar->close();
                    return 'Location: aprovar_extintores.php?message=' . urlencode('Extintor aprovado com sucesso.');
                } else {
                    $stmt_aprovar->close();
                    return 'Location: aprovar_extintores.php?message=' . urlencode('Erro: Não foi possível aprovar o extintor.');
                }
            } else {
                return 'Location: aprovar_extintores.php?message=' . urlencode('Erro: Não foi possível preparar o statement para aprovação do extintor.');
            }
        } else {
            return 'Location: aprovar_extintores.php?message=' . urlencode('Erro: Código do extintor não encontrado.');
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    session_start();
    require_once __DIR__ . '/config/db_conexao.php';
    include 'auditoria.php';

    $redirect = aprovar_extintor_logic($conn, $_SESSION, $_POST, $_SERVER);

    if ($conn) {
        $conn->close();
    }

    if ($redirect) {
        header($redirect);
        exit();
    }
}
?>