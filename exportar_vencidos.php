<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';


// Configuração de charset
$conn->set_charset("utf8mb4");

// Verificar se o usuário está autenticado e autorizado
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Capturar os dias para filtrar extintores vencidos
$dias = filter_input(INPUT_GET, 'dias', FILTER_SANITIZE_NUMBER_INT) ?: 30;

// Definir os cabeçalhos para exportar o arquivo HTML
require_once __DIR__ . '/vendor/autoload.php';
$filename = 'Extintores_vencidos_' . $dias . '.pdf';

// Construir a consulta SQL para obter os extintores vencidos
$sql = "SELECT * FROM bd_extintores WHERE dias_para_expirar_n2 <= ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $dias);
$stmt->execute();
$result = $stmt->get_result();

// Iniciar a geração do HTML para o relatório
$html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Extintores Vencidos</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">

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
<body class="export-page">
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="http://www.enilton.com.br/img/michelin_logo2.png" alt="Michelin Logo" class="header-img" style="max-width: 150px; margin-bottom: 15px;">
            <h2 class="text-center">Relatório de Extintores Vencidos</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Código do Extintor</th>
                        <th>Prédio</th>
                        <th>Local</th>
                        <th>Validade da Manutenção</th>
                        <th>Dias para Expirar N2</th>
                    </tr>
                </thead>
                <tbody>';

// Iterar sobre os resultados da consulta e preencher o HTML
$linhas_html = [];
while ($row = $result->fetch_assoc()) {
    // Garantir que valores nulos sejam substituídos por strings vazias
    foreach ($row as $key => $value) {
        if (is_null($value)) {
            $row[$key] = '';
        }
    }

    // Formatar a data da próxima manutenção para o formato d-m-Y
    $row['proxima_manutencao_n2'] = date_format(date_create($row['proxima_manutencao_n2']), 'd-m-Y');

    // Proteger contra XSS
    $row = array_map('htmlspecialchars', $row);

    // Gerar as linhas da tabela
    $linhas_html[] = "<tr>
                <td>{$row['codigo']}</td>
                <td>{$row['Predio']}</td>
                <td>{$row['Local_Exato']}</td>
                <td>{$row['proxima_manutencao_n2']}</td>
                <td>{$row['dias_para_expirar_n2']}</td>
            </tr>";
}
$html .= implode('', $linhas_html);

// Fechar o HTML
$html .= '</tbody>
            </table>
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>';

// Exibir o HTML gerado
    // Gerar PDF com mPDF (Landscape)
    $mpdf = new \Mpdf\Mpdf(['orientation' => 'L', 'format' => 'A4', 'tempDir' => sys_get_temp_dir() . '/mpdf']);
    $mpdf->setBasePath(__DIR__);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

// Registrar a ação no log de auditoria
$user_id = $_SESSION['user_id'];
$action = 'Exportação de Extintores Vencidos HTML';
$details = 'Exportação de Extintores Vencidos realizada em ' . date('d-m-Y H:i:s');
auditoria($action, null, $user_id, $_SESSION['user_level'], $details);

// Fechar a conexão com o banco de dados
$stmt->close();
$conn->close();
exit();
?>