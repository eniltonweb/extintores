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
    <link rel="stylesheet" href="styles.css">
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
