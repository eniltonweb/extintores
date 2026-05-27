<?php
// Define o cabeçalho para retornar JSON
header('Content-Type: application/json; charset=utf-8');

// Configuração de segurança: Token da API (mude para uma senha forte)
// O Power BI precisará enviar este token na URL para acessar os dados
$token_secreto = 'michelin_bsi';

// Verifica se o token enviado na URL está correto
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) {
    http_response_code(401);
    echo json_encode(['erro' => 'Acesso não autorizado. Token inválido.']);
    exit();
}

// CORREÇÃO AQUI: '../' para voltar uma pasta e achar o config
require_once __DIR__ . '/../config/db_conexao.php';

// Ajusta o charset para evitar problemas com acentos no Power BI
$conn->set_charset("utf8mb4");

// Consulta para buscar todos os extintores e calcular status dinâmicos
$sql = "
    SELECT 
        e.id,
        e.codigo,
        e.Predio,
        e.Local_Exato,
        e.Atividade,
        e.tip_extintor,
        e.carga,
        e.status_aprovacao,
        e.selo_do_Inmetro,
        e.cobertura,
        e.inspecao_trimestral_nivel1 AS ultima_inspecao_n1,
        e.manutencao_n2 AS ultima_manutencao_n2,
        e.proxima_manutencao_n2,
        e.dias_para_expirar_n2,
        CASE 
            WHEN e.dias_para_expirar_n2 < 0 THEN 'Vencido'
            WHEN e.dias_para_expirar_n2 BETWEEN 0 AND 30 THEN 'Vence em 30 dias'
            WHEN e.dias_para_expirar_n2 IS NULL THEN 'Sem Data'
            ELSE 'Válido'
        END AS status_vencimento,
        CASE 
            WHEN e.cobertura = 1 THEN 'Em Cobertura (Substituto)'
            WHEN e.status_aprovacao = 'Em espera' THEN 'Aguardando Aprovação'
            ELSE 'Em Área'
        END AS status_localizacao
    FROM bd_extintores e
";

$result = $conn->query($sql);

$dados_powerbi = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Formatar valores nulos ou vazios para não quebrar o Power BI
        foreach ($row as $key => $value) {
            if ($value === null) {
                $row[$key] = "";
            }
        }
        $dados_powerbi[] = $row;
    }
}

// Fechar conexão
$conn->close();

// Retornar os dados em formato JSON
echo json_encode($dados_powerbi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>