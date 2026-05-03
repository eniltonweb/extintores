<?php
session_start();

require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php'; // Incluindo o arquivo que contém a função auditoria


if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'bombeiro') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Obter o ID do usuário da sessão

// Preparar a consulta SQL para obter o nome de usuário
$sql = "SELECT username FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);

// Buscar o resultado da consulta
if ($stmt->fetch()) {
    // Definir a variável de sessão com o nome de usuário
    $_SESSION['username'] = $username;
    $stmt->close();
} else {
    echo "Usuário não encontrado.";
    $stmt->close();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = urlencode("Erro de validação: Token CSRF inválido.");
        header('Location: formulario_inspecao.php?codigo=' . urlencode($codigo) . '&message=' . $error_msg);
        exit();
    }

    $Local_Exato = filter_input(INPUT_POST, 'Local_Exato', FILTER_SANITIZE_SPECIAL_CHARS); // Novo campo
    $selo_do_Inmetro = filter_input(INPUT_POST, 'selo_do_Inmetro', FILTER_SANITIZE_SPECIAL_CHARS);
    $sinalizacao_vertical = filter_input(INPUT_POST, 'sinalizacao_vertical', FILTER_SANITIZE_SPECIAL_CHARS);
    $sinalizacao_piso = filter_input(INPUT_POST, 'sinalizacao_piso', FILTER_SANITIZE_SPECIAL_CHARS);
    $ficha_inspecao_trimestral = filter_input(INPUT_POST, 'ficha_inspecao_trimestral', FILTER_SANITIZE_SPECIAL_CHARS);
    $lacre = filter_input(INPUT_POST, 'lacre', FILTER_SANITIZE_SPECIAL_CHARS);
    $pressao_manometro = filter_input(INPUT_POST, 'pressao_manometro', FILTER_SANITIZE_SPECIAL_CHARS);
    $anel_identificacao = filter_input(INPUT_POST, 'anel_identificacao', FILTER_SANITIZE_SPECIAL_CHARS);
    $pesagem_co2_semestral = filter_input(INPUT_POST, 'pesagem_co2_semestral', FILTER_SANITIZE_SPECIAL_CHARS);
    $comentarios = filter_input(INPUT_POST, 'comentarios', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $foto = $_FILES['foto'];

    // Lidar com o upload de fotos
    $foto_nome = null;
    $upload_warning = '';
    if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
        // Validar o tamanho do arquivo (limite de 5MB)
        $max_file_size = 5 * 1024 * 1024; // 5MB em bytes
        if ($foto['size'] > $max_file_size) {
            error_log("Tentativa de upload de arquivo muito grande em salvar_inspecao.php. Tamanho: " . $foto['size'] . " bytes.");
            header('Location: formulario_inspecao.php?codigo=' . urlencode($codigo) . '&message=' . urlencode('Erro: O arquivo excede o tamanho máximo permitido de 5MB.'));
            exit();
        }

        // Validar extensão da foto
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            error_log("Tentativa de upload de arquivo não permitido em salvar_inspecao.php. Extensão: $file_extension");
            header('Location: formulario_inspecao.php?codigo=' . urlencode($codigo) . '&message=' . urlencode('Erro: Tipo de arquivo não permitido.'));
            exit();
        }

        // Validar MIME type da foto
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $foto['tmp_name']);
        finfo_close($finfo);

        $allowed_mime_types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mime_type, $allowed_mime_types)) {
            error_log("Tentativa de upload com MIME type não permitido em salvar_inspecao.php. MIME: $mime_type");
            header('Location: formulario_inspecao.php?codigo=' . urlencode($codigo) . '&message=' . urlencode('Erro: Tipo MIME não permitido.'));
            exit();
        }

        // Gerar um nome de arquivo seguro e aleatório baseado no MIME type detectado
        $safe_extension = $allowed_mime_types[$mime_type];
        $foto_nome = uniqid('foto_', true) . '.' . $safe_extension;
        $foto_destino = "../uploads/" . $foto_nome;

        if (!move_uploaded_file($foto['tmp_name'], $foto_destino)) {
            error_log("Erro ao mover arquivo de upload da foto em salvar_inspecao.php para: $foto_destino");
            header('Location: formulario_inspecao.php?codigo=' . urlencode($codigo) . '&message=' . urlencode('Erro ao salvar a foto.'));
            exit();
        }
    }

    // Atualizar inspeção no banco de dados
    $sql = "UPDATE bd_extintores SET 
            Local_Exato = ?, 
            selo_do_Inmetro = ?, 
            sinalizacao_vertical = ?, 
            sinalizacao_piso = ?, 
            ficha_inspecao_trimestral = ?, 
            lacre = ?, 
            pressao_manometro = ?, 
            anel_identificacao = ?, 
            pesagem_co2_semestral = ?, 
            comentarios = ?, 
            foto = ?, 
            usuario = ?, 
            inspecao_trimestral_nivel1 = NOW()
			WHERE codigo = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param('sssssssssssss', $Local_Exato, $selo_do_Inmetro, $sinalizacao_vertical, $sinalizacao_piso, $ficha_inspecao_trimestral, $lacre, $pressao_manometro, $anel_identificacao, $pesagem_co2_semestral, $comentarios, $foto_nome, $_SESSION['username'], $codigo);

    if ($stmt->execute()) {
        auditoria('Inspeção de nível 1 realizada', $codigo, $_SESSION['user_id'], $_SESSION['user_level'], 'Inspeção realizada com sucesso.');
        $success_msg = urlencode('Inspeção salva com sucesso.' . $upload_warning);

        $stmt->close();
        $conn->close();

        header('Location: formulario_inspecao.php?codigo=' . $codigo . '&message=' . $success_msg);
        exit();
    } else {
        error_log("Erro no DB ao salvar inspeção: " . $stmt->error);

        $stmt->close();
        $conn->close();

        header('Location: formulario_inspecao.php?codigo=' . $codigo . '&message=' . urlencode('Erro interno ao salvar a inspeção.'));
        exit();
    }
}
?>

