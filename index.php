<?php
session_start();
include '../config/db_conexao.php';

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
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff;
            color: #1b1e21;
        }
        .navbar {
            background: linear-gradient(45deg, #001f3f, #27509b);
            border-bottom: 3px solid #fce500;
        }
        .navbar-brand {
            font-size: 1.5rem;
            color: #fce500 !important;
        }
        .nav-link {
            color: #ffffff !important;
            transition: color 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        .nav-link:hover {
            color: #fce500 !important;
            transform: scale(1.1);
        }
        .navbar-toggler {
            border-color: #fce500;
        }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba%25255, 229, 0, 0.7%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
        }
        .dropdown-menu {
            background-color: #27509b;
            border: none;
        }
        .dropdown-item {
            color: #ffffff;
            transition: background-color 0.3s ease-in-out;
        }
        .dropdown-item:hover {
            background-color: #fce500;
            color: #000000;
        }
        .nav-item {
            margin-right: 10px;
        }
        .navbar-nav.ml-auto {
            margin-left: auto;
        }
        .navbar-collapse {
            flex-grow: 1;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <?php
    // Incluir o cabeçalho correto com base no nível de usuário
    if ($user_level == 'admin') {
        include '../templates/header1.php';
    } elseif ($user_level == 'bombeiro') {
        include '../templates/header2.php';
    } elseif ($user_level == 'fornecedor') {
        include '../templates/header3.php';
    } else {
        include '../templates/header.php';
    }
    ?>
    <div class="container mt-4">
        <h2>Bem-vindo ao Sistema de Controle e Manutenção de Extintores</h2>
        <p>Utilize o menu acima para navegar pelo sistema.</p>
        <p id="user-greeting"></p>
    </div>
    <footer class="footer">
        <div class="container text-center">
            <p>&copy; 2024 Sistema de Controle de Extintores</p>
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
