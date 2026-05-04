<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'bombeiro') {
    http_response_code(403);
    echo json_encode(['proximo_codigo' => '', 'erro' => 'Não autorizado']);
    exit();
}

$predio = filter_input(INPUT_GET, 'predio', FILTER_SANITIZE_SPECIAL_CHARS);

if ($predio) {
    // Obter o último código do prédio especificado
    $sql = "SELECT codigo FROM bd_extintores WHERE Predio = ? ORDER BY codigo DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $predio);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ultimo_codigo = $row['codigo'];

        // Extrair o número do último código e incrementar
        $partes_codigo = explode("-", $ultimo_codigo);
        if (count($partes_codigo) === 2 && is_numeric($partes_codigo[1])) {
            $novo_numero = intval($partes_codigo[1]) + 1;
            $proximo_codigo = $predio . '-' . $novo_numero;
        } else {
            // Caso o formato do código seja inesperado, começar do 1
            $proximo_codigo = $predio . '-1';
        }
    } else {
        // Caso não haja extintores para o prédio, começar do 1
        $proximo_codigo = $predio . '-1';
    }

    echo json_encode(['proximo_codigo' => $proximo_codigo]);
} else {
    echo json_encode(['proximo_codigo' => '']);
}

$conn->close();
?>
