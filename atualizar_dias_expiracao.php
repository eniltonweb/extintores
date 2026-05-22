<?php
session_start();

/**
 * Atualiza automaticamente a coluna 'dias_para_expirar_n2' com base na 'proxima_manutencao_n2'.
 *
 * @param mysqli $conn Conexão com o banco de dados.
 * @return bool True se a atualização foi bem-sucedida, false caso contrário.
 */
function atualizar_dias_expiracao($conn): bool {
    if (!$conn) {
        error_log("Falha na conexão com o banco de dados.");
        return false;
    }

    $sql = "UPDATE bd_extintores 
            SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE()) 
            WHERE proxima_manutencao_n2 IS NOT NULL";

    if ($conn->query($sql) === FALSE) {
        error_log("Erro ao atualizar a coluna: " . $conn->error);
        return false;
    }

    return true;
}

// Execução principal
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    require_once __DIR__ . '/config/db_conexao.php';

    // Define a mesma senha secreta para o Cron Job
    $cron_token_secreto = 'michelin_extintores_2026_secreto';

    // Verifica se é um admin logado OU se a URL tem o token secreto correto
    $is_admin = (isset($_SESSION['user_id']) && $_SESSION['user_level'] == 'admin');
    $is_cron  = (isset($_GET['token']) && $_GET['token'] === $cron_token_secreto);

    if (!$is_admin && !$is_cron) {
        // Se não for nenhum dos dois, bloqueia o acesso e exibe erro
        http_response_code(403);
        exit('Acesso negado.');
    }

    if (isset($conn)) {
        if (atualizar_dias_expiracao($conn)) {
            echo "Dias de expiracao atualizados com sucesso na base de dados.";
        } else {
            echo "Erro ao atualizar os dias de expiracao.";
        }
        $conn->close();
    }
}
?>