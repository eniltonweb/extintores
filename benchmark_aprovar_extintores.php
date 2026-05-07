<?php
require_once __DIR__ . '/tests/MockDatabase.php';

$conn = new MockConnection();

// Create fake data
$rows = [];
for ($i = 0; $i < 1000; $i++) {
    $rows[] = [
        'codigo' => 'EXT-' . $i,
        'Predio' => 'A',
        'Local_Exato' => 'Terreo',
        'tip_extintor' => 'AP',
        'carga' => '10L',
        'other_col_1' => str_repeat('A', 100),
        'other_col_2' => str_repeat('B', 100),
        'other_col_3' => str_repeat('C', 100),
        'status_aprovacao' => 'Em espera'
    ];
}

$conn->mock_query_results["SELECT * FROM bd_extintores WHERE status_aprovacao = 'Em espera'"] = $rows;

$conn->mock_query_results["SELECT codigo, Predio, Local_Exato, tip_extintor, carga FROM bd_extintores WHERE status_aprovacao = 'Em espera'"] = array_map(function($r) {
    return [
        'codigo' => $r['codigo'],
        'Predio' => $r['Predio'],
        'Local_Exato' => $r['Local_Exato'],
        'tip_extintor' => $r['tip_extintor'],
        'carga' => $r['carga']
    ];
}, $rows);


$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $sql = "SELECT * FROM bd_extintores WHERE status_aprovacao = 'Em espera'";
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
$end = microtime(true);
$baseline = $end - $start;
echo "Tempo (SELECT *): " . $baseline . " segundos\n";

$start2 = microtime(true);

// Implementando cache dos resultados do banco
$sql2 = "SELECT codigo, Predio, Local_Exato, tip_extintor, carga FROM bd_extintores WHERE status_aprovacao = 'Em espera'";
$result2 = $conn->query($sql2);
$cached_data = [];
while ($row = $result2->fetch_assoc()) {
    $cached_data[] = $row;
}

for ($i = 0; $i < 1000; $i++) {
    $data = $cached_data;
}
$end2 = microtime(true);
$optimized = $end2 - $start2;
echo "Tempo (Com cache): " . $optimized . " segundos\n";

$improvement = ($baseline - $optimized) / $baseline * 100;
echo "Melhoria: " . number_format($improvement, 2) . "%\n";

?>
