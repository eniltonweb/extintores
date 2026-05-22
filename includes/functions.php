<?php
// functions.php

/**
 * Sanitiza a entrada do usuário
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Valida o código de barras do extintor
 */
function validateBarcode($barcode) {
    return preg_match('/^[a-zA-Z0-9\-]+$/', $barcode);
}

/**
 * Obtém os detalhes do extintor do banco de dados
 */
function getExtintorDetails($conn, $codigo) {
    $sql = "SELECT e.*, 
                   e.usuario AS usuario_inspecao_nivel1,
                   hm2.usuario_id AS usuario_manutencao_nivel2
            FROM bd_extintores e
            LEFT JOIN historico_manutencao hm1 ON e.id = hm1.extintor_id AND hm1.tipo_manutencao = 'nivel_1'
            LEFT JOIN usuarios u ON hm1.usuario_id = u.id
            LEFT JOIN historico_manutencao hm2 ON e.id = hm2.extintor_id AND hm2.tipo_manutencao = 'nivel_2'
            WHERE e.codigo = ?
            ORDER BY hm1.data_manutencao DESC, hm2.data_manutencao DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}

/**
 * Verifica se o usuário pode realizar inspeção
 */
function canPerformInspecao($user_level, $extintor) {
    // Implemente a lógica de verificação aqui
    return $user_level == 'bombeiro';
}

/**
 * Verifica se o usuário pode realizar manutenção
 */
function canPerformManutencao($user_level, $extintor) {
    // Implemente a lógica de verificação aqui
    return $user_level == 'fornecedor';
}

/**
 * Gera um token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica o token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Configura os cabeçalhos de segurança
 */
function setSecurityHeaders() {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' http://www.enilton.com.br; script-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://maxcdn.bootstrapcdn.com; style-src 'self' https://maxcdn.bootstrapcdn.com 'unsafe-inline';");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}

/**
 * Retorna o template de cabeçalho correto com base no nível do usuário
 */
function getHeaderTemplate($user_level) {
    switch ($user_level) {
        case 'admin':
            return '../templates/header1.php';
        case 'bombeiro':
            return '../templates/header2.php';
        case 'fornecedor':
            return '../templates/header3.php';
        default:
            return '../templates/header.php';
    }
}

/**
 * Registra uma ação de auditoria
 */
function logAuditAction($user_id, $action, $details) {
    global $conn;
    $sql = "INSERT INTO audit_log (user_id, action, details, timestamp) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $user_id, $action, $details);
    $stmt->execute();
}