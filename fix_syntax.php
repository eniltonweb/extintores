<?php
$files = [
    'exportar_dados.php',
    'exportar_historico_cobertura.php',
    'exportar_historico_inspecao.php',
    'exportar_historico_manutencao.php',
    'exportar_inspecao_nok.php',
    'exportar_vencidos.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Replace 'Inter' with Inter to prevent breaking PHP string literals
        $content = str_replace("'Inter'", "Inter", $content);
        file_put_contents($file, $content);
        echo "Fixed syntax in $file\n";
    }
}
?>
