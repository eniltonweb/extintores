<?php
/**
 * salvar_inspecao.php — VERSÃO ATUALIZADA
 * ============================================================
 * Suporte a múltiplas fotos por inspeção.
 * Salva cada foto na tabela inspecao_fotos (nova).
 * Mantém retrocompatibilidade: salva a 1ª foto no campo
 * foto de bd_extintores para código legado que ainda lê dali.
 * ============================================================
 */

session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'bombeiro') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Buscar username
$sql = "SELECT username FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);
if ($stmt->fetch()) {
    $_SESSION['username'] = $username;
    $stmt->close();
} else {
    echo "Usuário não encontrado.";
    $stmt->close();
    exit();
}

// -------------------------------------------------------
// Funções auxiliares
// -------------------------------------------------------

/**
 * Processa e salva um único arquivo de imagem.
 * Retorna o nome do arquivo salvo ou null em caso de erro.
 */
function salvar_foto_upload(array $file, string $uploads_dir): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $max_size = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $max_size) {
        error_log("Upload recusado: arquivo muito grande ({$file['size']} bytes)");
        return null;
    }

    $allowed_mime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime_type, $allowed_mime)) {
        error_log("Upload recusado: MIME não permitido ($mime_type)");
        return null;
    }

    $ext       = $allowed_mime[$mime_type];
    $foto_nome = uniqid('foto_', true) . '.' . $ext;
    $destino   = $uploads_dir . $foto_nome;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        error_log("Erro ao mover upload para: $destino");
        return null;
    }

    return $foto_nome;
}

/**
 * Reconstrói o array $_FILES para múltiplos arquivos no formato
 * name="fotos[]" em um array mais fácil de iterar.
 */
function reorganizar_files_array(array $files_field): array
{
    $result = [];
    if (!isset($files_field['name']) || !is_array($files_field['name'])) {
        return $result;
    }
    foreach ($files_field['name'] as $i => $name) {
        $result[] = [
            'name'     => $name,
            'type'     => $files_field['type'][$i],
            'tmp_name' => $files_field['tmp_name'][$i],
            'error'    => $files_field['error'][$i],
            'size'     => $files_field['size'][$i],
        ];
    }
    return $result;
}

// -------------------------------------------------------
// Processamento do POST
// -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: formulario_inspecao.php');
    exit();
}

$codigo = filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);

// Validação CSRF
if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $msg = urlencode("Erro de validação: Token CSRF inválido.");
    header("Location: formulario_inspecao.php?codigo=" . urlencode($codigo) . "&message=$msg");
    exit();
}

// Campos do checklist
$Local_Exato              = filter_input(INPUT_POST, 'Local_Exato',              FILTER_SANITIZE_SPECIAL_CHARS);
$status_selo_inmetro      = filter_input(INPUT_POST, 'status_selo_inmetro',      FILTER_SANITIZE_SPECIAL_CHARS);
$sinalizacao_vertical     = filter_input(INPUT_POST, 'sinalizacao_vertical',     FILTER_SANITIZE_SPECIAL_CHARS);
$sinalizacao_piso         = filter_input(INPUT_POST, 'sinalizacao_piso',         FILTER_SANITIZE_SPECIAL_CHARS);
$ficha_inspecao_trimestral= filter_input(INPUT_POST, 'ficha_inspecao_trimestral',FILTER_SANITIZE_SPECIAL_CHARS);
$lacre                    = filter_input(INPUT_POST, 'lacre',                    FILTER_SANITIZE_SPECIAL_CHARS);
$pressao_manometro        = filter_input(INPUT_POST, 'pressao_manometro',        FILTER_SANITIZE_SPECIAL_CHARS);
$anel_identificacao       = filter_input(INPUT_POST, 'anel_identificacao',       FILTER_SANITIZE_SPECIAL_CHARS);
$pesagem_co2_semestral    = filter_input(INPUT_POST, 'pesagem_co2_semestral',    FILTER_SANITIZE_SPECIAL_CHARS);
$comentarios              = filter_input(INPUT_POST, 'comentarios',              FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Alerta automático se selo divergente
if ($status_selo_inmetro === 'NÃO OK') {
    $comentarios = "[ALERTA GRAVE: Selo INMETRO Divergente/Ausente!] " . $comentarios;
}

// Legendas das fotos (opcional)
$legendas_raw = isset($_POST['legendas']) && is_array($_POST['legendas'])
    ? $_POST['legendas']
    : [];

// -------------------------------------------------------
// Upload das fotos (múltiplas)
// -------------------------------------------------------
$uploads_dir    = __DIR__ . '/uploads/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

$fotos_salvas   = []; // array de nomes de arquivo salvos
$primeira_foto  = null;

if (isset($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
    $arquivos = reorganizar_files_array($_FILES['fotos']);
    $limite   = 5;
    $count    = 0;

    foreach ($arquivos as $arquivo) {
        if ($count >= $limite) break;
        if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) continue;

        $nome = salvar_foto_upload($arquivo, $uploads_dir);
        if ($nome !== null) {
            $fotos_salvas[] = $nome;
            if ($primeira_foto === null) {
                $primeira_foto = $nome;
            }
            $count++;
        }
    }
}

// -------------------------------------------------------
// Atualizar bd_extintores (checklist + 1ª foto retrocompat)
// -------------------------------------------------------
$sql_update = "
    UPDATE bd_extintores SET
        Local_Exato               = ?,
        sinalizacao_vertical      = ?,
        sinalizacao_piso          = ?,
        ficha_inspecao_trimestral = ?,
        lacre                     = ?,
        pressao_manometro         = ?,
        anel_identificacao        = ?,
        pesagem_co2_semestral     = ?,
        comentarios               = ?,
        foto                      = ?,
        usuario                   = ?,
        inspecao_trimestral_nivel1 = NOW()
    WHERE codigo = ?
";

$stmt = $conn->prepare($sql_update);
$stmt->bind_param(
    'ssssssssssss',
    $Local_Exato,
    $sinalizacao_vertical,
    $sinalizacao_piso,
    $ficha_inspecao_trimestral,
    $lacre,
    $pressao_manometro,
    $anel_identificacao,
    $pesagem_co2_semestral,
    $comentarios,
    $primeira_foto,
    $_SESSION['username'],
    $codigo
);

if (!$stmt->execute()) {
    error_log("Erro no DB ao salvar inspeção: " . $stmt->error);
    $stmt->close();
    $conn->close();
    header("Location: formulario_inspecao.php?codigo=" . urlencode($codigo) .
           "&message=" . urlencode('Erro interno ao salvar a inspeção.'));
    exit();
}
$stmt->close();

// -------------------------------------------------------
// Salvar cada foto em inspecao_fotos
// -------------------------------------------------------
if (!empty($fotos_salvas)) {
    $sql_foto = "
        INSERT INTO inspecao_fotos
            (extintor_codigo, data_inspecao, foto_nome, legenda, usuario)
        VALUES (?, CURDATE(), ?, ?, ?)
    ";
    $stmt_foto = $conn->prepare($sql_foto);

    foreach ($fotos_salvas as $i => $nome_foto) {
        $legenda = isset($legendas_raw[$i])
            ? substr(strip_tags($legendas_raw[$i]), 0, 255)
            : null;

        $stmt_foto->bind_param(
            'ssss',
            $codigo,
            $nome_foto,
            $legenda,
            $_SESSION['username']
        );
        $stmt_foto->execute();
    }
    $stmt_foto->close();
}

// -------------------------------------------------------
// Auditoria
// -------------------------------------------------------
$total_fotos    = count($fotos_salvas);
$detalhe_audit  = "Inspeção Nível 1 concluída. Selo INMETRO: $status_selo_inmetro. " .
                  "Fotos enviadas: $total_fotos.";
auditoria(
    'Inspeção de nível 1 realizada',
    $codigo,
    $_SESSION['user_id'],
    $_SESSION['user_level'],
    $detalhe_audit
);

$conn->close();

$msg_sucesso = "Inspeção salva com sucesso. $total_fotos foto(s) registrada(s).";
header("Location: formulario_inspecao.php?codigo=" . urlencode($codigo) .
       "&message=" . urlencode($msg_sucesso));
exit();
