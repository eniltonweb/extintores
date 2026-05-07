<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'fornecedor') {
    header('Location: index.php');
    exit();
}

// Consultar extintores liberados para manutenção de nível 2
$sql_liberados_manutencao = "
    SELECT be.Predio, be.codigo, be.Atividade, be.Local_Exato
    FROM liberacao_manutencao lm
    JOIN bd_extintores be ON lm.codigo_extintor = be.codigo
    WHERE lm.liberado_para = 'fornecedor'
";
$result_liberados_manutencao = $conn->query($sql_liberados_manutencao);

$extintores_por_predio = [];
if ($result_liberados_manutencao) {
    while ($row = $result_liberados_manutencao->fetch_assoc()) {
        $extintores_por_predio[$row['Predio']][] = $row;
    }
}

$codigo = filter_input(INPUT_GET, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);
$predio_selecionado = filter_input(INPUT_GET, 'predio', FILTER_SANITIZE_SPECIAL_CHARS);
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
                <?php
                $predios_unicos = array_keys($extintores_por_predio);
                foreach ($predios_unicos as $predio):
                ?>
                    <option value="<?php echo htmlspecialchars($predio); ?>" <?php echo ($predio_selecionado == $predio) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($predio); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($predio_selecionado): ?>
            <form method="GET" action="formulario_manutencao.php">
                <input type="hidden" name="predio" value="<?php echo htmlspecialchars($predio_selecionado); ?>">
                <label for="codigo">Selecione o Extintor:</label>
                <select id="codigo" name="codigo">
                    <?php
                    $extintores_do_predio = $extintores_por_predio[$predio_selecionado] ?? [];
                    foreach ($extintores_do_predio as $row): ?>
                        <option value="<?php echo htmlspecialchars($row['codigo']); ?>">
                            <?php echo htmlspecialchars($row['codigo'] . " - " . $row['Atividade'] . " - " . $row['Local_Exato']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Carregar Extintor</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

<?php if ($show_form): ?>
    <form method="POST" action="salvar_manutencao.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
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
