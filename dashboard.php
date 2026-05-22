<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/dashboard_data.json';
$cacheTime = 3600; // 1 hour cache

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$dashboard_data = null;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $content = file_get_contents($cacheFile);
    if ($content !== false) {
        $dashboard_data = json_decode($content, true);
    }
}

if (!is_array($dashboard_data)) {
    require_once __DIR__ . '/config/db_conexao.php';

    // Consultar dados agregados em uma única query para melhor performance
    $sql_consolidado = "
        SELECT 'manutencao' AS source, tipo_manutencao AS label, COUNT(*) AS total
        FROM historico_manutencao
        GROUP BY tipo_manutencao

        UNION ALL

        SELECT 'proxima' AS source, proxima_manutencao_n2 AS label, COUNT(*) AS total
        FROM bd_extintores
        WHERE proxima_manutencao_n2 IS NOT NULL
        GROUP BY proxima_manutencao_n2

        UNION ALL

        SELECT 'extintores' AS source, tip_extintor AS label, COUNT(*) AS total
        FROM bd_extintores
        GROUP BY tip_extintor
    ";

    $result = $conn->query($sql_consolidado);

    $manutencoes = [];
    $proximas_manutencoes = [];
    $extintores = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            switch ($row['source']) {
                case 'manutencao':
                    $manutencoes[] = ['tipo_manutencao' => $row['label'], 'total' => $row['total']];
                    break;
                case 'proxima':
                    $proximas_manutencoes[] = ['proxima_manutencao_n2' => $row['label'], 'total' => $row['total']];
                    break;
                case 'extintores':
                    $extintores[] = ['tip_extintor' => $row['label'], 'total' => $row['total']];
                    break;
            }
        }
    }

    $dashboard_data = [
        'manutencoes' => $manutencoes,
        'proximas_manutencoes' => $proximas_manutencoes,
        'extintores' => $extintores
    ];

    // Atomic write to prevent race conditions
    $tempFile = tempnam($cacheDir, 'dash_');
    if ($tempFile) {
        file_put_contents($tempFile, json_encode($dashboard_data));
        chmod($tempFile, 0644);
        rename($tempFile, $cacheFile);
    }
}

$manutencoes = $dashboard_data['manutencoes'] ?? [];
$proximas_manutencoes = $dashboard_data['proximas_manutencoes'] ?? [];
$extintores = $dashboard_data['extintores'] ?? [];

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
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
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'templates/header_controller.php'; ?>

<div class="container fade-in">
    <div class="card border-0 mb-4 text-center" style="background: transparent; box-shadow: none;">
        <h2 style="color: var(--michelin-blue-dark); text-transform: uppercase; letter-spacing: 1px;">Visão Geral do Sistema</h2>
        <p class="text-muted">Acompanhe as métricas de manutenções e a distribuição dos extintores.</p>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="chart-container h-100">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue);">Manutenções Realizadas</h4>
                <canvas id="manutencaoChart"></canvas>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container h-100">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue);">Próximas Manutenções</h4>
                <canvas id="proximasChart"></canvas>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="chart-container">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue-dark);">Distribuição dos Tipos de Extintores</h4>
                <div style="max-height: 400px; display: flex; justify-content: center;">
                    <canvas id="extintoresChart"></canvas>
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