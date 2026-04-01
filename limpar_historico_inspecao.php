<?php
session_start();

// Validations before including db connection so that we don't have to connect to DB to reject invalid requests
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: historico_inspecao.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: historico_inspecao.php?message=' . urlencode('Erro: Token CSRF inválido.'));
    exit();
}

require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

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
    auditoria('Limpeza de histórico de inspeção', null, $_SESSION['user_id'], $_SESSION['user_level'], 'Histórico de inspeção de nível 1 limpo com sucesso.');
    header('Location: historico_inspecao.php?message=' . urlencode('Histórico de inspeção limpo com sucesso'));
    exit();
} else {
    error_log("Erro ao limpar o histórico de inspeção: " . $conn->error);
    header('Location: historico_inspecao.php?message=' . urlencode('Erro ao limpar o histórico de inspeção.'));
    exit();
}

$conn->close();
?>
