<?php
$files = [
    'auditoria_logs.php',
    'formulario_manutencao.php',
    'historico_inspecao.php',
    'liberar_manutencao.php',
    'novo_extintor.php',
    'registrar_usuario.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // This regex matches <nav class="navbar ..."> ... </nav> block
        // We use PCRE_DOTALL (?s) so . matches newlines
        $content = preg_replace('/<nav class="navbar.*?<\/nav>/s', "<?php include 'templates/header_controller.php'; ?>", $content, 1);
        
        file_put_contents($file, $content);
        echo "Fixed menu in $file\n";
    }
}
?>
