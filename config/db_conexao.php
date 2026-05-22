<?php
declare(strict_types=1);

// Configuração direta de conexão ao banco de dados
$dbHost = 'eniltonbd.mysql.dbaas.com.br';
$dbUser = 'eniltonbd';
$dbPass = 'Nil2024#';
$dbName = 'eniltonbd';        

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    
    // Verifica se houve erro na conexão
    if ($conn->connect_error) {
        throw new mysqli_sql_exception('Falha na conexão: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8');
} catch (mysqli_sql_exception $e) {
    error_log('Erro de conexão com o banco: ' . $e->getMessage());
    http_response_code(500);
    // Em caso de erro persistente, descomente a linha abaixo para ver o erro no navegador:
    // die('ERRO DE CONEXÃO: ' . $e->getMessage());
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
?>