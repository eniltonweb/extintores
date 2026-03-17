<?php
session_start();

// Validations before including db connection so that we don't have to connect to DB to reject invalid requests
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: historico_manutencao.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: historico_manutencao.php?message=Erro:+Token+CSRF+inválido.');
    exit();
}

// Limpar os campos de manutenção na tabela bd_extintores
$sql = "
    UPDATE bd_extintores
    SET manutencao_n2 = NULL, proxima_manutencao_n2 = NULL, dias_para_expirar_n2 = NULL, cobertura = NULL
    WHERE manutencao_n2 IS NOT NULL
";
if ($conn->query($sql) === TRUE) {
    auditoria('Limpeza de histórico', null, $_SESSION['user_id'], $_SESSION['user_level'], 'O histórico de manutenções foi limpo.');
    header('Location: historico_manutencao.php?message=Histórico de manutenções limpo com sucesso');
    exit();
} else {
    echo "Erro ao limpar histórico: " . $conn->error;
}

$conn->close();
?>
