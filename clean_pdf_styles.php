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
        
        // Remove links to bootstrap and styles.css
        $content = preg_replace('/<link[^>]*href="[^"]*bootstrap[^"]*"[^>]*>/i', '', $content);
        $content = preg_replace('/<link[^>]*href="styles\.css"[^>]*>/i', '', $content);
        
        // Remove scripts
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        
        file_put_contents($file, $content);
        echo "Cleaned HTML links in $file\n";
    }
}
?>
