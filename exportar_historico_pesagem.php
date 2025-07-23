<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar se a conexão ao banco de dados está configurada para usar a codificação correta
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename=historico_pesagem_' . date('Y-m-d') . '.html');

// Construir consulta SQL
$sql = "
    SELECT 
        bd_extintores.codigo AS extintor_codigo, 
        bd_extintores.Local_Exato AS local_exato,
        bd_extintores.Predio AS predio,
        COALESCE(bd_extintores.usuario, 'Usuário removido') AS usuario_nome, 
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
        AND bd_extintores.pesagem_co2_semestral IS NOT NULL
";

$result = $conn->query($sql);

// Iniciar a geração do conteúdo HTML
$html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Pesagem</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .table th {
            background-color: #0056b3;
            color: white;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .header-img {
            width: 300px;
            height: auto;
        }
        h2 {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-4">
            <img src="http://www.enilton.com.br/img/michelin_logo2.png" alt="Michelin Logo" class="header-img">
            <h2 class="text-center">Relatório de Pesagem</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Código do Extintor</th>
                        <th>Prédio</th>
                        <th>Local Exato</th>
                        <th>Usuário</th>
                        <th>Data da Inspeção</th>
                        <th>Selo do Inmetro</th>
                        <th>Sinalização Vertical</th>
                        <th>Sinalização no Piso</th>
                        <th>Ficha de Inspeção Trimestral</th>
                        <th>Lacre</th>
                        <th>Pressão do Manômetro</th>
                        <th>Anel de Identificação</th>
                        <th>Pesagem CO2 Semestral</th>
                    </tr>
                </thead>
                <tbody>';

while ($row = $result->fetch_assoc()) {
    $row = array_map('htmlspecialchars', $row);
    $html .= '<tr>
                <td>' . $row['extintor_codigo'] . '</td>
                <td>' . $row['predio'] . '</td>
                <td>' . $row['local_exato'] . '</td>
                <td>' . $row['usuario_nome'] . '</td>
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
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>';

echo $html;

// Registrar a ação no log de auditoria
$user_id = $_SESSION['user_id'];
$action = 'Exportação de Pesagem HTML';
$details = 'Exportação de Pesagem realizada em ' . date('d-m-Y H:i:s');
auditoria($action, null, $user_id, $_SESSION['user_level'], $details);


$conn->close();
exit();
?>