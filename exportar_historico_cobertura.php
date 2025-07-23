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
header('Content-Disposition: attachment; filename=historico_cobertura_' . date('Y-m-d') . '.html');

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
</body>
</html>';

echo $html;

// Registrar a ação no log de auditoria
$user_id = $_SESSION['user_id'];
$action = 'Exportação de Coberturas HTML';
$details = 'Exportação de Coberturas realizada em ' . date('d-m-Y H:i:s');
auditoria($action, null, $user_id, $_SESSION['user_level'], $details);

$conn->close();
exit();
?>