<?php
if (!function_exists('limpar_historico_inspecao_logic')) {
    function limpar_historico_inspecao_logic($conn, $session, $post, $server) {
        if (!isset($session['user_id']) || $session['user_level'] != 'admin') {
            return 'Location: index.php';
        }

        if ($server['REQUEST_METHOD'] !== 'POST') {
            return 'Location: historico_inspecao.php';
        }

        if (!isset($post['csrf_token']) || !isset($session['csrf_token']) || !hash_equals($session['csrf_token'], $post['csrf_token'])) {
            return 'Location: historico_inspecao.php?message=' . urlencode('Erro: Token CSRF inválido.');
        }

        // Limpar histórico de inspeção de nível 1
        $sql = "UPDATE bd_extintores SET
                    inspecao_trimestral_nivel1 = NULL,
                    selo_do_Inmetro = NULL,
                    sinalizacao_vertical = NULL,
                    sinalizacao_piso = NULL,
                    ficha_inspecao_trimestral = NULL,
                    lacre = NULL,
                    pressao_manometro = NULL,
                    anel_identificacao = NULL,
                    pesagem_co2_semestral = NULL,
                    usuario = NULL,
                    peso_co2 = NULL,
                    comentarios = NULL,
                    foto = NULL
                WHERE inspecao_trimestral_nivel1 IS NOT NULL";

        if ($conn->query($sql) === TRUE) {
            if (function_exists('auditoria')) {
                auditoria('Limpeza de histórico de inspeção', null, $session['user_id'], $session['user_level'], 'Histórico de inspeção de nível 1 limpo com sucesso.');
            }
            return 'Location: historico_inspecao.php?message=' . urlencode('Histórico de inspeção limpo com sucesso');
        } else {
            error_log("Erro ao limpar o histórico de inspeção: " . ($conn->error ?? 'Unknown error'));
            return 'Location: historico_inspecao.php?message=' . urlencode('Erro ao limpar o histórico de inspeção.');
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] !== 'admin') {
    header('Location: index.php');
    exit();
}
    require_once __DIR__ . '/config/db_conexao.php';
    include 'auditoria.php';

    $redirect = limpar_historico_inspecao_logic($conn, $_SESSION, $_POST, $_SERVER);

    if ($conn) {
        $conn->close();
    }

    if ($redirect) {
        header($redirect);
        exit();
    }
}
?>
