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

        $username = (string)filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
        // Process password as a raw string and apply basic length limit to prevent DoS
        $password = (string)filter_input(INPUT_POST, 'password', FILTER_DEFAULT);

        if (empty($username) || empty($password)) {
            $error = "Preencha todos os campos.";
        } elseif (strlen($username) > 255 || strlen($password) > 255) {
            $error = "Tamanho de entrada excedido.";
        } else {
            $sql = "SELECT * FROM usuarios WHERE username = ?";
            $stmt = $conn->prepare($sql);

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

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $session['user_id'] = $user['id'];
                $session['user_level'] = $user['nivel_acesso'];
                $session['user_name'] = $user['username'];
                $stmt->close();
                return true;
            } else {
                $error = "Credenciais inválidas.";
            }
        } else {
            $error = "Credenciais inválidas.";
        }
        $stmt->close();
        return false;
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
            height: 100vh;
            background-color: #f0f0f0;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-container .form-group {
            margin-bottom: 15px;
        }
        .login-container .btn-primary {
            background-color: #27509b;
            border: none;
        }
        .login-container .btn-primary:hover {
            background-color: #fce500;
            color: #000;
        }
        .login-container p.error {
            color: red;
            margin-top: 10px;
        }
        .login-container img {
            max-width: 360px;
            margin-bottom: 0px;
        }
    </style>
</head>
<body>
    <div class="login-container text-center">
        <img src="img/michelin_logo.png" alt="Logo">
        <h2 class="text-center">ENTRAR NO SISTEMA</h2>
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
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</body>
</html>
<?php
}
?>
