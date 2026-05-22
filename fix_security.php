<?php
$admin_files = [
    'aprovar_extintores.php', 'auditoria_logs.php', 'exportar_dados.php', 
    'exportar_historico_cobertura.php', 'exportar_historico_inspecao.php', 
    'exportar_historico_manutencao.php', 'exportar_inspecao_nok.php', 
    'exportar_relatorio_pdf.php', 'exportar_vencidos.php', 'historico_inspecao.php', 
    'historico_manutencao.php', 'liberar_manutencao.php', 'limpar_historico.php', 
    'limpar_historico_inspecao.php', 'registrar_usuario.php'
];

$bombeiro_files = ['formulario_inspecao.php', 'novo_extintor.php', 'salvar_inspecao.php', 'salvar_novo_extintor.php'];
$fornecedor_files = ['formulario_manutencao.php', 'salvar_manutencao.php'];
$misto_files = ['filtro_vencimento.php'];

function addSecurityCheck($filename, $role) {
    if (!file_exists($filename)) return;
    $content = file_get_contents($filename);
    
    // Check if user_level check exists
    if (strpos($content, "\$_SESSION['user_level']") !== false && strpos($content, "!= '$role'") !== false) {
        return; // Already secured
    }
    
    // if it has if (!isset($_SESSION['user_id'])) without user_level
    $pattern = "/if\s*\(\!isset\(\\\$_SESSION\['user_id'\]\)\)\s*\{/";
    
    if ($role == 'admin') {
        $replacement = "if (!isset(\$_SESSION['user_id']) || \$_SESSION['user_level'] !== 'admin') {";
    } elseif ($role == 'bombeiro') {
        $replacement = "if (!isset(\$_SESSION['user_id']) || \$_SESSION['user_level'] !== 'bombeiro') {";
    } elseif ($role == 'fornecedor') {
        $replacement = "if (!isset(\$_SESSION['user_id']) || \$_SESSION['user_level'] !== 'fornecedor') {";
    } elseif ($role == 'misto') {
        $replacement = "if (!isset(\$_SESSION['user_id']) || (\$_SESSION['user_level'] !== 'admin' && \$_SESSION['user_level'] !== 'fornecedor')) {";
    }

    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $replacement, $content);
        file_put_contents($filename, $content);
        echo "Secured $filename (replaced isset)\n";
    } else {
        // Find session_start() and inject after it
        $pattern_ss = "/session_start\(\);/";
        $inject = "session_start();\n\n$replacement\n    header('Location: index.php');\n    exit();\n}";
        if (preg_match($pattern_ss, $content)) {
            $content = preg_replace($pattern_ss, $inject, $content, 1);
            file_put_contents($filename, $content);
            echo "Secured $filename (injected after session_start)\n";
        }
    }
}

function replaceHeaders($filename) {
    if (!file_exists($filename)) return;
    $content = file_get_contents($filename);
    
    $modified = false;
    $patterns = [
        "/include\s+'\/?templates\/header1\.php';/",
        "/include\s+'\/?templates\/header2\.php';/",
        "/include\s+'\/?templates\/header3\.php';/",
        "/include\s+'\/?templates\/header\.php';/",
        "/include\s+\"templates\/header1\.php\";/",
        "/include\s+\"templates\/header2\.php\";/",
        "/include\s+\"templates\/header3\.php\";/",
        "/include\s+\"templates\/header\.php\";/"
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "include 'templates/header_controller.php';", $content);
            $modified = true;
        }
    }
    
    if ($modified) {
        file_put_contents($filename, $content);
        echo "Replaced headers in $filename\n";
    }
}

foreach ($admin_files as $f) { addSecurityCheck($f, 'admin'); replaceHeaders($f); }
foreach ($bombeiro_files as $f) { addSecurityCheck($f, 'bombeiro'); replaceHeaders($f); }
foreach ($fornecedor_files as $f) { addSecurityCheck($f, 'fornecedor'); replaceHeaders($f); }
foreach ($misto_files as $f) { addSecurityCheck($f, 'misto'); replaceHeaders($f); }

// dashboard and index are already handled manually, but let's run replaceHeaders just in case
replaceHeaders('index.php');
replaceHeaders('dashboard.php');

echo "Done.\n";
?>
