<?php
include '../config/db_conexao.php';
session_start();

// Verificar se o usuário está logado e se tem permissão
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Registrar a exportação no log de auditoria
function registrar_auditoria($conn, $user_id, $action, $details) {
    $sql = "INSERT INTO auditoria_logs (user_id, action, detalhes) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// Nome do arquivo com data de exportação
$data_exportacao = date('Y-m-d_H-i-s');
$nome_arquivo = "extintores_$data_exportacao.html";

// Cabeçalhos HTTP para download do arquivo HTML
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $nome_arquivo);

// Início da exportação HTML
echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Extintores - [Nome da Empresa]</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            max-width: 200px; /* Ajuste o tamanho conforme necessário */
            height: auto;
        }
        .table thead th {
            background-color: #004c97;
            color: white;
            text-align: center;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #f2f2f2;
        }
        .table tbody tr:nth-child(even) {
            background-color: #e6e6e6;
        }
        .table tbody tr:hover {
            background-color: #ffcc00;
        }
        .text-center {
            text-align: center;
        }
        .proximo-vencimento {
            background-color: #FFFF00; /* Amarelo */
        }
        .vencido {
            background-color: #FF0000; /* Vermelho */
            color: white;
        }
        .mb-10{
          margin-bottom: 30px;
        }
        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            background-color: #f8f9fa;
            text-align: center;
            padding: 10px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="container mt-3">
        <div class="header">
            <img src="https://th.bing.com/th/id/R.ef057f57dcc9d793dba44afa229d81bb?rik=xhl9Y3NNxQmgQA&pid=ImgRaw&r=0" alt="Logo da Empresa">
            <h2 class="mb-10">Relatório de Inspeção de Extintores</h2>
            <p class="text-muted">Gerado em: ' . date('Y-m-d H:i:s') . '</p>
        </div>';

// Consultar os dados dos extintores
$sql = "SELECT Predio, codigo, Atividade, Local_Exato, tip_extintor, carga, 
        manutencao_n2, proxima_manutencao_n2, dias_para_expirar_n2, inspecao_trimestral_nivel1, 
        selo_do_Inmetro, sinalizacao_vertical, sinalizacao_piso, ficha_inspecao_trimestral, 
        lacre, pressao_manometro, anel_identificacao, pesagem_co2_semestral, usuario, comentarios 
        FROM bd_extintores ORDER BY Predio, codigo";
$result = $conn->query($sql);

// Variáveis para controle de quebra de página e formatação
$predio_atual = null;
$contador = 0;

// Preencher a tabela com os dados dos extintores
while ($row = $result->fetch_assoc()) {
    // Quebra de página a cada novo prédio
    if ($row['Predio'] != $predio_atual) {
        if ($predio_atual !== null) {
            echo '</tbody>
                  </table>
                </div>'; // Fechando tabela e container anterior
        }
        echo '<div class="container mt-3">
                <h3 class="text-center">Prédio ' . htmlspecialchars($row['Predio']) . ' - ' . htmlspecialchars($row['Atividade']) . '</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Local Exato</th>
                            <th>Tipo Extintor</th>
                            <th>Carga</th>
                            <th>Próxima Manutenção N2</th>
                            <th>Dias para Expirar N2</th>
                            <th>Selo do Inmetro</th>
                            <th>Sinalização Vertical</th>
                            <th>Sinalização Piso</th>
                            <th>Comentários</th>
                        </tr>
                    </thead>
                    <tbody>';
        $predio_atual = $row['Predio'];
        $contador = 0;
    }

    // Formatação condicional para datas próximas do vencimento
    $classe_vencimento = '';
    if (!empty($row['proxima_manutencao_n2'])) {
        $data_proxima_manutencao = strtotime($row['proxima_manutencao_n2']);
        $dias_para_expirar = intval($row['dias_para_expirar_n2']);

        if ($dias_para_expirar <= 30 && $dias_para_expirar >= 0) {
            $classe_vencimento = 'proximo-vencimento';
        } elseif ($dias_para_expirar < 0) {
            $classe_vencimento = 'vencido';
        }
    }

    echo '<tr>
            <td>' . htmlspecialchars($row['codigo']) . '</td>
            <td>' . htmlspecialchars($row['Local_Exato']) . '</td>
            <td>' . htmlspecialchars($row['tip_extintor']) . '</td>
            <td>' . htmlspecialchars($row['carga']) . '</td>
            <td class="' . $classe_vencimento . '">' . htmlspecialchars($row['proxima_manutencao_n2']) . '</td>
            <td class="' . $classe_vencimento . '">' . htmlspecialchars($row['dias_para_expirar_n2']) . '</td>
            <td>' . htmlspecialchars($row['selo_do_Inmetro']) . '</td>
            <td>' . htmlspecialchars($row['sinalizacao_vertical']) . '</td>
            <td>' . htmlspecialchars($row['sinalizacao_piso']) . '</td>
            <td>' . htmlspecialchars($row['comentarios']) . '</td>
          </tr>';

    $contador++;
}

// Fechar a última tabela
if ($predio_atual !== null) {
    echo '</tbody>
          </table>
        </div>';
}

echo '<div class="footer">
        Chama Gaucha - Relatório Gerado em ' . date('Y-m-d H:i:s') . '
    </div>
</body>
</html>';

// Registrar a auditoria
$user_id = $_SESSION['user_id'];
$action = 'Exportação de dados';
$details = 'Exportação de dados dos extintores realizada em ' . date('Y-m-d H:i:s');
registrar_auditoria($conn, $user_id, $action, $details);

$conn->close();
exit();
?>