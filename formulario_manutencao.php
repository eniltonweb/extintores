<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'fornecedor') {
    header('Location: index.php');
    exit();
}

// Consultar extintores liberados para manutenção de nível 2
$sql_liberados_manutencao = "
    SELECT DISTINCT be.Predio
    FROM liberacao_manutencao lm
    JOIN bd_extintores be ON lm.codigo_extintor = be.codigo
    WHERE lm.liberado_para = 'fornecedor'
";
$result_liberados_manutencao = $conn->query($sql_liberados_manutencao);

$codigo = filter_input(INPUT_GET, 'codigo', FILTER_SANITIZE_STRING);
$predio_selecionado = filter_input(INPUT_GET, 'predio', FILTER_SANITIZE_STRING);
$show_form = false;

if ($codigo) {
    $sql = "SELECT * FROM bd_extintores WHERE codigo = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $show_form = true;
    } else {
        echo "<div class='warning'>Nenhum extintor encontrado com o código fornecido.</div>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sistema de Controle e Manutenção de Extintores</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

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

        select, input, textarea, button {
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

        .warning {
            max-width: 800px;
            margin: 50px auto;
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
        }

        @media (max-width: 600px) {
            .container {
                width: 90%;
            }
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
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(255, 229, 0, 0.7)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
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
<header>
<nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="index.php">Controle de Extintores</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="formulario_manutencao.php">Manutenção de Nível 2</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="filtro_vencimento.php">Vencimento Extintores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="sair.php">Sair</a>
                </li>
            </ul>
        </div>
    </nav>
</header>
<div class="container">
    <?php if (isset($_GET['message'])) : ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$codigo): ?>
        <form method="GET" action="formulario_manutencao.php">
            <label for="predio">Filtrar por Prédio:</label>
            <select id="predio" name="predio" onchange="this.form.submit()">
                <option value="">Selecione o Prédio</option>
                <?php while ($row = $result_liberados_manutencao->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($row['Predio']); ?>" <?php echo ($predio_selecionado == $row['Predio']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['Predio']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>

        <?php if ($predio_selecionado): ?>
            <form method="GET" action="formulario_manutencao.php">
                <input type="hidden" name="predio" value="<?php echo htmlspecialchars($predio_selecionado); ?>">
                <label for="codigo">Selecione o Extintor:</label>
                <select id="codigo" name="codigo">
                    <?php
                    $sql_extintores = "SELECT * FROM bd_extintores WHERE Predio = ?";
                    $stmt_extintores = $conn->prepare($sql_extintores);
                    $stmt_extintores->bind_param("s", $predio_selecionado);
                    $stmt_extintores->execute();
                    $result_extintores = $stmt_extintores->get_result();
                    while ($row = $result_extintores->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($row['codigo']); ?>">
                            <?php echo htmlspecialchars($row['codigo'] . " - " . $row['Atividade'] . " - " . $row['Local_Exato']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit">Carregar Extintor</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

<?php if ($show_form): ?>
    <form method="POST" action="salvar_manutencao.php">
        <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="manutencao_n2" name="manutencao_n2" value="1">
            <label class="form-check-label" for="manutencao_n2">Marcar Manutenção de Nível 2 Realizada</label>
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="cobertura" name="cobertura" value="1">
            <label class="form-check-label" for="cobertura">Marcar como Cobertura</label>
        </div>
        <button type="submit">Salvar Manutenção</button>
    </form>
<?php endif; ?>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
