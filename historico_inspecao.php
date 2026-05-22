<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';


// Verificar se a conexão ao banco de dados está configurada para usar a codificação correta
$conn->set_charset("utf8");

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch_data') {
    $extintor_codigo = isset($_GET['extintor_codigo']) ? $_GET['extintor_codigo'] : '';
    $predio = isset($_GET['predio']) ? $_GET['predio'] : '';

    $where_sql = "bd_extintores.inspecao_trimestral_nivel1 IS NOT NULL AND bd_extintores.inspecao_trimestral_nivel1 >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $params = [];
    $types = "";

    if (!empty($extintor_codigo)) {
        $where_sql .= " AND bd_extintores.codigo LIKE ?";
        $params[] = "%" . $extintor_codigo . "%";
        $types .= "s";
    }

    if (!empty($predio)) {
        $where_sql .= " AND bd_extintores.Predio LIKE ?";
        $params[] = "%" . $predio . "%";
        $types .= "s";
    }

    // 1. Contar total de registros para a paginação
    $sql_count = "SELECT COUNT(*) AS total FROM bd_extintores WHERE " . $where_sql;
    $result_count = execute_stmt($conn, $sql_count, $types, $params);
    $count_row = $result_count ? $result_count->fetch_assoc() : null;
    $total_registros = $count_row ? (int)$count_row['total'] : 0;

    $itens_por_pagina = 20;
    $total_paginas = ceil($total_registros / $itens_por_pagina);
    $pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($pagina_atual < 1) $pagina_atual = 1;
    if ($total_paginas > 0 && $pagina_atual > $total_paginas) $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    // 2. Buscar dados para o gráfico usando GROUP BY (evita carregar todos os dados em PHP)
    $sql_chart = "
        SELECT inspecao_trimestral_nivel1 AS data_inspecao, COUNT(*) AS total
        FROM bd_extintores
        WHERE " . $where_sql . "
        GROUP BY inspecao_trimestral_nivel1
        ORDER BY inspecao_trimestral_nivel1 ASC
    ";
    $result_chart = execute_stmt($conn, $sql_chart, $types, $params);
    $inspecoes_por_data = [];
    if ($result_chart) {
        while ($row_chart = $result_chart->fetch_assoc()) {
            $inspecoes_por_data[$row_chart['data_inspecao']] = (int)$row_chart['total'];
        }
    }

    // 3. Buscar dados paginados para a tabela
    $sql_paginated = "
        SELECT 
            bd_extintores.codigo AS extintor_codigo, 
            bd_extintores.Local_Exato AS local_exato,
            bd_extintores.Predio AS predio,
            COALESCE(bd_extintores.usuario, 'Usuário removido') AS usuario_nome, 
            bd_extintores.inspecao_trimestral_nivel1 AS data_inspecao,
            bd_extintores.selo_do_Inmetro, 
            bd_extintores.sinalizacao_vertical,
            bd_extintores.sinalizacao_piso, 
            bd_extintores.ficha_inspecao_trimestral,
            bd_extintores.lacre, 
            bd_extintores.pressao_manometro,
            bd_extintores.anel_identificacao, 
            bd_extintores.pesagem_co2_semestral,
            bd_extintores.usuario AS usuario_id,
            bd_extintores.comentarios AS comentario,
            bd_extintores.updated_at AS atualizacao
        FROM 
            bd_extintores
        LEFT JOIN 
            usuarios ON bd_extintores.usuario = usuarios.id
        WHERE " . $where_sql . "
        ORDER BY bd_extintores.inspecao_trimestral_nivel1 DESC
        LIMIT ? OFFSET ?
    ";

    $params_paginated = $params;
    $types_paginated = $types . "ii";
    $params_paginated[] = $itens_por_pagina;
    $params_paginated[] = $offset;

    $result_paginated = execute_stmt($conn, $sql_paginated, $types_paginated, $params_paginated);

    $data = [];
    if ($result_paginated) {
        while ($row = $result_paginated->fetch_assoc()) {
            $data[] = $row;
        }
    }

    echo json_encode([
        'data' => $data,
        'inspecoes_por_data' => $inspecoes_por_data,
        'pagination' => [
            'total_paginas' => $total_paginas,
            'pagina_atual' => $pagina_atual,
            'total_registros' => $total_registros
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
    <title>Sistema de Controle e Manutenção de Extintores</title>
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
<?php include 'templates/header_controller.php'; ?>
<div class="container mt-4">
    <h2>Histórico de Inspeções</h2>

    <form class="filter-form mb-4" method="GET" onsubmit="loadInspecoes(); return false;">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="extintor_codigo">Código do Extintor:</label>
                <input type="text" id="extintor_codigo" name="extintor_codigo" class="form-control" placeholder="Digite o código do extintor">
            </div>
            <div class="form-group col-md-6">
                <label for="predio">Prédio:</label>
                <input type="text" id="predio" name="predio" class="form-control" placeholder="Digite o prédio">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>

    <form method="POST" action="limpar_historico_inspecao.php" onsubmit="return confirm('Tem certeza que deseja limpar o histórico completo? Esta ação não pode ser desfeita.');">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <button type="submit" class="btn btn-danger mb-4">Limpar Histórico</button>
    </form>
    <a href="exportar_historico_inspecao.php" class="btn btn-success mb-4">Exportar Histórico de Inspeção</a>
    <a href="exportar_inspecao_nok.php" class="btn btn-success mb-4">Exportar Histórico de Inspeção Não Conforme</a>
    
    <button class="btn btn-primary mb-4" type="button" data-toggle="collapse" data-target="#painelPDF" aria-expanded="false" aria-controls="painelPDF">
        Gerar Relatório PDF Avançado
    </button>

    <div class="collapse mb-4" id="painelPDF">
        <div class="card card-body border-primary">
            <h5 class="card-title text-primary">Filtros do Relatório em PDF</h5>
            <form action="exportar_relatorio_pdf.php" method="GET" target="_blank">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="pdf_predio">Prédio (Opcional):</label>
                        <input type="text" name="predio" id="pdf_predio" class="form-control" placeholder="Ex: 470">
                    </div>

                    <div class="form-group col-md-3">
                        <label for="pdf_de">Data Inicial:</label>
                        <input type="date" name="de" id="pdf_de" class="form-control" value="<?= date('Y-m-01') ?>">
                    </div>

                    <div class="form-group col-md-3">
                        <label for="pdf_ate">Data Final:</label>
                        <input type="date" name="ate" id="pdf_ate" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="apenas_nok" id="pdf_apenas_nok" value="1">
                            <label class="form-check-label text-danger font-weight-bold" for="pdf_apenas_nok">
                                Apenas Não Conformes
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Gerar PDF</button>
            </form>
        </div>
    </div>

    <table class="table table-bordered">


    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Extintor</th>
                <th>Prédio</th>
                <th>Local</th>
                <th>Usuário</th>
                <th>Data</th>
                <th>Comentários</th>
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
        <canvas id="inspecaoChart"></canvas>
    </div>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadInspecoes();
    });

    function loadInspecoes(page = 1) {
        const extintorCodigo = document.getElementById('extintor_codigo').value;
        const predio = document.getElementById('predio').value;
        const url = `historico_inspecao.php?action=fetch_data&extintor_codigo=${extintorCodigo}&predio=${predio}&page=${page}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                const inspecoes = data.data;
                const inspecoesPorData = data.inspecoes_por_data;
                const pagination = data.pagination;

                const tableBody = document.querySelector('table tbody');
                tableBody.innerHTML = '';

                if (inspecoes.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma inspeção encontrada</td></tr>';
                } else {
                    const fragment = document.createDocumentFragment();
                    inspecoes.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${row.extintor_codigo}</td>
                            <td>${row.predio}</td>
                            <td>${row.local_exato}</td>
                            <td>${row.usuario_nome}</td>
                            <td>${row.atualizacao}</td>
                            <td>${row.comentario}</td>
                        `;
                        fragment.appendChild(tr);
                    });
                    tableBody.appendChild(fragment);
                }

                updatePagination(pagination);
                updateChart(inspecoesPorData);
            })
            .catch(error => console.error('Error:', error));
    }

    function updatePagination(pagination) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        if (!pagination || pagination.total_paginas <= 1) return;

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
            loadInspecoes(page);
        };
        li.appendChild(a);
        return li;
    }

    function updateChart(inspecoesPorData) {
        updateLineChart('inspecaoChart', 'inspecaoChart', inspecoesPorData, 'Número de Inspeções', 'dd/MM/yyyy');
    }
</script>
</body>
</html>
