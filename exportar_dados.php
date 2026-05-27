<?php
// Aumentar limites para tabelas muito grandes
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '300');

require_once __DIR__ . '/config/db_conexao.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado e se tem permissão para acessar esta página
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/auditoria.php';

// Nome do arquivo com data de exportação
$data_exportacao = date('Y-m-d_H-i-s');
$nome_arquivo = "extintores_$data_exportacao.csv";

// Definir cabeçalhos HTTP para forçar o download de arquivo CSV (Excel)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir a saída PHP como um "arquivo"
$output = fopen('php://output', 'w');

// Adicionar BOM (Byte Order Mark) do UTF-8 para que o Excel acentue corretamente ao abrir
fputs($output, "\xEF\xBB\xBF");

// Definir os cabeçalhos das colunas
$cabecalhos = [
    'Prédio', 'Código', 'Atividade', 'Local Exato', 'Tipo Extintor', 'Carga', 
    'Manutenção N2', 'Próxima Manutenção N2', 'Dias para Expirar N2', 'Inspeção Trimestral Nível 1', 
    'Selo do Inmetro', 'Sinalização Vertical', 'Sinalização Piso', 'Ficha Inspeção Trimestral', 
    'Lacre', 'Pressão Manômetro', 'Anel Identificação', 'Pesagem CO2 Semestral', 'Usuário', 'Comentários'
];
// Escrever a primeira linha (cabeçalho) separada por ponto e vírgula
fputcsv($output, $cabecalhos, ';');

// Consultar todos os dados dos extintores
$sql = "SELECT Predio, codigo, Atividade, Local_Exato, tip_extintor, carga, 
        manutencao_n2, proxima_manutencao_n2, dias_para_expirar_n2, inspecao_trimestral_nivel1, 
        selo_do_Inmetro, sinalizacao_vertical, sinalizacao_piso, ficha_inspecao_trimestral, 
        lacre, pressao_manometro, anel_identificacao, pesagem_co2_semestral, usuario, comentarios 
        FROM bd_extintores";
$result = $conn->query($sql);

// Preencher as linhas com os dados
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $linha = [];
        foreach ($row as $key => $value) {
            // Converter nulos para strings vazias e limpar quebras de linha que possam quebrar o CSV
            $val = (string)($value ?? '');
            $val = str_replace(["\r", "\n"], " ", $val);
            $linha[] = $val;
        }
        // Escrever a linha
        fputcsv($output, $linha, ';');
    }
}

// Registrar a auditoria
$user_id = $_SESSION['user_id'];
$action = 'Exportação de dados em CSV/Excel';
$details = 'Exportação completa de dados dos extintores realizada em ' . date('Y-m-d H:i:s');
registrar_auditoria($conn, $user_id, $action, $details);

$conn->close();
fclose($output);
exit();
?>
