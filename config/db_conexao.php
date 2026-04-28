<?php
declare(strict_types=1);

// Carrega configuração do ambiente; valores de fallback são definidos apenas
// para desenvolvimento local. Em produção, configure DB_HOST, DB_USER, DB_PASS
// e DB_NAME no ambiente.
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'usuario';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'extintores';

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8');
} catch (mysqli_sql_exception $e) {
    error_log('Erro de conexão com o banco: ' . $e->getMessage());
    http_response_code(500);
    throw new Exception('Não foi possível conectar ao banco de dados.', 0, $e);
}

// Inicializa o token CSRF se a sessão estiver ativa
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('execute_stmt')) {
    // Helper function to execute prepared statements with dynamic params
    function execute_stmt($conn, $sql, $types, $params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;

        if (!empty($params)) {
            $bind_params = [];
            foreach ($params as $key => $value) {
                $bind_params[$key] = &$params[$key];
            }
            $stmt->bind_param($types, ...$bind_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
}
