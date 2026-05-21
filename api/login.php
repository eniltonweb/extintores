<?php
// Configurações de CORS para permitir acesso do aplicativo móvel
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');

// Conexão com o banco de dados externa
include '../../config/db_conexao.php';

$dados = json_decode(file_get_contents('php://input'), true);
$username = filter_var($dados['username'] ?? '', FILTER_SANITIZE_STRING);
$password = $dados['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "erro", "mensagem" => "Por favor, preencha o usuário e a senha."]);
    exit();
}

// Busca alinhada com as tabelas reais do banco eniltonbd.sql
$sql = "SELECT id, username, password, nivel_acesso FROM usuarios WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['password'])) {
        
        // Pega o nível real, remove espaços e transforma tudo em minúsculo
        $nivel_real = trim(strtolower($user['nivel_acesso'] ?? 'bombeiro'));

        $secret_key = getenv('JWT_SECRET') ?: 'SuaChaveSecretaProvisoria123';
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => (int)$user['id'],
            'username' => utf8_encode($user['username']),
            'nivel_acesso' => $nivel_real, // Nível higienizado indo para o Token
            'exp' => time() + 86400
        ]);

        $b64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $b64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $b64Header . "." . $b64Payload, $secret_key, true);
        $b64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $token = $b64Header . "." . $b64Payload . "." . $b64Signature;

        echo json_encode(["status" => "sucesso", "token" => $token]);
    } else {
        http_response_code(401); 
        echo json_encode(["status" => "erro", "mensagem" => "Senha incorreta."]);
    }
} else {
    http_response_code(401); 
    echo json_encode(["status" => "erro", "mensagem" => "Usuário não encontrado."]);
}
$stmt->close(); 
$conn->close();
?>