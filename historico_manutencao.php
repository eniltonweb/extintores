<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (isset($_GET['action']) && $_GET['action'] == 'fetch_data') {
    $extintor_codigo = isset($_GET['extintor_codigo']) ? $_GET['extintor_codigo'] : '';
    $predio = isset($_GET['predio']) ? $_GET['predio'] : '';
    $cobertura = isset($_GET['cobertura']) ? $_GET['cobertura'] : '';
    $data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
    $data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';

    // Build WHERE clauses
    $where_clauses = ["bd_extintores.manutencao_n2 IS NOT NULL"];
    if (!empty($extintor_codigo)) {
        $where_clauses[] = "bd_extintores.codigo LIKE '%" . $conn->real_escape_string($extintor_codigo) . "%'";
    }
    if (!empty($predio)) {
        $where_clauses[] = "bd_extintores.Predio LIKE '%" . $conn->real_escape_string($predio) . "%'";
    }
    if ($cobertura === 'SIM') {
        $where_clauses[] = "bd_extintores.cobertura = 1";
    }
    if (!empty($data_inicial)) {
        $where_clauses[] = "bd_extintores.manutencao_n2 >= '" . $conn->real_escape_string($data_inicial) . "'";
    }
    if (!empty($data_final)) {
        $where_clauses[] = "bd_extintores.manutencao_n2 <= '" . $conn->real_escape_string($data_final) . "'";
    }
    $where_sql = implode(" AND ", $where_clauses);

    // 1. Contar total de registros para a paginação
    $sql_count = "SELECT COUNT(*) AS total FROM bd_extintores WHERE $where_sql";
    $result_count = $conn->query($sql_count);
    $total_registros = $result_count->fetch_assoc()['total'];

    $itens_por_pagina = 20;
    $total_paginas = ceil($total_registros / $itens_por_pagina);
    $pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($pagina_atual < 1) $pagina_atual = 1;
    if ($total_paginas > 0 && $pagina_atual > $total_paginas) $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    // 2. Buscar dados para o gráfico usando GROUP BY (evita carregar todos os dados em PHP)
    $sql_chart = "
        SELECT manutencao_n2 AS data_manutencao, COUNT(*) AS total
        FROM bd_extintores
        WHERE $where_sql
        GROUP BY manutencao_n2
        ORDER BY manutencao_n2 ASC
    ";
    $result_chart = $conn->query($sql_chart);
    $manutencoes_por_data = [];
    while ($row_chart = $result_chart->fetch_assoc()) {
        $manutencoes_por_data[$row_chart['data_manutencao']] = (int)$row_chart['total'];
    }

    // 3. Buscar dados paginados para a tabela
    $sql_paginated = "
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
            $where_sql
        ORDER BY bd_extintores.manutencao_n2 DESC
        LIMIT $itens_por_pagina OFFSET $offset
    ";
    $result_paginated = $conn->query($sql_paginated);
    $data = [];
    while ($row = $result_paginated->fetch_assoc()) {
        $data[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $data,
        'manutencoes_por_data' => $manutencoes_por_data,
        'pagination' => [
            'total_paginas' => (int)$total_paginas,
            'pagina_atual' => (int)$pagina_atual,
            'total_registros' => (int)$total_registros
        ]
    ]);
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
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <script src="js/chart_utils.js"></script>
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
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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

    <nav aria-label="Navegação de página" class="mt-4">
        <ul id="pagination" class="pagination justify-content-center">
            <!-- Os controles de paginação serão inseridos aqui via JS -->
        </ul>
    </nav>

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

    function loadManutencoes(page = 1) {
        const extintorCodigo = document.getElementById('extintor_codigo').value;
        const predio = document.getElementById('predio').value;
        const cobertura = document.getElementById('cobertura').value;
        const dataInicial = document.getElementById('data_inicial').value;
        const dataFinal = document.getElementById('data_final').value;
        const url = `historico_manutencao.php?action=fetch_data&extintor_codigo=${extintorCodigo}&predio=${predio}&cobertura=${cobertura}&data_inicial=${dataInicial}&data_final=${dataFinal}&page=${page}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                const manutencoes = data.data;
                const manutencoesPorData = data.manutencoes_por_data;
                const pagination = data.pagination;

                const tableBody = document.querySelector('table tbody');
                tableBody.innerHTML = '';

                if (manutencoes.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma manutenção encontrada</td></tr>';
                } else {
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
                }

                updatePagination(pagination);
                updateChart(manutencoesPorData);
            })
            .catch(error => console.error('Error:', error));
    }

    function updatePagination(pagination) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        if (pagination.total_paginas <= 1) return;

        const range = 2;
        const currentPage = pagination.pagina_atual;
        const totalPages = pagination.total_paginas;

        if (currentPage > 1) {
            paginationContainer.appendChild(createPageItem('Anterior', currentPage - 1));
        }

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
                paginationContainer.appendChild(createPageItem(i, i, i === currentPage));
            } else if (i === currentPage - range - 1 || i === currentPage + range + 1) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">...</span>';
                paginationContainer.appendChild(li);
            }
        }

        if (currentPage < totalPages) {
            paginationContainer.appendChild(createPageItem('Próximo', currentPage + 1));
        }
    }

    function createPageItem(label, page, active = false) {
        const li = document.createElement('li');
        li.className = `page-item ${active ? 'active' : ''}`;
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = label;
        a.onclick = (e) => {
            e.preventDefault();
            loadManutencoes(page);
        };
        li.appendChild(a);
        return li;
    }

    function updateChart(manutencoesPorData) {
        updateLineChart('manutencaoChart', 'manutencaoChart', manutencoesPorData, 'Número de Manutenções');
    }
</script>
</body>
</html>
