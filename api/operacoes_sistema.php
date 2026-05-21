<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

include 'auth_api.php';
$usuario_logado = validarTokenAPI();

include '../../config/db_conexao.php';
$conn->set_charset("utf8mb4");

$operator_id = (int)($usuario_logado['user_id'] ?? 0);
$username_operator = $usuario_logado['username'] ?? 'Usuario';
$nivel_permissao = trim(strtolower($usuario_logado['nivel_acesso'] ?? 'bombeiro'));

$dados = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $dados['action'] ?? '';

// Função para espelhar auditoria igual à Web
function registrarAuditoria($conn, $user_id, $level, $action_msg, $extintor_id = null, $detalhes = null) {
    $sql = "INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, data_hora, detalhes) VALUES (?, ?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issis', $user_id, $level, $action_msg, $extintor_id, $detalhes);
    $stmt->execute();
    $stmt->close();
}

// 1. DASHBOARD COMPLETO
if ($action === 'dashboard_completo') {
    $stats = ['total' => 0, 'inspecionados' => 0, 'manutencao' => 0, 'vencidos' => 0];
    
    $res = $conn->query("SELECT COUNT(*) as qtd FROM bd_extintores");
    if($row = $res->fetch_assoc()) $stats['total'] = (int)$row['qtd'];
    
    $res = $conn->query("SELECT COUNT(*) as qtd FROM bd_extintores WHERE status_aprovacao = 'Aprovado'");
    if($row = $res->fetch_assoc()) $stats['inspecionados'] = (int)$row['qtd'];
    
    $res = $conn->query("SELECT COUNT(*) as qtd FROM bd_extintores WHERE status_aprovacao = 'Em espera'");
    if($row = $res->fetch_assoc()) $stats['manutencao'] = (int)$row['qtd'];
    
    $res = $conn->query("SELECT COUNT(*) as qtd FROM bd_extintores WHERE dias_para_expirar_n2 < 0");
    if($row = $res->fetch_assoc()) $stats['vencidos'] = (int)$row['qtd'];

    echo json_encode(["status" => "sucesso", "stats" => $stats, "nivel_acesso" => $nivel_permissao]);
    $conn->close(); exit();
}

// 2. BUSCAR DADOS DO EXTINTOR PARA O APP
if ($action === 'obter_extintor') {
    $codigo = filter_var($_GET['codigo'] ?? '', FILTER_SANITIZE_STRING);
    $stmt = $conn->prepare("SELECT Predio, codigo, Atividade, Local_Exato, tip_extintor, carga, selo_do_Inmetro, pesagem_co2_semestral, comentarios FROM bd_extintores WHERE codigo = ?");
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ext = $result->fetch_assoc();
        echo json_encode(["status" => "sucesso", "dados" => [
            "predio" => $ext['Predio'],
            "codigo" => $ext['codigo'],
            "atividade" => $ext['Atividade'],
            "local_exato" => $ext['Local_Exato'],
            "tip_extintor" => $ext['tip_extintor'],
            "carga" => $ext['carga'],
            "selo" => $ext['selo_do_Inmetro'],
            "peso_anterior" => $ext['pesagem_co2_semestral'],
            "comentarios" => $ext['comentarios']
        ]]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Extintor (".$codigo.") não localizado na base de dados."]);
    }
    $stmt->close(); $conn->close(); exit();
}

// 3. INVENTÁRIO COM BUSCA E PAGINAÇÃO (MUITO MAIS RÁPIDO)
if ($action === 'inventario_filtrado') {
    $busca = $_GET['busca'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 30);
    $offset = ($page - 1) * $limit;
    
    $queryStr = "SELECT codigo, Predio, Local_Exato, status_aprovacao, dias_para_expirar_n2 FROM bd_extintores";
    if (!empty($busca)) { $queryStr .= " WHERE Predio LIKE ? OR codigo LIKE ?"; }
    $queryStr .= " ORDER BY codigo ASC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($queryStr);
    if (!empty($busca)) {
        $paramBusca = "%".$busca."%";
        $stmt->bind_param('ssii', $paramBusca, $paramBusca, $limit, $offset);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $dados = [];
    while($row = $result->fetch_assoc()) {
        $dados[] = [
            "codigo" => $row['codigo'],
            "predio" => $row['Predio'],
            "local" => $row['Local_Exato'],
            "status" => $row['status_aprovacao'],
            "vencido" => ((int)$row['dias_para_expirar_n2'] < 0)
        ];
    }
    echo json_encode(["status" => "sucesso", "dados" => $dados]);
    $stmt->close(); $conn->close(); exit();
}

// 4. CADASTRAR NOVO EXTINTOR
if ($action === 'cadastrar_extintor') {
    if ($nivel_permissao !== 'admin' && $nivel_permissao !== 'administrador') { 
        echo json_encode(["status" => "erro", "mensagem" => "Acesso restrito."]); exit(); 
    }
    $codigo = filter_var($dados['codigo'] ?? '', FILTER_SANITIZE_STRING);
    $predio = filter_var($dados['predio'] ?? '', FILTER_SANITIZE_STRING);
    $atividade = filter_var($dados['atividade'] ?? '', FILTER_SANITIZE_STRING);
    $local_exato = filter_var($dados['local_exato'] ?? '', FILTER_SANITIZE_STRING);
    $tip = filter_var($dados['tip_extintor'] ?? 'PQS', FILTER_SANITIZE_STRING);
    $carga = filter_var($dados['carga'] ?? '6KG', FILTER_SANITIZE_STRING);

    if(empty($codigo) || empty($predio)) { echo json_encode(["status" => "erro", "mensagem" => "Código e Prédio são obrigatórios."]); exit(); }

    $sql = "INSERT INTO bd_extintores (Predio, codigo, Atividade, Local_Exato, tip_extintor, carga, status_aprovacao, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Aprovado', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssss', $predio, $codigo, $atividade, $local_exato, $tip, $carga);
    
    if ($stmt->execute()) {
        $last_id = $conn->insert_id;
        registrarAuditoria($conn, $operator_id, $nivel_permissao, "Cadastro de Extintor", $last_id, "Inclusão do equipamento código: " . $codigo);
        echo json_encode(["status" => "sucesso", "mensagem" => "Extintor cadastrado com sucesso!"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Código já existe ou falha no banco."]);
    }
    $stmt->close(); $conn->close(); exit();
}

// 5. CONTROLE DE EQUIPES E USUÁRIOS
if ($action === 'listar_usuarios') {
    $result = $conn->query("SELECT id, username, nivel_acesso FROM usuarios ORDER BY username ASC");
    $lista = [];
    while($row = $result->fetch_assoc()) { $lista[] = $row; }
    echo json_encode(["status" => "sucesso", "usuarios" => $lista]);
    $conn->close(); exit();
}

if ($action === 'criar_usuario') {
    if ($nivel_permissao !== 'admin' && $nivel_permissao !== 'administrador') { echo json_encode(["status" => "erro", "mensagem" => "Acesso restrito."]); exit(); }
    $user_new = filter_var($dados['username'] ?? '', FILTER_SANITIZE_STRING);
    $pass_new = password_hash($dados['password'] ?? '', PASSWORD_BCRYPT);
    $nivel_new = filter_var($dados['nivel_acesso'] ?? 'bombeiro', FILTER_SANITIZE_STRING);

    $sql = "INSERT INTO usuarios (username, password, nivel_acesso, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $user_new, $pass_new, $nivel_new);
    if($stmt->execute()) echo json_encode(["status" => "sucesso", "mensagem" => "Operador salvo na base."]);
    else echo json_encode(["status" => "erro", "mensagem" => "Usuário já existe."]);
    $stmt->close(); $conn->close(); exit();
}

if ($action === 'deletar_usuario') {
    if ($nivel_permissao !== 'admin' && $nivel_permissao !== 'administrador') { echo json_encode(["status" => "erro", "mensagem" => "Acesso restrito."]); exit(); }
    $id_del = (int)($dados['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ? AND id != ?");
    $stmt->bind_param('ii', $id_del, $operator_id);
    if($stmt->execute()) echo json_encode(["status" => "sucesso", "mensagem" => "Removido com sucesso."]);
    $stmt->close(); $conn->close(); exit();
}

// 6. HISTÓRICO GERAL / AUDITORIA DE LOGS
if ($action === 'historico_completo') {
    $sql = "SELECT l.action, l.data_hora, l.detalhes, u.username, e.codigo 
            FROM auditoria_logs l 
            LEFT JOIN usuarios u ON l.user_id = u.id 
            LEFT JOIN bd_extintores e ON l.extintor_id = e.id 
            ORDER BY l.data_hora DESC LIMIT 100";
    $result = $conn->query($sql);
    $logs = [];
    while($row = $result->fetch_assoc()) {
        $logs[] = [
            "codigo" => $row['codigo'] ?: "Sistema",
            "usuario" => $row['username'] ?: "N/A",
            "action" => $row['action'],
            "data" => date('d/m/Y H:i', strtotime($row['data_hora'])),
            "status" => $row['detalhes']
        ];
    }
    echo json_encode(["status" => "sucesso", "dados" => $logs]);
    $conn->close(); exit();
}

// 7. CONTROLE DE MANUTENÇÃO E APROVAÇÃO (FLUXO WEB)
if (in_array($action, ['liberar_manutencao', 'aprovar_retorno', 'rejeitar_extintor'])) {
    $codigo = filter_var($dados['codigo'] ?? '', FILTER_SANITIZE_STRING);
    $res = $conn->query("SELECT id FROM bd_extintores WHERE codigo = '$codigo'");
    if ($res->num_rows === 0) { echo json_encode(["status" => "erro", "mensagem" => "Extintor não localizado."]); exit(); }
    $ext = $res->fetch_assoc(); 
    $ext_id = $ext['id'];

    if ($action === 'liberar_manutencao') {
        $motivo = filter_var($dados['motivo'] ?? 'Saída para Manutenção Externa', FILTER_SANITIZE_STRING);
        $sql = "UPDATE bd_extintores SET status_aprovacao = 'Em espera', comentarios = ?, usuario = ? WHERE id = ?";
        $stmt = $conn->prepare($sql); $stmt->bind_param('ssi', $motivo, $username_operator, $ext_id); $stmt->execute(); $stmt->close();
        registrarAuditoria($conn, $operator_id, $nivel_permissao, "Ordem de Saída (Oficina)", $ext_id, "Motivo: " . $motivo);
        echo json_encode(["status" => "sucesso", "mensagem" => "Status alterado para 'Em espera' e liberado para manutenção."]);
    }
    
    if ($action === 'aprovar_retorno') {
        $sql = "UPDATE bd_extintores SET status_aprovacao = 'Aprovado', comentarios = 'Retorno Homologado', usuario = ? WHERE id = ?";
        $stmt = $conn->prepare($sql); $stmt->bind_param('si', $username_operator, $ext_id); $stmt->execute(); $stmt->close();
        registrarAuditoria($conn, $operator_id, $nivel_permissao, "Aprovação de Retorno", $ext_id, "Equipamento reintegrado.");
        echo json_encode(["status" => "sucesso", "mensagem" => "Equipamento aprovado e reintegrado à fábrica."]);
    }

    if ($action === 'rejeitar_extintor') {
        $sql = "UPDATE bd_extintores SET status_aprovacao = 'Em espera', comentarios = 'Reprovado em Inspeção', usuario = ? WHERE id = ?";
        $stmt = $conn->prepare($sql); $stmt->bind_param('si', $username_operator, $ext_id); $stmt->execute(); $stmt->close();
        registrarAuditoria($conn, $operator_id, $nivel_permissao, "Rejeição em Campo", $ext_id, "Equipamento bloqueado pelo Bombeiro.");
        echo json_encode(["status" => "sucesso", "mensagem" => "Equipamento reprovado e retido."]);
    }
    $conn->close(); exit();
}

echo json_encode(["status" => "erro", "mensagem" => "Rota não reconhecida."]);
?>