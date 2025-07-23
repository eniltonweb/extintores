<?php
include '../config/db_conexao.php'; // Inclua seu arquivo de conexão com o banco de dados

if ($conn) {
    // Atualizar automaticamente a coluna 'dias_para_expirar_n2' com base na 'proxima_manutencao_n2'
    $sql = "UPDATE bd_extintores 
            SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE()) 
            WHERE proxima_manutencao_n2 IS NOT NULL";

    if ($conn->query($sql) === FALSE) {
        // Registrar o erro no log ou exibir para debugging
        error_log("Erro ao atualizar a coluna: " . $conn->error);
    }

    // Fechar a conexão
    $conn->close();
} else {
    error_log("Falha na conexão com o banco de dados.");
}
?>
