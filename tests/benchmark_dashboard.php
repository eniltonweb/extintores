<?php
require_once __DIR__ . '/MockDatabase.php';

class MockConnectionWithLatency extends MockConnection {
    public function query($query) {
        usleep(2000); // Simulate 2ms network latency per query
        return parent::query($query);
    }
}

$conn = new MockConnectionWithLatency();

// Simulate some data
$conn->mock_query_results["SELECT tipo_manutencao, COUNT(*) AS total FROM historico_manutencao GROUP BY tipo_manutencao"] = [
    ['tipo_manutencao' => 'Preventiva', 'total' => 10],
    ['tipo_manutencao' => 'Corretiva', 'total' => 5],
];
$conn->mock_query_results["SELECT proxima_manutencao_n2, COUNT(*) AS total FROM bd_extintores WHERE proxima_manutencao_n2 IS NOT NULL GROUP BY proxima_manutencao_n2"] = [
    ['proxima_manutencao_n2' => '2024-01-01', 'total' => 20],
    ['proxima_manutencao_n2' => '2024-02-01', 'total' => 15],
];
$conn->mock_query_results["SELECT tip_extintor, COUNT(*) AS total FROM bd_extintores GROUP BY tip_extintor"] = [
    ['tip_extintor' => 'AP', 'total' => 30],
    ['tip_extintor' => 'CO2', 'total' => 10],
];
$conn->mock_query_results["
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
    "] = [
        ['source' => 'manutencao', 'label' => 'Preventiva', 'total' => 10],
        ['source' => 'manutencao', 'label' => 'Corretiva', 'total' => 5],
        ['source' => 'proxima', 'label' => '2024-01-01', 'total' => 20],
        ['source' => 'proxima', 'label' => '2024-02-01', 'total' => 15],
        ['source' => 'extintores', 'label' => 'AP', 'total' => 30],
        ['source' => 'extintores', 'label' => 'CO2', 'total' => 10],
    ];


$start = microtime(true);

for ($i = 0; $i < 100; $i++) {
    $sql_manutencao = "SELECT tipo_manutencao, COUNT(*) AS total FROM historico_manutencao GROUP BY tipo_manutencao";
    $result_manutencao = $conn->query($sql_manutencao);
    $manutencoes = [];
    while ($row = $result_manutencao->fetch_assoc()) {
        $manutencoes[] = $row;
    }

    $sql_proximas = "SELECT proxima_manutencao_n2, COUNT(*) AS total FROM bd_extintores WHERE proxima_manutencao_n2 IS NOT NULL GROUP BY proxima_manutencao_n2";
    $result_proximas = $conn->query($sql_proximas);
    $proximas_manutencoes = [];
    while ($row = $result_proximas->fetch_assoc()) {
        $proximas_manutencoes[] = $row;
    }

    $sql_extintores = "SELECT tip_extintor, COUNT(*) AS total FROM bd_extintores GROUP BY tip_extintor";
    $result_extintores = $conn->query($sql_extintores);
    $extintores = [];
    while ($row = $result_extintores->fetch_assoc()) {
        $extintores[] = $row;
    }
}

$end = microtime(true);
echo "Tempo (3 queries, com latência): " . ($end - $start) . " segundos\n";

$start2 = microtime(true);

for ($i = 0; $i < 100; $i++) {
    $sql = "
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

    $result = $conn->query($sql);
    $manutencoes2 = [];
    $proximas_manutencoes2 = [];
    $extintores2 = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['source'] === 'manutencao') {
                $manutencoes2[] = ['tipo_manutencao' => $row['label'], 'total' => $row['total']];
            } elseif ($row['source'] === 'proxima') {
                $proximas_manutencoes2[] = ['proxima_manutencao_n2' => $row['label'], 'total' => $row['total']];
            } elseif ($row['source'] === 'extintores') {
                $extintores2[] = ['tip_extintor' => $row['label'], 'total' => $row['total']];
            }
        }
    }
}

$end2 = microtime(true);
echo "Tempo (1 query com UNION ALL, com latência): " . ($end2 - $start2) . " segundos\n";

?>
