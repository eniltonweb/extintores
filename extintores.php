<?php
session_start();
include '../config/db_conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_level'], ['admin', 'fornecedor'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Inserir um novo extintor
    if (isset($_POST['inserir'])) {
        $codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $predio = filter_input(INPUT_POST, 'predio', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $atividade = filter_input(INPUT_POST, 'atividade', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $local_exato = filter_input(INPUT_POST, 'local_exato', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tipo_extintor = filter_input(INPUT_POST, 'tipo_extintor', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $carga = filter_input(INPUT_POST, 'carga', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $sql_inserir = "INSERT INTO bd_extintores (codigo, Predio, Atividade, Local_Exato, tip_extintor, carga) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_inserir = $conn->prepare($sql_inserir);
        $stmt_inserir->bind_param('ssssss', $codigo, $predio, $atividade, $local_exato, $tipo_extintor, $carga);

        if ($stmt_inserir->execute()) {
            $mensagem = "Extintor inserido com sucesso!";
        } else {
            $mensagem = "Erro ao inserir extintor: " . $stmt_inserir->error;
        }
    }

    // Remover um extintor
    if (isset($_POST['remover'])) {
        $codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $sql_remover = "DELETE FROM bd_extintores WHERE codigo = ?";
        $stmt_remover = $conn->prepare($sql_remover);
        $stmt_remover->bind_param('s', $codigo);

        if ($stmt_remover->execute()) {
            $mensagem = "Extintor removido com sucesso!";
        } else {
            $mensagem = "Erro ao remover extintor: " . $stmt_remover->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Liberação de Manutenções</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <link rel="manifest" href="../manifest.json">
    <style>
      .container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        h1, h2 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-top: 10px;
            font-weight: bold;
        }
        input, button {
            margin-top: 5px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-top: 20px;
            padding: 15px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        .message {
            text-align: center;
            margin-top: 20px;
            font-size: 18px;
            color: green;
        }
        .warning {
            text-align: center;
            margin-top: 20px;
            font-size: 18px;
            color: red;
        }
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
<nav class="navbar navbar-expand-lg navbar-dark">
    <a class="navbar-brand" href="index.php">Controle de Extintores</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="index.php">Inicio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="liberar_manutencao.php">Liberar Extintores</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="historico_inspecao.php">Historico Inspeções</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="historico_manutencao.php">Histórico Manutenções</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="filtro_vencimento.php">Vencimento Extintores</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="registrar_usuario.php">Gerenciar Usuários</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="auditoria_logs.php">Log Auditoria</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="exportar_dados.php">Exportar</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="sair.php">Sair</a>
            </li>
        </ul>
    </div>
</nav>
    <div class="container mt-4">
        <h1>Gerenciar Extintores</h1>
        <?php if (isset($mensagem)) : ?>
            <div class="message"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>
        <h2>Inserir Extintor</h2>
        <form method="POST" action="extintores.php">
            <label for="codigo">Código:</label>
            <input type="text" id="codigo" name="codigo" required>
            <label for="predio">Prédio:</label>
            <input type="text" id="predio" name="predio" required>
            <label for="atividade">Atividade:</label>
            <input type="text" id="atividade" name="atividade" required>
            <label for="local_exato">Local Exato:</label>
            <input type="text" id="local_exato" name="local_exato" required>
            <label for="tipo_extintor">Tipo de Extintor:</label>
            <input type="text" id="tipo_extintor" name="tipo_extintor" required>
            <label for="carga">Carga:</label>
            <input type="text" id="carga" name="carga" required>
            <button type="submit" name="inserir">Inserir Extintor</button>
        </form>

        <h2>Remover Extintor</h2>
        <form method="POST" action="extintores.php">
            <label for="codigo">Código:</label>
            <input type="text" id="codigo" name="codigo" required>
            <button type="submit" name="remover">Remover Extintor</button>
        </form>
    </div>
    <footer class="footer mt-4">
        <div class="container text-center">
            <p>&copy; 2024 Sistema de Controle de Extintores</p>
        </div>
    </footer>
</body>
</html>
<?php
$conn->close();
?>
