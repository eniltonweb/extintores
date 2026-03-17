<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'fornecedor') {
    header('Location: index.php');
    exit();
}

// Capturar dados do formulário
$codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);
$cobertura = isset($_POST['cobertura']) && $_POST['cobertura'] == '1' ? 1 : 0;
$manutencao_n2 = isset($_POST['manutencao_n2']) && $_POST['manutencao_n2'] == '1' ? 1 : 0;

// Garantir que o código não esteja vazio
if (empty($codigo)) {
    die("Erro: Código do extintor não especificado.");
}

// Capturar o nome do usuário logado a partir da sessão
$username = $_SESSION['user_name'] ?? null;

// Verificar se o username foi recuperado corretamente
if (empty($username)) {
    die("Erro ao capturar o usuário logado.");
}

// Variável para armazenar mensagens de sucesso ou erro
$message = '';

// Se o checkbox de manutenção de nível 2 foi marcado
if ($manutencao_n2) {
    // Definir data de manutenção atual e próxima manutenção para um ano depois
    $data_manutencao_n2 = date('Y-m-d');
    $data_proxima_manutencao_n2 = date('Y-m-d', strtotime('+1 year'));

    // Atualizar o extintor específico com as informações de manutenção e cobertura
    $sql_update_manutencao = "UPDATE bd_extintores 
                   SET manutencao_n2 = ?, proxima_manutencao_n2 = ?, usuario_n2 = ?, cobertura = ?, updated_at = NOW() 
                   WHERE codigo = ? LIMIT 1";
    $stmt_manutencao = $conn->prepare($sql_update_manutencao);

    // Verificar se a preparação da consulta foi bem-sucedida
    if ($stmt_manutencao === false) {
        die("Erro ao preparar consulta para atualizar manutenção: " . $conn->error);
    }

    $stmt_manutencao->bind_param("sssis", $data_manutencao_n2, $data_proxima_manutencao_n2, $username, $cobertura, $codigo);

    // Executar e verificar se a consulta foi bem-sucedida
    if ($stmt_manutencao->execute()) {
        // Sucesso ao salvar
        $message .= "Manutenção e próxima manutenção registradas com sucesso!";
    } else {
        // Erro ao salvar
        $message .= "Erro ao atualizar a manutenção: " . $stmt_manutencao->error;
    }
    $stmt_manutencao->close();
} 

// Se o checkbox de cobertura foi marcado, atualizar apenas a cobertura
if (!$manutencao_n2 && $cobertura) {
    // Atualizar apenas a cobertura do extintor específico
    $sql_update_cobertura = "UPDATE bd_extintores 
                   SET cobertura = ?, usuario_n2 = ?, updated_at = NOW() 
                   WHERE codigo = ? LIMIT 1";
    $stmt_cobertura = $conn->prepare($sql_update_cobertura);

    // Verificar se a preparação da consulta foi bem-sucedida
    if ($stmt_cobertura === false) {
        die("Erro ao preparar consulta para atualizar cobertura: " . $conn->error);
    }

    $stmt_cobertura->bind_param("iss", $cobertura, $username, $codigo);

    // Executar e verificar se a consulta foi bem-sucedida
    if ($stmt_cobertura->execute()) {
        // Sucesso ao salvar
        $message .= " Cobertura atualizada com sucesso!";
    } else {
        // Erro ao salvar
        $message .= " Erro ao atualizar a cobertura: " . $stmt_cobertura->error;
    }
    $stmt_cobertura->close();
}

// Atualizar automaticamente os dias para expirar apenas do extintor específico
$sql_update_dias = "UPDATE bd_extintores 
                    SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE())
                    WHERE codigo = ? LIMIT 1";
$stmt_dias = $conn->prepare($sql_update_dias);

if ($stmt_dias === false) {
    die("Erro ao preparar consulta para atualizar dias para expirar: " . $conn->error);
}

$stmt_dias->bind_param("s", $codigo);

if ($stmt_dias->execute()) {
    // Sucesso ao atualizar os dias
    $message .= " Dias para expirar atualizados com sucesso!";
} else {
    // Erro ao atualizar os dias
    $message .= " Erro ao atualizar os dias para expirar: " . $stmt_dias->error;
}
$stmt_dias->close();

// Fechar a conexão
$conn->close();

// Redirecionar para a página anterior com uma mensagem
header("Location: formulario_manutencao.php?message=" . urlencode($message));
exit();
?>
