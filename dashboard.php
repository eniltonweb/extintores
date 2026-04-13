<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Consultar dados consolidados do dashboard para reduzir o número de requisições ao banco
$sql_dashboard = "
    SELECT 'manutencao' AS source, tipo_manutencao AS label, COUNT(*) AS total FROM historico_manutencao GROUP BY tipo_manutencao
    UNION ALL
    SELECT 'proxima' AS source, proxima_manutencao_n2 AS label, COUNT(*) AS total FROM bd_extintores WHERE proxima_manutencao_n2 IS NOT NULL GROUP BY proxima_manutencao_n2
    UNION ALL
    SELECT 'extintores' AS source, tip_extintor AS label, COUNT(*) AS total FROM bd_extintores GROUP BY tip_extintor
";
$result_dashboard = $conn->query($sql_dashboard);

$manutencoes = [];
$proximas_manutencoes = [];
$extintores = [];

if ($result_dashboard !== false) {
    while ($row = $result_dashboard->fetch_assoc()) {
        if ($row['source'] === 'manutencao') {
            $manutencoes[] = ['tipo_manutencao' => $row['label'], 'total' => $row['total']];
        } elseif ($row['source'] === 'proxima') {
            $proximas_manutencoes[] = ['proxima_manutencao_n2' => $row['label'], 'total' => $row['total']];
        } elseif ($row['source'] === 'extintores') {
            $extintores[] = ['tip_extintor' => $row['label'], 'total' => $row['total']];
        }
    }
}

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

<div class="container">
    <h2 class="text-center">GRÁFICO DAS MANUTENÇÕES</h2>
    <div class="row">
        <div class="col-md-6 chart-container">
            <h3>Manutenções Realizadas</h3>
            <canvas id="manutencaoChart"></canvas>
        </div>
        <div class="col-md-6 chart-container">
            <h3>Próximas Manutenções</h3>
            <canvas id="proximasChart"></canvas>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 chart-container">
            <h3>Distribuição dos Tipos de Extintores</h3>
            <canvas id="extintoresChart"></canvas>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>

<script>
    // Dados de manutenções realizadas
    var manutencaoLabels = <?php echo json_encode(array_column($manutencoes, 'tipo_manutencao')); ?>;
    var manutencaoData = <?php echo json_encode(array_column($manutencoes, 'total')); ?>;

    var ctxManutencao = document.getElementById('manutencaoChart').getContext('2d');
    var manutencaoChart = new Chart(ctxManutencao, {
        type: 'bar',
        data: {
            labels: manutencaoLabels,
            datasets: [{
                label: 'Manutenções Realizadas',
                data: manutencaoData,
                backgroundColor: ['rgba(75, 192, 192, 0.2)', 'rgba(255, 159, 64, 0.2)'],
                borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 159, 64, 1)'],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Dados de próximas manutenções
    var proximasLabels = <?php echo json_encode(array_column($proximas_manutencoes, 'proxima_manutencao_n2')); ?>;
    var proximasData = <?php echo json_encode(array_column($proximas_manutencoes, 'total')); ?>;

    var ctxProximas = document.getElementById('proximasChart').getContext('2d');
    var proximasChart = new Chart(ctxProximas, {
        type: 'line',
        data: {
            labels: proximasLabels,
            datasets: [{
                label: 'Próximas Manutenções',
                data: proximasData,
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1,
                fill: true
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Dados de distribuição dos tipos de extintores
    var extintoresLabels = <?php echo json_encode(array_column($extintores, 'tip_extintor')); ?>;
    var extintoresData = <?php echo json_encode(array_column($extintores, 'total')); ?>;

    var ctxExtintores = document.getElementById('extintoresChart').getContext('2d');
    var extintoresChart = new Chart(ctxExtintores, {
        type: 'pie',
        data: {
            labels: extintoresLabels,
            datasets: [{
                label: 'Distribuição dos Tipos de Extintores',
                data: extintoresData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true
        }
    });
</script>
</body>
</html>