<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';


// Verificar se a conexão ao banco de dados está configurada para usar a codificação correta
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';
$filename = 'historico_cobertura_' . date('Y-m-d') . '.pdf';

// Construir consulta SQL
$sql = "
    SELECT 
        bd_extintores.codigo, 
        bd_extintores.Predio,
        bd_extintores.Local_Exato,
        COALESCE(usuarios.username, 'Usuário removido') AS usuario_nome, 
        bd_extintores.tip_extintor,
        bd_extintores.carga, 
        bd_extintores.manutencao_n2,
        bd_extintores.proxima_manutencao_n2, 
        bd_extintores.dias_para_expirar_n2,
        bd_extintores.cobertura
    FROM 
        bd_extintores
    LEFT JOIN 
        usuarios ON bd_extintores.usuario = usuarios.id
    WHERE 
        bd_extintores.manutencao_n2 IS NOT NULL 
        AND bd_extintores.cobertura >= '1'
";

$result = $conn->query($sql);

// Iniciar a geração do conteúdo HTML
$html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Coberturas</title>
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
        <h2 class="text-center">Relatório de Coberturas</h2>
		</div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Código do Extintor</th>
                        <th>Prédio</th>
                        <th>Local Exato</th>
                        <th>Usuário</th>
                        <th>Tipo de Extintor</th>
                        <th>Carga</th>
                        <th>Manutenção N2</th>
                        <th>Próxima Manutenção N2</th>
                        <th>Dias para Expirar N2</th>
                        <th>Cobertura</th>
                    </tr>
                </thead>
                <tbody>';

while ($row = $result->fetch_assoc()) {
    $row = array_map('htmlspecialchars', $row);
    $html .= '<tr>
                <td>' . $row['codigo'] . '</td>
                <td>' . $row['Predio'] . '</td>
                <td>' . $row['Local_Exato'] . '</td>
                <td>' . $row['usuario_nome'] . '</td>
                <td>' . $row['tip_extintor'] . '</td>
                <td>' . $row['carga'] . '</td>
                <td>' . $row['manutencao_n2'] . '</td>
                <td>' . $row['proxima_manutencao_n2'] . '</td>
                <td>' . $row['dias_para_expirar_n2'] . '</td>
                <td>' . $row['cobertura'] . '</td>
            </tr>';
}

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

    // Gerar PDF com mPDF (Landscape)
    $mpdf = new \Mpdf\Mpdf(['orientation' => 'L', 'format' => 'A4', 'tempDir' => sys_get_temp_dir() . '/mpdf']);
    $mpdf->setBasePath(__DIR__);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

// Registrar a ação no log de auditoria
$user_id = $_SESSION['user_id'];
$action = 'Exportação de Coberturas HTML';
$details = 'Exportação de Coberturas realizada em ' . date('d-m-Y H:i:s');
auditoria($action, null, $user_id, $_SESSION['user_level'], $details);

$conn->close();
exit();
?>