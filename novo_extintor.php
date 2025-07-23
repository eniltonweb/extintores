<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'bombeiro') {
    header('Location: index.php');
    exit();
}

// Obter prédios com extintores existentes
$sql_predios = "SELECT DISTINCT Predio FROM bd_extintores";
$result_predios = $conn->query($sql_predios);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Formulário Inspeção de Nivel 1</title>
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
            background-color: #28a745;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-top: 20px;
            padding: 15px;
        }

        button:hover {
            background-color: #218838;
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
                <a class="nav-link" href="formulario_inspecao.php">Inspeção de Nível 1</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="novo_extintor.php">Adicionar Novo Extintor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sair.php">Sair</a>
            </li>
        </ul>
    </div>
</nav>
</header>

<div class="container mt-4">
    <h2>Adicionar Novo Extintor</h2>
    <form method="POST" action="salvar_novo_extintor.php" enctype="multipart/form-data">
        <label for="novo_predio">Prédio:</label>
        <select id="novo_predio" name="novo_predio" required onchange="gerarCodigoNovoExtintor()">
            <option value="">Selecione um Prédio</option>
            <?php while ($row_predios = $result_predios->fetch_assoc()) : ?>
                <option value="<?php echo htmlspecialchars($row_predios['Predio']); ?>">
                    <?php echo htmlspecialchars($row_predios['Predio']); ?>
                </option>
            <?php endwhile; ?>
        </select><br>

        <label for="novo_codigo">Código do Novo Extintor:</label>
        <input type="text" id="novo_codigo" name="novo_codigo" readonly required><br>

        <label for="novo_local">Local Exato:</label>
        <input type="text" id="novo_local" name="novo_local" required><br>

        <label for="novo_tipo">Tipo do Extintor:</label>
        <select id="novo_tipo" name="novo_tipo" required>
            <option value="AP">AP</option>
            <option value="PQS">PQS</option>
            <option value="CO2">CO2</option>
            <option value="ESPUMA">ESPUMA</option>
            <option value="K">K</option>
        </select><br>

        <label for="novo_carga">Carga do Extintor:</label>
        <select id="novo_carga" name="novo_carga" required>
            <option value="2KG">2KG</option>
            <option value="4KG">4KG</option>
            <option value="6KG">6KG</option>
            <option value="10KG">10KG</option>
            <option value="25KG">25KG</option>
            <option value="27KG">27KG</option>
            <option value="45KG">45KG</option>
            <option value="10L">10L</option>
            <option value="75L">75L</option>
        </select><br>

        <button type="submit">Adicionar Extintor</button>
    </form>
</div>

<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

<script>
    function gerarCodigoNovoExtintor() {
        const predio = document.getElementById('novo_predio').value;
        if (predio) {
            // Fazer uma requisição AJAX para obter o próximo código do extintor
            fetch(`obter_proximo_codigo.php?predio=${predio}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('novo_codigo').value = data.proximo_codigo;
                })
                .catch(error => {
                    console.error('Erro ao obter o próximo código do extintor:', error);
                });
        } else {
            document.getElementById('novo_codigo').value = '';
        }
    }
</script>

</body>
</html>
<?php
$conn->close();
?>
