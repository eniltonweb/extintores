<?php
// Registrar a exportação no log de auditoria
function registrar_auditoria($conn, $user_id, $action, $details) {
    $sql = "INSERT INTO auditoria_logs (user_id, action, detalhes) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// Verificar se o script está sendo executado diretamente e não incluído
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    require_once __DIR__ . '/config/db_conexao.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar se o usuário está logado e se tem permissão para acessar esta página
    if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
        header('Location: index.php');
        exit();
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
    <title>Exportação de Extintores</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="export-page">
    <div class="container mt-5">
        <h2 class="text-center">Listagem de Extintores</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Prédio</th>
                    <th>Código</th>
                    <th>Atividade</th>
                    <th>Local Exato</th>
                    <th>Tipo Extintor</th>
                    <th>Carga</th>
                    <th>Manutenção N2</th>
                    <th>Próxima Manutenção N2</th>
                    <th>Dias para Expirar N2</th>
                    <th>Inspeção Trimestral Nível 1</th>
                    <th>Selo do Inmetro</th>
                    <th>Sinalização Vertical</th>
                    <th>Sinalização Piso</th>
                    <th>Ficha Inspeção Trimestral</th>
                    <th>Lacre</th>
                    <th>Pressão Manômetro</th>
                    <th>Anel Identificação</th>
                    <th>Pesagem CO2 Semestral</th>
                    <th>Usuário</th>
                    <th>Comentários</th>
                </tr>
            </thead>
            <tbody>';

// Consultar os dados dos extintores
$sql = "SELECT Predio, codigo, Atividade, Local_Exato, tip_extintor, carga, 
        manutencao_n2, proxima_manutencao_n2, dias_para_expirar_n2, inspecao_trimestral_nivel1, 
        selo_do_Inmetro, sinalizacao_vertical, sinalizacao_piso, ficha_inspecao_trimestral, 
        lacre, pressao_manometro, anel_identificacao, pesagem_co2_semestral, usuario, comentarios 
        FROM bd_extintores";
$result = $conn->query($sql);

// Preencher a tabela com os dados dos extintores
while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    foreach ($row as $value) {
        echo '<td>' . htmlspecialchars($value) . '</td>';
    }
    echo '</tr>';
}

echo '        </tbody>
        </table>
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
}
?>
