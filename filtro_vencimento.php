<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php'; // Inclui a conexão com o banco de dados

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_level'], ['admin', 'fornecedor'])) {
    header('Location: index.php');
    exit();
}

// Atualizar automaticamente a coluna 'dias_para_expirar_n2'
$sql_update = "UPDATE bd_extintores 
               SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE()) 
               WHERE proxima_manutencao_n2 IS NOT NULL";
if ($conn->query($sql_update) === FALSE) {
    error_log("Erro ao atualizar dias_para_expirar_n2: " . $conn->error);
} else {
    if ($conn->affected_rows > 0) {
        error_log("dias_para_expirar_n2 atualizado para " . $conn->affected_rows . " registros.");
    } else {
        error_log("Nenhum registro foi atualizado em dias_para_expirar_n2. Verifique se a coluna proxima_manutencao_n2 está preenchida corretamente.");
    }
}

// Filtrar os extintores com base nos dias para expirar
if (isset($_GET['action']) && $_GET['action'] == 'fetch_data') {
    $dias = filter_input(INPUT_GET, 'dias', FILTER_SANITIZE_NUMBER_INT) ?: 30; // Filtrar os dias, valor padrão: 30
    $sort_column = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?: 'codigo';
    $sort_order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING) ?: 'ASC';

    // Validar colunas permitidas para ordenação
    $valid_columns = ['codigo', 'Predio', 'dias_para_expirar_n2'];
    if (!in_array($sort_column, $valid_columns)) {
        $sort_column = 'codigo';
    }

    // Validar ordem permitida (ASC ou DESC)
    if ($sort_order != 'ASC' && $sort_order != 'DESC') {
        $sort_order = 'ASC';
    }

    $sql = "SELECT * FROM bd_extintores WHERE dias_para_expirar_n2 <= ? ORDER BY $sort_column $sort_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $dias);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode(['data' => $data]); // Retornar os dados em formato JSON
    $stmt->close();
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
    <title>Vencimento dos Extintores</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</head>
<body>
    <?php 
    // Inclui o header correto baseado no nível de usuário
    if ($_SESSION['user_level'] == 'admin') {
        include '../templates/header1.php';
    } elseif ($_SESSION['user_level'] == 'fornecedor') {
        include '../templates/header3.php';
    }
    ?>
    <div class="container mt-4">
        <h1 class="text-center">Extintores Próximos do Vencimento</h1>
        <form id="filterForm" method="GET" class="form-inline justify-content-center mb-4">
            <div class="form-group mx-sm-3 mb-2">
                <label for="dias" class="sr-only">Dias para o Vencimento:</label>
                <input type="number" id="dias" name="dias" class="form-control" placeholder="Dias para o Vencimento" value="30" required>
            </div>
            <button type="submit" class="btn btn-primary mb-2">Filtrar</button>
        </form>

        <!-- Formulário para exportar extintores vencidos -->
        <form id="exportForm" method="GET" action="exportar_vencidos.php" class="form-inline justify-content-center mb-4">
            <input type="hidden" id="dias_export" name="dias" value="30">
            <button type="submit" class="btn btn-success mb-2">Exportar Extintores Vencidos</button>
        </form>

        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th class="sortable" data-sort="codigo">Código</th>
                    <th class="sortable" data-sort="Predio">Prédio</th>
                    <th>Local</th>
                    <th>Validade da Manutenção</th>
                    <th class="sortable" data-sort="dias_para_expirar_n2">Dias para Expirar</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <footer class="footer mt-4">
        <div class="container text-center">
             <p>&copy; 2024 Sistema de Controle de Extintores</p>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadExtintores();
        });

        // Função para carregar os extintores baseado no filtro
        function loadExtintores(sort = 'codigo', order = 'ASC') {
            const dias = document.getElementById('dias').value;
            const url = `filtro_vencimento.php?action=fetch_data&dias=${dias}&sort=${sort}&order=${order}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const extintores = data.data;

                    const tableBody = document.querySelector('table tbody');
                    tableBody.innerHTML = '';

                    if (extintores.length > 0) {
                        extintores.forEach(row => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${row.codigo}</td>
                                <td>${row.Predio}</td>
                                <td>${row.Local_Exato}</td>
                                <td>${new Date(row.proxima_manutencao_n2).toLocaleDateString('pt-BR')}</td>
                                <td>${row.dias_para_expirar_n2}</td>
                            `;
                            tableBody.appendChild(tr);
                        });
                    } else {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td colspan="5" class="text-center">Nenhum extintor encontrado para o filtro aplicado.</td>`;
                        tableBody.appendChild(tr);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        document.getElementById('filterForm').addEventListener('submit', function(event) {
            event.preventDefault();
            loadExtintores();
            document.getElementById('dias_export').value = document.getElementById('dias').value; // Atualizar o valor para exportação
        });

        // Adicionar evento de click para ordenar ao clicar no cabeçalho da tabela
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sort = this.getAttribute('data-sort');
                let order = this.getAttribute('data-order') || 'ASC';
                order = order === 'ASC' ? 'DESC' : 'ASC';
                this.setAttribute('data-order', order);
                loadExtintores(sort, order);
            });
        });
    </script>
</body>
</html>