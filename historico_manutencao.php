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
<?php include "templates/header1.php"; ?>
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

    <form method="POST" action="limpar_historico.php" onsubmit="return confirm('Tem certeza que deseja limpar o histórico completo? Esta ação não pode ser desfeita.');">
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
        const url = `api_historico_manutencao.php?action=fetch_data&extintor_codigo=${extintorCodigo}&predio=${predio}&cobertura=${cobertura}&data_inicial=${dataInicial}&data_final=${dataFinal}&page=${page}`;

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
<?php include "templates/footer.php"; ?>
