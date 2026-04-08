<?php
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

    if (isset($conn)) {
        atualizar_dias_expiracao($conn);
        $conn->close();
    }
}
?>
