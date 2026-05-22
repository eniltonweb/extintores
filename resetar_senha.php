<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

// Verificar se o usuário está logado e se tem permissão para acessar esta página
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Erro de validação CSRF.";
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);

        // Prevenir SQL Injection usando prepared statements
        $sql = "UPDATE usuarios SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nova_senha, $id);

        if ($stmt->execute()) {
            $message = "Senha resetada com sucesso.";
        } else {
            $message = "Erro ao resetar senha: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
} else {
    header('Location: registrar_usuario.php');
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Resetar Senha</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include 'templates/header_controller.php'; ?>
    <div class="container mt-4">
        <h2 class="text-center">Resetar Senha</h2>

        <?php if (isset($message)) : ?>
            <div class="alert alert-info text-center"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" action="resetar_senha.php" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id ?? ''); ?>">
            <div class="form-group">
                <label for="nova_senha">Nova Senha:</label>
                <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Resetar Senha</button>
        </form>
    </div>
   <footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>