<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Capturar o valor do parâmetro de cobertura
$cobertura_param = isset($_GET['cobertura']) ? $_GET['cobertura'] : 'all';

// Construir a consulta SQL com base no parâmetro de cobertura
$sql = "
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
        bd_extintores.manutencao_n2 IS NOT NULL
";

if ($cobertura_param == 'sim') {
    $sql .= " AND bd_extintores.cobertura = 1";
}

$sql .= " ORDER BY bd_extintores.manutencao_n2 DESC";

$result = $conn->query($sql);

// Verificar se a consulta teve resultados
if ($result->num_rows > 0) {
    // Começar a criar o conteúdo do HTML para exportar
    $html = "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Histórico de Manutenções de Extintores</title>
        <link rel='stylesheet' href='styles.css'>
    
    <style>
        body.export-page { background-color: #ffffff; font-family: Inter, sans-serif; }
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
</head>
    <body class='export-page'>
        <h2>Histórico de Manutenções de Extintores</h2>
        <table>
            <thead>
                <tr>
                    <th>Extintor</th>
                    <th>Prédio</th>
                    <th>Local</th>
                    <th>Usuário</th>
                    <th>Data de Manutenção</th>
                    <th>Cobertura</th>
                </tr>
            </thead>
            <tbody>
    ";

    // Preencher a tabela com os dados do banco de dados
    while ($row = $result->fetch_assoc()) {
        $cobertura = $row['cobertura'] == 1 ? 'Sim' : 'Não';
        $row = array_map('htmlspecialchars', $row);
        $html .= "
            <tr>
                <td>{$row['extintor_codigo']}</td>
                <td>{$row['predio']}</td>
                <td>{$row['local_exato']}</td>
                <td>{$row['usuario_nome']}</td>
                <td>" . date('d/m/Y', strtotime($row['data_manutencao'])) . "</td>
                <td>{$cobertura}</td>
            </tr>
        ";
    }

    // Fechar a tabela e o HTML
    $html .= "
            </tbody>
        </table>
    </body>
    </html>
    ";

    // Definir os headers para download do arquivo HTML
    require_once __DIR__ . '/vendor/autoload.php';
$filename = '"historico_manutencao.pdf"';

    // Imprimir o HTML para download
        // Gerar PDF com mPDF (Landscape)
    $mpdf = new \Mpdf\Mpdf(['orientation' => 'L', 'format' => 'A4', 'tempDir' => sys_get_temp_dir() . '/mpdf']);
    $mpdf->setBasePath(__DIR__);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

} else {
    // Mensagem amigável para o caso de nenhum registro ser encontrado
    echo "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Histórico de Manutenções - Nenhum Registro Encontrado</title>
        <link rel='stylesheet' href='styles.css'>
    
    <style>
        body.export-page { background-color: #ffffff; font-family: Inter, sans-serif; }
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
</head>
    <body class='export-page no-data'>
        <div class='message-box'>
            <h2>Nenhum Registro Encontrado</h2>
            <p>Não foram encontrados registros de manutenção para exportação de acordo com os critérios selecionados.</p>
            <a href='historico_manutencao.php'>Voltar para Histórico de Manutenções</a>
        </div>
        <footer class='footer mt-4'>
    <div class='container text-center'>
        <p>&copy; Sistema de Controle de Extintores</p>
    </div>
</footer>
    </body>
    </html>
    ";
}

// Fechar a conexão
$conn->close();
?>
