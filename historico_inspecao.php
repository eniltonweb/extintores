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

    $sql = "
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
        WHERE 
            bd_extintores.inspecao_trimestral_nivel1 IS NOT NULL 
            AND bd_extintores.inspecao_trimestral_nivel1 >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    ";

    if (!empty($extintor_codigo)) {
        $sql .= " AND bd_extintores.codigo LIKE '%" . $conn->real_escape_string($extintor_codigo) . "%'";
    }

    if (!empty($predio)) {
        $sql .= " AND bd_extintores.Predio LIKE '%" . $conn->real_escape_string($predio) . "%'";
    }

    $sql .= " ORDER BY bd_extintores.inspecao_trimestral_nivel1 DESC";

    $result = $conn->query($sql);

    $data = [];
    $inspecoes_por_data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $data_inspecao = $row['data_inspecao'];
        if (!isset($inspecoes_por_data[$data_inspecao])) {
            $inspecoes_por_data[$data_inspecao] = 1;
        } else {
            $inspecoes_por_data[$data_inspecao]++;
        }
    }

    echo json_encode(['data' => $data, 'inspecoes_por_data' => $inspecoes_por_data]);
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
    
    <div class="chart-container mt-4">
        <canvas id="inspecaoChart"></canvas>
    </div>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadInspecoes();
    });

    function loadInspecoes() {
        const extintorCodigo = document.getElementById('extintor_codigo').value;
        const predio = document.getElementById('predio').value;
        const url = `historico_inspecao.php?action=fetch_data&extintor_codigo=${extintorCodigo}&predio=${predio}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                const inspecoes = data.data;
                const inspecoesPorData = data.inspecoes_por_data;

                const tableBody = document.querySelector('table tbody');
                tableBody.innerHTML = '';

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

                updateChart(inspecoesPorData);
            })
            .catch(error => console.error('Error:', error));
    }

    function updateChart(inspecoesPorData) {
        updateLineChart('inspecaoChart', 'inspecaoChart', inspecoesPorData, 'Número de Inspeções', 'dd/MM/yyyy');
    }
</script>
</body>
</html>
