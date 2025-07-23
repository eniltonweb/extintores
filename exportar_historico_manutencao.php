<?php
session_start();
include '../config/db_conexao.php';

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
        <style>
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            table, th, td {
                border: 1px solid black;
            }
            th, td {
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
        </style>
    </head>
    <body>
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
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="historico_manutencao.html"');

    // Imprimir o HTML para download
    echo $html;

} else {
    // Mensagem amigável para o caso de nenhum registro ser encontrado
    echo "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Histórico de Manutenções - Nenhum Registro Encontrado</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f0f0f0;
                text-align: center;
                margin-top: 50px;
            }
            .message-box {
                background-color: #fff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                display: inline-block;
            }
            h2 {
                color: #333;
            }
            p {
                color: #666;
            }
            a {
                display: inline-block;
                margin-top: 20px;
                text-decoration: none;
                color: #27509b;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class='message-box'>
            <h2>Nenhum Registro Encontrado</h2>
            <p>Não foram encontrados registros de manutenção para exportação de acordo com os critérios selecionados.</p>
            <a href='historico_manutencao.php'>Voltar para Histórico de Manutenções</a>
        </div>
    </body>
    </html>
    ";
}

// Fechar a conexão
$conn->close();
?>
