<?php
// Registrar a exportação no log de auditoria
require_once __DIR__ . '/auditoria.php';

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    session_start();
    require_once __DIR__ . '/config/db_conexao.php';

    // Verificar se o usuário está logado e se tem permissão para acessar esta página
    if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
        header('Location: index.php');
        exit();
    }

    require_once __DIR__ . '/vendor/autoload.php';
    $filename = 'historico_inspecao_' . date('Y-m-d_H:i:s') . '.pdf';

    // Construir consulta SQL
    $sql = "
        SELECT
            bd_extintores.codigo AS extintor_codigo,
            bd_extintores.Local_Exato AS local_exato,
            bd_extintores.Predio AS predio,
            COALESCE(bd_extintores.usuario, 'Usuário removido') AS usuario_nome,
            bd_extintores.tip_extintor AS tipo_extintor,
            bd_extintores.inspecao_trimestral_nivel1 AS data_inspecao,
            bd_extintores.selo_do_Inmetro,
            bd_extintores.sinalizacao_vertical,
            bd_extintores.sinalizacao_piso,
            bd_extintores.ficha_inspecao_trimestral,
            bd_extintores.lacre,
            bd_extintores.pressao_manometro,
            bd_extintores.anel_identificacao,
            bd_extintores.pesagem_co2_semestral
        FROM
            bd_extintores
        LEFT JOIN
            usuarios ON bd_extintores.usuario = usuarios.id
        WHERE
            bd_extintores.inspecao_trimestral_nivel1 IS NOT NULL
            AND bd_extintores.inspecao_trimestral_nivel1 >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
    ";

    $result = $conn->query($sql);

    // Iniciar a geração do conteúdo HTML
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Histórico de inspeções</title>
    
    

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
            <h2 class="text-center">Relatório Inspeção de Nível 1</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Código do Extintor</th>
                        <th>Local Exato</th>
                        <th>Prédio</th>
                        <th>Usuário</th>
                        <th>Tipo de Extintor</th>
                        <th>Inspeção Trimestral Nivel 1</th>
                        <th>Selo do Inmetro</th>
                        <th>Sinalização Vertical</th>
                        <th>Sinalização Piso</th>
                        <th>Ficha de Inspeçao Trimestral</th>
                        <th>Lacre</th>
                        <th>Pressão do Mamometro</th>
                        <th>Anel de Identificação</th>
                        <th>Pesagem Semestral Co2</th>
                    </tr>
                </thead>
                <tbody>';

    while ($row = $result->fetch_assoc()) {
        $row = array_map('htmlspecialchars', $row);

        // Formatando a data para d-m-Y
        $row['data_inspecao'] = date_format(date_create($row['data_inspecao']), 'd-m-Y');

        $html .= '<tr>
                    <td>' . $row['extintor_codigo'] . '</td>
                    <td>' . $row['local_exato'] . '</td>
                    <td>' . $row['predio'] . '</td>
                    <td>' . $row['usuario_nome'] . '</td>
                    <td>' . $row['tipo_extintor'] . '</td>
                    <td>' . $row['data_inspecao'] . '</td>
                    <td>' . $row['selo_do_Inmetro'] . '</td>
                    <td>' . $row['sinalizacao_vertical'] . '</td>
                    <td>' . $row['sinalizacao_piso'] . '</td>
                    <td>' . $row['ficha_inspecao_trimestral'] . '</td>
                    <td>' . $row['lacre'] . '</td>
                    <td>' . $row['pressao_manometro'] . '</td>
                    <td>' . $row['anel_identificacao'] . '</td>
                    <td>' . $row['pesagem_co2_semestral'] . '</td>
                </tr>';
    }

    $html .= '</tbody>
            </table>
        </div>
    </div>
    
    
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
    // Registrar a auditoria
    $user_id = $_SESSION['user_id'];
    $action = 'Exportação de inspeções';
    $details = 'Exportação inspeções realizada em ' . date('Y-m-d H:i:s');
    registrar_auditoria($conn, $user_id, $action, $details);

    $conn->close();
    exit();
}
?>