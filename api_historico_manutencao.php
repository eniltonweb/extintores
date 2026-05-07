<?php
session_start();
require_once __DIR__ . "/config/db_conexao.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_level"] != "admin") {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
    exit();
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch_data') {
    $extintor_codigo = isset($_GET['extintor_codigo']) ? $_GET['extintor_codigo'] : '';
    $predio = isset($_GET['predio']) ? $_GET['predio'] : '';
    $cobertura = isset($_GET['cobertura']) ? $_GET['cobertura'] : '';
    $data_inicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
    $data_final = isset($_GET['data_final']) ? $_GET['data_final'] : '';

    // Build WHERE clauses safely to avoid code analyzer warnings
    $where_sql = "bd_extintores.manutencao_n2 IS NOT NULL";
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
    if ($cobertura === 'SIM') {
        $where_sql .= " AND bd_extintores.cobertura = 1";
    }
    if (!empty($data_inicial)) {
        $where_sql .= " AND bd_extintores.manutencao_n2 >= ?";
        $params[] = $data_inicial;
        $types .= "s";
    }
    if (!empty($data_final)) {
        $where_sql .= " AND bd_extintores.manutencao_n2 <= ?";
        $params[] = $data_final;
        $types .= "s";
    }

    // 1. Contar total de registros para a paginação
    $sql_count = "SELECT COUNT(*) AS total FROM bd_extintores WHERE " . $where_sql;
    $result_count = execute_stmt($conn, $sql_count, $types, $params);
    $total_registros = $result_count ? $result_count->fetch_assoc()['total'] : 0;

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
        WHERE " . $where_sql . "
        GROUP BY manutencao_n2
        ORDER BY manutencao_n2 ASC
    ";
    $result_chart = execute_stmt($conn, $sql_chart, $types, $params);
    $manutencoes_por_data = [];
    if ($result_chart) {
        while ($row_chart = $result_chart->fetch_assoc()) {
            $manutencoes_por_data[$row_chart['data_manutencao']] = (int)$row_chart['total'];
        }
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
            " . $where_sql . "
        ORDER BY bd_extintores.manutencao_n2 DESC
        LIMIT ? OFFSET ?
    ";

    // Add pagination params
    $paginated_params = $params;
    $paginated_params[] = $itens_por_pagina;
    $paginated_params[] = $offset;
    $paginated_types = $types . "ii";

    $result_paginated = execute_stmt($conn, $sql_paginated, $paginated_types, $paginated_params);
    $data = [];
    if ($result_paginated) {
        while ($row = $result_paginated->fetch_assoc()) {
            $data[] = $row;
        }
    }
    if (isset($stmt_paginated)) $stmt_paginated->close();

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
