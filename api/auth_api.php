<?php
// public/api/auth_api.php

function validarTokenAPI() {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$authHeader) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["status" => "erro", "mensagem" => "Acesso negado. Token de autorização ausente."]);
        exit();
    }

    $token = str_replace('Bearer ', '', $authHeader);
    $secret_key = getenv('JWT_SECRET') ?: 'SuaChaveSecretaProvisoria123';

    $partes = explode('.', $token);
    if (count($partes) !== 3) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["status" => "erro", "mensagem" => "Token inválido ou mal formatado."]);
        exit();
    }

    $header = $partes[0];
    $payload = $partes[1];
    $assinatura_enviada = $partes[2];

    $assinatura_valida = hash_hmac('sha256', $header . "." . $payload, $secret_key, true);
    $assinatura_esperada = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($assinatura_valida));

    if (!hash_equals($assinatura_esperada, $assinatura_enviada)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["status" => "erro", "mensagem" => "Token inválido ou assinatura digital corrompida."]);
        exit();
    }

    $dados_usuario = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
    
    if (isset($dados_usuario['exp']) && $dados_usuario['exp'] < time()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(["status" => "erro", "mensagem" => "A sua sessão expirou. Por favor, faça login novamente no aplicativo."]);
        exit();
    }

    return $dados_usuario;
}
?>