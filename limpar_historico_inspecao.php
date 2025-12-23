<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
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
    auditoria('Limpeza de histórico de inspeção', null, $_SESSION['user_id'], $_SESSION['user_level'], 'Histórico de inspeção de nível 1 limpo com sucesso.');
    header('Location: historico_inspecao.php?message=Histórico de inspeção limpo com sucesso');
    exit();
} else {
    echo "Erro ao limpar o histórico de inspeção: " . $conn->error;
}

$conn->close();
?>
