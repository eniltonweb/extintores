<?php
session_start();
include '../config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch_data') {
    $extintor_codigo = isset($_GET['extintor_codigo']) ? $_GET['extintor_codigo'] : '';
    $predio = isset($_GET['predio']) ? $_GET['predio'] : '';
    $cobertura = isset($_GET['cobertura']) && $_GET['cobertura'] == 'SIM' ? 1 : '';
    $data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
    $data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';

    // Consulta atualizada sem o LEFT JOIN
    $sql = "
        SELECT 
            bd_extintores.codigo AS extintor_codigo, 
            bd_extintores.Local_Exato AS local_exato,
            bd_extintores.Predio AS predio,
            bd_extintores.cobertura,
            CASE 
                WHEN bd_extintores.usuario_n2 IS NULL OR bd_extintores.usuario_n2 = '' THEN 'Usuário removido'
                ELSE bd_extintores.usuario_n2
            END AS usuario_nome, 
            bd_extintores.manutencao_n2 AS data_manutencao
        FROM 
            bd_extintores
        WHERE 
            bd_extintores.manutencao_n2 IS NOT NULL
    ";

    if (!empty($extintor_codigo)) {
        $sql .= " AND bd_extintores.codigo LIKE '%" . $conn->real_escape_string($extintor_codigo) . "%'";
    }

    if (!empty($predio)) {
        $sql .= " AND bd_extintores.Predio LIKE '%" . $conn->real_escape_string($predio) . "%'";
    }

    if ($cobertura !== '') {
        $sql .= " AND bd_extintores.cobertura = 1";
    }

    if (!empty($data_inicial)) {
        $sql .= " AND bd_extintores.manutencao_n2 >= '" . $conn->real_escape_string($data_inicial) . "'";
    }

    if (!empty($data_final)) {
        $sql .= " AND bd_extintores.manutencao_n2 <= '" . $conn->real_escape_string($data_final) . "'";
    }

    $sql .= " ORDER BY bd_extintores.manutencao_n2 DESC";

    $result = $conn->query($sql);

    $data = [];
    $manutencoes_por_data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $data_manutencao = $row['data_manutencao'];
        if (!isset($manutencoes_por_data[$data_manutencao])) {
            $manutencoes_por_data[$data_manutencao] = 1;
        } else {
            $manutencoes_por_data[$data_manutencao]++;
        }
    }

    echo json_encode(['data' => $data, 'manutencoes_por_data' => $manutencoes_por_data]);
    $conn->close();
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Histórico de Manutenções</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
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
                    <a class="nav-link" href="aprovar_extintores.php">Aprovar Extintores</a>
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
    <h2>Histórico de Manutenções</h2>

    <form class="filter-form mb-4" method="GET" onsubmit="loadManutencoes(); return false;">
        <div class="form-row">
            <div class="form-group col-md-4">
                <label for="extintor_codigo">Código do Extintor:</label>
                <input type="text" id="extintor_codigo" name="extintor_codigo" class="form-control" placeholder="Digite o código do extintor">
            </div>
            <div class="form-group col-md-4">
                <label for="predio">Prédio:</label>
                <input type="text" id="predio" name="predio" class="form-control" placeholder="Digite o prédio">
            </div>
            <div class="form-group col-md-4">
                <label for="cobertura">Mostrar Coberturas:</label>
                <select id="cobertura" name="cobertura" class="form-control">
                    <option value="">Todos</option>
                    <option value="SIM">Cobertura</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="data_inicial">Data Inicial:</label>
                <input type="date" id="data_inicial" name="data_inicial" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label for="data_final">Data Final:</label>
                <input type="date" id="data_final" name="data_final" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>

    <form method="POST" action="limpar_historico.php" onsubmit="return confirm('Tem certeza que deseja limpar todo o histórico? Esta ação não pode ser desfeita.');">
        <button type="submit" class="btn btn-danger mb-4">Limpar Histórico</button>
    </form>
		<a href="exportar_historico_manutencao.php?cobertura=all" class="btn btn-primary mb-4">Exportar Todos</a>
		<a href="exportar_historico_manutencao.php?cobertura=sim" class="btn btn-secondary mb-4">Exportar Cobertura</a>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Extintor</th>
                <th>Prédio</th>
                <th>Local</th>
                <th>Usuário</th>
                <th>Data</th>
                <th>Cobertura</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div class="chart-container mt-4">
        <canvas id="manutencaoChart"></canvas>
    </div>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadManutencoes();
    });

    function loadManutencoes() {
        const extintorCodigo = document.getElementById('extintor_codigo').value;
        const predio = document.getElementById('predio').value;
        const cobertura = document.getElementById('cobertura').value;
        const dataInicial = document.getElementById('data_inicial').value;
        const dataFinal = document.getElementById('data_final').value;
        const url = `historico_manutencao.php?action=fetch_data&extintor_codigo=${extintorCodigo}&predio=${predio}&cobertura=${cobertura}&data_inicial=${dataInicial}&data_final=${dataFinal}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                const manutencoes = data.data;
                const manutencoesPorData = data.manutencoes_por_data;

                const tableBody = document.querySelector('table tbody');
                tableBody.innerHTML = '';

                manutencoes.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${row.extintor_codigo}</td>
                        <td>${row.predio}</td>
                        <td>${row.local_exato}</td>
                        <td>${row.usuario_nome}</td>
                        <td>${new Date(row.data_manutencao).toLocaleDateString('pt-BR')}</td>
                        <td>${row.cobertura === 1 ? 'Sim' : 'Não'}</td>
                    `;
                    tableBody.appendChild(tr);
                });

                updateChart(manutencoesPorData);
            })
            .catch(error => console.error('Error:', error));
    }

    function updateChart(manutencoesPorData) {
        const ctx = document.getElementById('manutencaoChart').getContext('2d');
        const labels = Object.keys(manutencoesPorData);
        const data = Object.values(manutencoesPorData);

        if (window.manutencaoChart && typeof window.manutencaoChart.destroy === 'function') {
            window.manutencaoChart.destroy();
        }

        window.manutencaoChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Número de Manutenções',
                    data: data,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        },
                        title: {
                            display: true,
                            text: 'Data'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantidade'
                        }
                    }
                }
            }
        });
    }
</script>
</body>
</html>
