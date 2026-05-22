<?php
$files = [
    'exportar_historico_cobertura.php',
    'exportar_historico_inspecao.php',
    'exportar_historico_manutencao.php',
    'exportar_inspecao_nok.php',
    'exportar_vencidos.php'
];

$mpdf_block = <<<'EOD'
    // Gerar PDF com mPDF (Landscape)
    $mpdf = new \Mpdf\Mpdf(['orientation' => 'L', 'format' => 'A4', 'tempDir' => sys_get_temp_dir() . '/mpdf']);
    $mpdf->setBasePath(__DIR__);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
EOD;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Substituir headers HTML por include do autoload
        $content = preg_replace("/header\('Content-Type: text\/html.*?;\s*/i", "require_once __DIR__ . '/vendor/autoload.php';\n", $content);
        
        // Mudar extensões .html para .pdf nos filenames
        $content = preg_replace("/\.html'/", ".pdf'", $content);
        $content = preg_replace('/\.html"/', '.pdf"', $content);

        // Algumas capturam $filename
        // Por exemplo: header('Content-Disposition: attachment; filename=' . $filename);
        // Precisamos capturar a variável $filename e não ecoar o header, mas manter a variável.
        if (preg_match("/header\('Content-Disposition: attachment; filename='\s*\.\s*(\\$[a-zA-Z0-9_]+|\w+.*?)\);/i", $content, $matches)) {
            $filename_var = $matches[1];
            $content = preg_replace("/header\('Content-Disposition: attachment; filename='.*?\);/i", "\$filename = $filename_var;", $content);
        } else if (preg_match("/header\('Content-Disposition: attachment; filename=(.*?)'\);/i", $content, $matches)) {
            // filename literal string
            $content = preg_replace("/header\('Content-Disposition: attachment; filename=.*?'\);/i", "\$filename = '" . $matches[1] . "';", $content);
        }

        // Trocar echo $html; pelo bloco mPDF
        $content = str_replace("echo \$html;", $mpdf_block, $content);

        file_put_contents($file, $content);
        echo "Migrated $file to PDF\n";
    }
}
?>
