<?php
if (!function_exists('limpar_historico_logic')) {
    function limpar_historico_logic($conn, $session, $post, $server) {
        if (!isset($session['user_id']) || $session['user_level'] != 'admin') {
            return 'Location: index.php';
        }

        if ($server['REQUEST_METHOD'] !== 'POST') {
            return 'Location: historico_manutencao.php';
        }

        if (!isset($post['csrf_token']) || !isset($session['csrf_token']) || !hash_equals($session['csrf_token'], $post['csrf_token'])) {
            return 'Location: historico_manutencao.php?message=' . urlencode('Erro: Token CSRF inválido.');
        }

        // Limpar os campos de manutenção na tabela bd_extintores
        $sql = "
            UPDATE bd_extintores
            SET manutencao_n2 = NULL, proxima_manutencao_n2 = NULL, dias_para_expirar_n2 = NULL, cobertura = NULL
            WHERE manutencao_n2 IS NOT NULL
        ";
        if ($conn->query($sql) === TRUE) {
            if (function_exists('auditoria')) {
                auditoria('Limpeza de histórico', null, $session['user_id'], $session['user_level'], 'O histórico de manutenções foi limpo.');
            }
            return 'Location: historico_manutencao.php?message=' . urlencode('Histórico de manutenções limpo com sucesso');
        } else {
            error_log("Erro ao limpar histórico: " . ($conn->error ?? 'Unknown error'));
            return 'Location: historico_manutencao.php?message=' . urlencode('Erro ao limpar o histórico de manutenção.');
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    session_start();
    require_once __DIR__ . '/config/db_conexao.php';
    require_once __DIR__ . '/auditoria.php';

    $redirect = limpar_historico_logic($conn, $_SESSION, $_POST, $_SERVER);

    if ($conn) {
        $conn->close();
    }

    if ($redirect) {
        header($redirect);
        exit();
    }
}
?>
