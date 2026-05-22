<?php
$files = glob("exportar_*.php");

$style_block = "
    <style>
        body.export-page { background-color: #ffffff; font-family: 'Inter', sans-serif; }
        .export-page .header-img { max-width: 120px !important; margin-bottom: 1rem; }
        .export-page h2 { color: #27509b; font-weight: 700; margin-bottom: 1.5rem; }
        .export-page table { font-size: 12px; width: 100%; border-collapse: collapse; }
        .export-page th { background-color: #27509b !important; color: #ffffff !important; padding: 10px; }
        .export-page td { padding: 8px; border: 1px solid #cbd5e0; }
        .export-page tbody tr:nth-child(even) { background-color: #f8fafc !important; }
        /* Garantir impressão de cores de fundo (WebKit) */
        @media print {
            .export-page th { background-color: #27509b !important; -webkit-print-color-adjust: exact; color: #ffffff !important; }
            .export-page tbody tr:nth-child(even) { background-color: #f8fafc !important; -webkit-print-color-adjust: exact; }
        }
    </style>
";

foreach ($files as $file) {
    if ($file === 'exportar_relatorio_pdf.php') continue; // O PDF usa mpdf, não HTML baixado
    
    $content = file_get_contents($file);
    
    // Inserir style antes de </head> se já não tiver
    if (strpos($content, ".export-page .header-img") === false) {
        $content = str_replace("</head>", $style_block . "</head>", $content);
        
        // Colocar inline style diretamente na img como garantia caso o email ou cliente limpe o CSS
        $content = preg_replace('/<img([^>]*?)class=["\']header-img["\']([^>]*?)>/i', '<img$1class="header-img" style="max-width: 150px; margin-bottom: 15px;"$2>', $content);
        
        file_put_contents($file, $content);
        echo "Fixed styles in $file\n";
    }
}
echo "Done.\n";
?>
