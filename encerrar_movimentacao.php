<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

// Proteção de acesso
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Não autorizado.']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $extintor_substituto_id = intval($_POST['extintor_substituto_id']);
    $user_id = $_SESSION['user_id'];
    $user_level = $_SESSION['user_level'];

    $conn->begin_transaction();

    try {
        // 1. Procurar a movimentação ativa deste extintor reserva
        $stmt = $conn->prepare("SELECT id, extintor_ausente_id, local_original_substituto FROM bd_historico_movimentacao WHERE extintor_substituto_id = ? AND status_movimentacao = 'Ativa' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $extintor_substituto_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            throw new Exception("Não foi encontrada nenhuma cobertura ativa para este extintor.");
        }
        
        $movimentacao = $res->fetch_assoc();
        $mov_id = $movimentacao['id'];
        $local_original = $movimentacao['local_original_substituto'];
        $ausente_id = $movimentacao['extintor_ausente_id'];

        // 2. Marcar a movimentação como 'Finalizada' e carimbar a data de retorno
        $stmtUpdateHist = $conn->prepare("UPDATE bd_historico_movimentacao SET status_movimentacao = 'Finalizada', data_retorno = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUpdateHist->bind_param("i", $mov_id);
        $stmtUpdateHist->execute();

        // 3. Devolver o extintor reserva ao seu local original e remover o estado de cobertura
        $stmtUpdateExt = $conn->prepare("UPDATE bd_extintores SET Local_Exato = ?, cobertura = 0 WHERE id = ?");
        $stmtUpdateExt->bind_param("si", $local_original, $extintor_substituto_id);
        $stmtUpdateExt->execute();

        // 4. Registar no Log de Auditoria
        $acao = "Encerramento de Cobertura (Devolução)";
        $detalhes = "Extintor ID $extintor_substituto_id devolvido ao local original: $local_original. Cobertura do extintor ID $ausente_id finalizada.";
        $stmtLog = $conn->prepare("INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, detalhes) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->bind_param("issis", $user_id, $user_level, $acao, $extintor_substituto_id, $detalhes);
        $stmtLog->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Extintor devolvido ao Quartel/Estoque e cobertura encerrada com sucesso!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar: ' . $e->getMessage()]);
    }
}
?>