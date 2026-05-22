<?php

if (!function_exists('process_login')) {
    function process_login($conn, $post, &$session, &$error) {
        // Verificar token CSRF
        if (!isset($post['csrf_token']) || empty($session['csrf_token']) || !hash_equals($session['csrf_token'], $post['csrf_token'])) {
            error_log("Erro CSRF detectado na tentativa de login.");
            $error = "Erro de validação de segurança. Por favor, tente novamente.";
            return false;
        }

        unset($session['csrf_token']); // Invalidar o token após o uso
        $session['csrf_token'] = bin2hex(random_bytes(32)); // Gerar novo para próxima tentativa

        $username = (string)filter_var($post['username'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        // Process password as a raw string and apply basic length limit to prevent DoS
        $password = (string)filter_var($post['password'] ?? '', FILTER_DEFAULT);

        if (empty($username) || empty($password)) {
            $error = "Preencha todos os campos.";
            return false;
        }

        if (strlen($username) > 255 || strlen($password) > 255) {
            $error = "Tamanho de entrada excedido.";
            return false;
        }

        $sql = "SELECT * FROM usuarios WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            // Log do erro no servidor e fallback amigável para o usuário
            error_log("Erro na preparação da consulta de login: " . $conn->error);
            $error = "Erro interno do servidor. Tente novamente mais tarde.";
            return false;
        }

        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "Credenciais inválidas.";
            $stmt->close();
            return false;
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            $error = "Credenciais inválidas.";
            $stmt->close();
            return false;
        }

        $session['user_id'] = $user['id'];
        $session['user_level'] = $user['nivel_acesso'];
        $session['user_name'] = $user['username'];
        $stmt->close();
        return true;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    session_start();
    session_regenerate_id(true);
    require_once __DIR__ . '/config/db_conexao.php';

    // Gerar token CSRF
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    $error = null;

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (process_login($conn, $_POST, $_SESSION, $error)) {
            header('Location: index.php');
            exit();
        }
        // If login failed, $session['csrf_token'] might have been updated
        $csrf_token = $_SESSION['csrf_token'] ?? $csrf_token;
    }

    $conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Acessar o Sistema</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--michelin-blue-dark), var(--michelin-blue));
            margin: 0;
            position: relative;
            overflow: hidden;
        }
        /* Efeito de fundo abstrato */
        body::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(252, 229, 0, 0.15) 0%, transparent 70%);
            top: -200px;
            left: -200px;
            border-radius: 50%;
            z-index: 0;
        }
        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            padding: 40px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--glass-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 420px;
            z-index: 1;
            text-align: center;
        }
        .login-container .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .login-container label {
            font-weight: 600;
            color: var(--michelin-blue-dark);
            margin-bottom: 8px;
        }
        .login-container .btn-primary {
            margin-top: 10px;
            padding: 12px;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .login-container h2 {
            font-size: 1.5rem;
            margin-top: 15px;
            margin-bottom: 25px;
        }
        .login-container p.error {
            color: #e53e3e;
            background-color: #fff5f5;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid #fc8181;
            margin-top: 15px;
            font-weight: 500;
        }
        .login-container img {
            max-width: 200px;
            margin-bottom: 10px;
            filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.1));
        }
        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: transparent !important;
            box-shadow: none !important;
            color: rgba(255,255,255,0.7) !important;
        }
    </style>
</head>
<body>
    <div class="login-container fade-in">
        <img src="img/michelin_logo.png" alt="Logo Michelin">
        <h2>Acesso ao Sistema</h2>
        <form method="POST" action="login.php">
			<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="username">Usuário:</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <?php if (isset($error)) : ?>
            <p class="error text-center"><?php echo $error; ?></p>
        <?php endif; ?>
    </div>
    
    <footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</body>
</html>
<?php
}
?>
