<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

// Proteção de acesso
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Não autorizado.']));
}

$user_id = $_SESSION['user_id'];
$user_level = $_SESSION['user_level'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $extintor_substituto = intval($_POST['extintor_substituto_id']);
    $extintor_ausente = intval($_POST['extintor_ausente_id']);
    $motivo = $conn->real_escape_string($_POST['motivo']);

    $conn->begin_transaction();

    try {
        // 1. Obter dados do extintor que foi para manutenção (o ponto que precisa ser coberto)
        $stmt = $conn->prepare("SELECT Local_Exato, Predio FROM bd_extintores WHERE id = ?");
        $stmt->bind_param("i", $extintor_ausente);
        $stmt->execute();
        $res = $stmt->get_result();
        $dadosAusente = $res->fetch_assoc();
        $localDestino = $dadosAusente['Local_Exato'];
        
        // 2. Obter dados do extintor reserva (substituto)
        $stmt = $conn->prepare("SELECT Local_Exato FROM bd_extintores WHERE id = ?");
        $stmt->bind_param("i", $extintor_substituto);
        $stmt->execute();
        $res = $stmt->get_result();
        $dadosSubstituto = $res->fetch_assoc();
        $localOriginal = $dadosSubstituto['Local_Exato'];

        // 3. Registrar o histórico na nova tabela
        $stmtHistorico = $conn->prepare("INSERT INTO bd_historico_movimentacao (extintor_substituto_id, extintor_ausente_id, local_original_substituto, novo_local_provsorio, usuario_id, motivo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtHistorico->bind_param("iissss", $extintor_substituto, $extintor_ausente, $localOriginal, $localDestino, $user_id, $motivo);
        $stmtHistorico->execute();

        // 4. Atualizar o cadastro principal do extintor substituto (setar como cobertura e mudar o local provisoriamente)
        $stmtUpdate = $conn->prepare("UPDATE bd_extintores SET Local_Exato = ?, cobertura = 1 WHERE id = ?");
        $stmtUpdate->bind_param("si", $localDestino, $extintor_substituto);
        $stmtUpdate->execute();

        // 5. Registrar log de auditoria
        $acao = "Movimentação/Cobertura: Extintor ID $extintor_substituto cobrindo ID $extintor_ausente";
        $stmtLog = $conn->prepare("INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, detalhes) VALUES (?, ?, ?, ?, ?)");
        $detalhes = "Local anterior: $localOriginal -> Novo local provisório: $localDestino. Motivo: $motivo";
        $stmtLog->bind_param("issis", $user_id, $user_level, $acao, $extintor_substituto, $detalhes);
        $stmtLog->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Movimentação registrada com sucesso.']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar: ' . $e->getMessage()]);
    }
}
?>