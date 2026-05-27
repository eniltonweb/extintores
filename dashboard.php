<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/dashboard_data_v2.json'; // Nome novo para não conflitar com cache antigo
$cacheTime = 300; // 5 minutos de cache (reduzido para melhor tempo real)

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

    // 1. Manutenções Realizadas
    $manutencoes = [];
    $res = $conn->query("SELECT tipo_manutencao AS label, COUNT(*) AS total FROM historico_manutencao GROUP BY tipo_manutencao");
    if ($res) while ($row = $res->fetch_assoc()) $manutencoes[] = $row;

    // 2. Próximas Manutenções
    $proximas = [];
    $res = $conn->query("SELECT proxima_manutencao_n2 AS label, COUNT(*) AS total FROM bd_extintores WHERE proxima_manutencao_n2 IS NOT NULL GROUP BY proxima_manutencao_n2");
    if ($res) while ($row = $res->fetch_assoc()) $proximas[] = $row;

    // 3. Extintores por Tipo
    $extintores = [];
    $res = $conn->query("SELECT tip_extintor AS label, COUNT(*) AS total FROM bd_extintores GROUP BY tip_extintor");
    if ($res) while ($row = $res->fetch_assoc()) $extintores[] = $row;

    // 4. Inspeções por Data
    $inspecoes = [];
    $res = $conn->query("SELECT inspecao_trimestral_nivel1 AS label, COUNT(*) AS total FROM bd_extintores WHERE inspecao_trimestral_nivel1 IS NOT NULL GROUP BY inspecao_trimestral_nivel1 ORDER BY inspecao_trimestral_nivel1 ASC");
    if ($res) while ($row = $res->fetch_assoc()) $inspecoes[] = $row;

    // 5. Total de NOK (Com Falha)
    $total_nok = 0;
    $res = $conn->query("SELECT COUNT(*) AS total FROM bd_extintores WHERE sinalizacao_vertical LIKE '%NÃO OK%' OR sinalizacao_piso LIKE '%NÃO OK%' OR ficha_inspecao_trimestral LIKE '%NÃO OK%' OR lacre LIKE '%NÃO OK%' OR pressao_manometro LIKE '%NÃO OK%' OR anel_identificacao LIKE '%NÃO OK%'");
    if ($res) $total_nok = $res->fetch_assoc()['total'];

    // 6. Vencendo em <= 30 dias
    $total_vencendo = 0;
    $res = $conn->query("SELECT COUNT(*) AS total FROM bd_extintores WHERE dias_para_expirar_n2 <= 30 AND proxima_manutencao_n2 IS NOT NULL");
    if ($res) $total_vencendo = $res->fetch_assoc()['total'];

    // 7. Coberturas Ativas
    $total_coberturas = 0;
    $res = $conn->query("SELECT COUNT(*) AS total FROM bd_extintores WHERE cobertura = 1");
    if ($res) $total_coberturas = $res->fetch_assoc()['total'];

    $dashboard_data = [
        'manutencoes' => $manutencoes,
        'proximas'    => $proximas,
        'extintores'  => $extintores,
        'inspecoes'   => $inspecoes,
        'kpis'        => [
            'nok'        => $total_nok,
            'vencendo'   => $total_vencendo,
            'coberturas' => $total_coberturas
        ]
    ];

    // Atomic write
    $tempFile = tempnam($cacheDir, 'dash_v2_');
    if ($tempFile) {
        file_put_contents($tempFile, json_encode($dashboard_data));
        chmod($tempFile, 0644);
        rename($tempFile, $cacheFile);
    }
    
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

// Extrair variáveis
$manutencoes = $dashboard_data['manutencoes'] ?? [];
$proximas_manutencoes = $dashboard_data['proximas'] ?? [];
$extintores = $dashboard_data['extintores'] ?? [];
$inspecoes = $dashboard_data['inspecoes'] ?? [];
$kpis = $dashboard_data['kpis'] ?? ['nok' => 0, 'vencendo' => 0, 'coberturas' => 0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard Executivo MQP</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome para os ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .kpi-card {
            border-radius: 8px;
            padding: 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .kpi-card i {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 80px;
            opacity: 0.2;
        }
        .kpi-nok { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .kpi-vencendo { background: linear-gradient(135deg, #f39c12, #d35400); }
        .kpi-cobertura { background: linear-gradient(135deg, #3498db, #2980b9); }
        
        .kpi-title { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 5px; }
        .kpi-number { font-size: 38px; font-weight: 900; line-height: 1; }
        .kpi-desc { font-size: 11px; opacity: 0.8; margin-top: 8px; }
    </style>
</head>
<body>
<?php include 'templates/header_controller.php'; ?>

<div class="container fade-in">
    <div class="card border-0 mb-4 text-center" style="background: transparent; box-shadow: none;">
        <h2 style="color: var(--michelin-blue-dark); text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Centro de Comando MQP</h2>
        <p class="text-muted">Visão executiva em tempo real de manutenção e inspeções.</p>
    </div>
    
    <!-- Painel de KPIs (Alertas Rápidos) -->
    <div class="row mb-4">
        <!-- NOK -->
        <div class="col-md-4 mb-3">
            <div class="kpi-card kpi-nok">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="kpi-title">Falhas de Inspeção (NOK)</div>
                <div class="kpi-number"><?= $kpis['nok'] ?></div>
                <div class="kpi-desc">Extintores com lacre rompido, sem selo ou danificados detectados pela brigada.</div>
            </div>
        </div>
        <!-- Vencendo -->
        <div class="col-md-4 mb-3">
            <div class="kpi-card kpi-vencendo">
                <i class="fas fa-hourglass-half"></i>
                <div class="kpi-title">Vencendo (&le; 30 Dias)</div>
                <div class="kpi-number"><?= $kpis['vencendo'] ?></div>
                <div class="kpi-desc">Equipamentos cuja manutenção Nível 2 exigirá recolhimento iminente pelo fornecedor.</div>
            </div>
        </div>
        <!-- Coberturas -->
        <div class="col-md-4 mb-3">
            <div class="kpi-card kpi-cobertura">
                <i class="fas fa-exchange-alt"></i>
                <div class="kpi-title">Coberturas Ativas</div>
                <div class="kpi-number"><?= $kpis['coberturas'] ?></div>
                <div class="kpi-desc">Extintores reservas atualmente deslocados cobrindo pontos desguarnecidos.</div>
            </div>
        </div>
    </div>

    <!-- Nova Linha: Gráfico de Inspeções Nível 1 -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="chart-container" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue); font-weight: bold;">Volume de Inspeções Realizadas (Nível 1)</h4>
                <canvas id="inspecoesChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="chart-container h-100" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue);">Manutenções Concluídas (Nível 2/3)</h4>
                <canvas id="manutencaoChart"></canvas>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container h-100" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue);">Projeção de Vencimentos Futuros</h4>
                <canvas id="proximasChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="chart-container" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h4 class="text-center mb-4" style="color: var(--michelin-blue-dark);">Distribuição do Parque de Extintores</h4>
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
    // --- GRÁFICO 1: INSPEÇÕES NÍVEL 1 (NOVO) ---
    var insLabels = <?php echo json_encode(array_column($inspecoes, 'label')); ?>;
    var insData = <?php echo json_encode(array_column($inspecoes, 'total')); ?>;
    
    new Chart(document.getElementById('inspecoesChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: insLabels,
            datasets: [{
                label: 'Extintores Inspecionados no Dia',
                data: insData,
                backgroundColor: 'rgba(39, 80, 155, 0.7)', // Azul Michelin
                borderColor: 'rgba(39, 80, 155, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
    });

    // --- GRÁFICO 2: MANUTENÇÕES CONCLUÍDAS ---
    var manLabels = <?php echo json_encode(array_column($manutencoes, 'label')); ?>;
    var manData = <?php echo json_encode(array_column($manutencoes, 'total')); ?>;

    new Chart(document.getElementById('manutencaoChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: manLabels,
            datasets: [{
                label: 'Manutenções',
                data: manData,
                backgroundColor: ['rgba(75, 192, 192, 0.6)', 'rgba(255, 159, 64, 0.6)'],
                borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 159, 64, 1)'],
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // --- GRÁFICO 3: PROJEÇÃO VENCIMENTOS ---
    var proxLabels = <?php echo json_encode(array_column($proximas_manutencoes, 'label')); ?>;
    var proxData = <?php echo json_encode(array_column($proximas_manutencoes, 'total')); ?>;

    new Chart(document.getElementById('proximasChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: proxLabels,
            datasets: [{
                label: 'Vencimentos',
                data: proxData,
                backgroundColor: 'rgba(252, 229, 0, 0.2)', // Amarelo Michelin
                borderColor: 'rgba(212, 175, 55, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // --- GRÁFICO 4: DISTRIBUIÇÃO ---
    var extLabels = <?php echo json_encode(array_column($extintores, 'label')); ?>;
    var extData = <?php echo json_encode(array_column($extintores, 'total')); ?>;

    new Chart(document.getElementById('extintoresChart').getContext('2d'), {
        type: 'doughnut', // Doughnut fica mais moderno que Pie
        data: {
            labels: extLabels,
            datasets: [{
                data: extData,
                backgroundColor: [
                    '#27509b', '#fce500', '#e74c3c', '#2ecc71', '#9b59b6', '#34495e'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: { responsive: true, cutout: '65%' }
    });
</script>
</body>
</html>