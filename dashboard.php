<?php
session_start();
include '../config/db_conexao.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Consultar dados de manutenções realizadas
$sql_manutencao = "SELECT tipo_manutencao, COUNT(*) AS total FROM historico_manutencao GROUP BY tipo_manutencao";
$result_manutencao = $conn->query($sql_manutencao);

$manutencoes = [];
while ($row = $result_manutencao->fetch_assoc()) {
    $manutencoes[] = $row;
}

// Consultar dados de próximas manutenções
$sql_proximas = "SELECT proxima_manutencao_n2, COUNT(*) AS total FROM bd_extintores WHERE proxima_manutencao_n2 IS NOT NULL GROUP BY proxima_manutencao_n2";
$result_proximas = $conn->query($sql_proximas);

$proximas_manutencoes = [];
while ($row = $result_proximas->fetch_assoc()) {
    $proximas_manutencoes[] = $row;
}

// Consultar dados de tipos de extintores
$sql_extintores = "SELECT tip_extintor, COUNT(*) AS total FROM bd_extintores GROUP BY tip_extintor";
$result_extintores = $conn->query($sql_extintores);

$extintores = [];
while ($row = $result_extintores->fetch_assoc()) {
    $extintores[] = $row;
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
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .container {
            margin-top: 20px;
        }
        .chart-container {
            margin-top: 20px;
        }
        footer {
            background-color: #343a40;
            color: #fff;
            text-align: center;
            padding: 10px 0;
            margin-top: 20px;
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