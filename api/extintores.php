<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

include 'auth_api.php';
$usuario = validarTokenAPI(); // Proteção ativa pelo porteiro da API

include '../../config/db_conexao.php';
$metodo = $_SERVER['REQUEST_METHOD'];

// ROTA GET: LISTAR OS EXTINTORES NO APLICATIVO
if ($metodo === 'GET') {
    // Consulta alinhada com as colunas reais do eniltonbd.sql
    $sql = "SELECT codigo, Predio, Atividade, Local_Exato FROM bd_extintores";
    $result = $conn->query($sql);
    
    $extintores = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) { 
            // Converte os dados do formato latin1 do MySQL para UTF-8 de forma limpa para o celular
            $extintores[] = [
                "codigo" => utf8_encode($row['codigo']),
                "Predio" => utf8_encode($row['Predio']),
                "Atividade" => utf8_encode($row['Atividade']),
                "Local_Exato" => utf8_encode($row['Local_Exato'])
            ]; 
        }
    }
    echo json_encode(["status" => "sucesso", "dados" => $extintores]);
    $conn->close();
    exit();
}

// ROTA POST: DAR BAIXA/REMOVER VIA CÂMERA DO CELULAR
if ($metodo === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);
    $acao = $dados['acao'] ?? '';
    $codigo = filter_var($dados['codigo'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($acao === 'remover' && !empty($codigo)) {
        $sql = "DELETE FROM bd_extintores WHERE codigo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $codigo);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "sucesso", "mensagem" => "Extintor " . $codigo . " baixado com sucesso!"]);
        } else {
            http_response_code(500); 
            echo json_encode(["status" => "erro", "mensagem" => "Erro ao processar baixa no banco."]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
}

http_response_code(400); 
echo json_encode(["status" => "erro", "mensagem" => "Operação inválida."]);
$conn->close();
?>