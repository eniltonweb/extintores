<?php
$files = [
    'exportar_historico_cobertura.php',
    'exportar_historico_inspecao.php',
    'exportar_historico_manutencao.php',
    'exportar_inspecao_nok.php',
    'exportar_vencidos.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Fix syntax error caused by previous regex
        $content = preg_replace("/charset=utf-8'\);[\r\n]*/", "", $content);
        file_put_contents($file, $content);
    }
}
echo "Fixed regex mistake.\n";
?>
