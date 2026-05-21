<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { header("Access-Control-Allow-Methods: POST, OPTIONS"); header("Access-Control-Allow-Headers: Authorization, Content-Type"); exit(0); }

include 'auth_api.php';
$usuario_logado = validarTokenAPI();

include '../../config/db_conexao.php';
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = json_decode(file_get_contents('php://input'), true);
    $codigo = filter_var($dados['codigo'] ?? '', FILTER_SANITIZE_STRING);
    
    if (empty($codigo)) { echo json_encode(["status" => "erro", "mensagem" => "Código ausente."]); exit(); }

    // Upload de Foto via App Mobile para a pasta uploads da Web
    $nome_foto_final = null;
    if (!empty($dados['foto_base64'])) {
        $imgData = base64_decode($dados['foto_base64']);
        $diretorio = '../../uploads/';
        if (!is_dir($diretorio)) { mkdir($diretorio, 0755, true); }
        $nome_foto_final = "inspecao_" . $codigo . "_" . time() . ".jpg";
        file_put_contents($diretorio . $nome_foto_final, $imgData);
    }

    $selo = filter_var($dados['selo_do_Inmetro'] ?? '', FILTER_SANITIZE_STRING);
    $s_vert = filter_var($dados['sinalizacao_vertical'] ?? 'OK', FILTER_SANITIZE_STRING);
    $s_piso = filter_var($dados['sinalizacao_piso'] ?? 'OK', FILTER_SANITIZE_STRING);
    $ficha = filter_var($dados['ficha_inspecao_trimestral'] ?? 'OK', FILTER_SANITIZE_STRING);
    $lacre = filter_var($dados['lacre'] ?? 'OK', FILTER_SANITIZE_STRING);
    $pressao = filter_var($dados['pressao_manometro'] ?? 'OK', FILTER_SANITIZE_STRING);
    $anel = filter_var($dados['anel_identificacao'] ?? 'OK', FILTER_SANITIZE_STRING);
    $peso_co2 = filter_var($dados['pesagem_co2_semestral'] ?? '0', FILTER_SANITIZE_STRING);
    $comentarios = filter_var($dados['comentarios'] ?? '', FILTER_SANITIZE_STRING);
    $username = $usuario_logado['username'] ?? 'Bombeiro';
    $operator_id = (int)$usuario_logado['user_id'];

    $res = $conn->query("SELECT id FROM bd_extintores WHERE codigo = '$codigo'");
    if($res->num_rows === 0) { echo json_encode(["status" => "erro", "mensagem" => "Extintor não existe na base."]); exit(); }
    $ext = $res->fetch_assoc(); $ext_id = $ext['id'];

    // Prepara a Query mantendo a foto antiga se uma nova não for enviada
    $sql_foto_part = $nome_foto_final ? ", foto = '$nome_foto_final'" : "";

    $sql = "UPDATE bd_extintores SET 
            selo_do_Inmetro = ?, sinalizacao_vertical = ?, sinalizacao_piso = ?, 
            ficha_inspecao_trimestral = ?, lacre = ?, pressao_manometro = ?, 
            anel_identificacao = ?, pesagem_co2_semestral = ?, comentarios = ?, 
            usuario = ?, status_aprovacao = 'Aprovado', inspecao_trimestral_nivel1 = NOW() $sql_foto_part
            WHERE codigo = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssssss', $selo, $s_vert, $s_piso, $ficha, $lacre, $pressao, $anel, $peso_co2, $comentarios, $username, $codigo);
    
    if ($stmt->execute()) {
        $sql_log = "INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, data_hora, detalhes) VALUES (?, 'bombeiro', 'Inspeção de Nível 1 Realizada (App)', ?, NOW(), 'Inspeção Mobile OK.')";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param('ii', $operator_id, $ext_id);
        $stmt_log->execute(); $stmt_log->close();

        echo json_encode(["status" => "sucesso", "mensagem" => "Checklist trimestral gravado e auditado com sucesso!"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Falha no banco ao gravar checklist."]);
    }
    $stmt->close(); $conn->close(); exit();
}
?>