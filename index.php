<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

// Configurações de segurança da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log'); // Certifique-se de definir o caminho correto para o arquivo de log

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_level = $_SESSION['user_level'];

// Consultar o nome do usuário no banco de dados
$sql_user = "SELECT username FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$username = htmlspecialchars($user['username']);

$stmt_user->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sistema de Controle e Manutenção de Extintores</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

</head>
<body>
    <?php
    // Incluir o cabeçalho dinâmico centralizado
    include 'templates/header_controller.php';
    ?>
    <div class="container mt-5 fade-in">
        <div class="card border-0 text-center py-5">
            <h2 class="font-weight-bold mb-3" style="color: var(--michelin-blue-dark);">Bem-vindo ao Sistema de Controle de Extintores</h2>
            <p class="lead text-muted mb-4">Gerencie as inspeções e manutenções com eficiência e segurança.</p>
            <div class="p-3 mb-2 bg-light rounded shadow-sm d-inline-block">
                <p id="user-greeting" class="m-0 font-weight-bold" style="color: var(--michelin-blue); font-size: 1.2rem;"></p>
            </div>
            
            <div class="mt-5 row justify-content-center">
                <div class="col-md-4 mb-3">
                    <div class="p-4 border rounded shadow-sm" style="background: rgba(39, 80, 155, 0.05);">
                        <h4 style="color: var(--michelin-blue);">Inspeções</h4>
                        <p class="text-muted small">Acesso rápido para registro de campo.</p>
                        <a href="formulario_inspecao.php" class="btn btn-primary btn-sm mt-2">Acessar</a>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-4 border rounded shadow-sm" style="background: rgba(252, 229, 0, 0.1);">
                        <h4 style="color: var(--michelin-blue-dark);">Dashboard</h4>
                        <p class="text-muted small">Visualize métricas e andamento.</p>
                        <a href="dashboard.php" class="btn btn-primary btn-sm mt-2">Visualizar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadUserData();
        });

        function loadUserData() {
            const username = "<?php echo $username; ?>";
            const userLevel = "<?php echo htmlspecialchars($user_level); ?>";
            document.getElementById('user-greeting').textContent = getUserGreeting(userLevel, username);
        }

        function getUserGreeting(userLevel, username) {
            switch(userLevel) {
                case 'admin':
                    return `Administrador ${username}, você tem acesso completo ao sistema.`;
                case 'bombeiro':
                    return `Bombeiro ${username}, você pode realizar inspeções de nível 1.`;
                case 'fornecedor':
                    return `Fornecedor ${username}, você pode realizar manutenções de nível 2.`;
                default:
                    return `Bem-vindo ${username}, utilize o menu acima para navegar pelo sistema.`;
            }
        }
    </script>
</body>
</html>
