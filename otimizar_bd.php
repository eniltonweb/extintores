<?php
require_once __DIR__ . '/config/db_conexao.php';

function createIndex($conn, $table, $indexName, $column) {
    // Check if index exists
    $result = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
    if ($result && $result->num_rows == 0) {
        $sql = "CREATE INDEX `$indexName` ON `$table` (`$column`)";
        if ($conn->query($sql) === TRUE) {
            echo "Index $indexName created on $table($column).\n";
        } else {
            echo "Error creating index $indexName: " . $conn->error . "\n";
        }
    } else {
        echo "Index $indexName already exists on $table.\n";
    }
}

// Otimizações para bd_extintores
createIndex($conn, 'bd_extintores', 'idx_codigo', 'codigo');
createIndex($conn, 'bd_extintores', 'idx_predio', 'Predio');
createIndex($conn, 'bd_extintores', 'idx_status_aprovacao', 'status_aprovacao');
createIndex($conn, 'bd_extintores', 'idx_inspecao_nivel1', 'inspecao_trimestral_nivel1');

// Se houver tabela liberacao_manutencao
createIndex($conn, 'liberacao_manutencao', 'idx_codigo_extintor', 'codigo_extintor');

// Se houver auditoria_logs
createIndex($conn, 'auditoria_logs', 'idx_usuario_id', 'usuario_id');
createIndex($conn, 'auditoria_logs', 'idx_data_hora', 'data_hora');

$conn->close();
echo "Database optimization finished.\n";
?>
