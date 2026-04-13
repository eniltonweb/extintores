<?php
function auditoria($acao, $codigo_extintor, $user_id, $user_level, $detalhes = '') {
    global $conn;
    static $extintor_cache = [];
    $extintor_id = null;

    if (!is_null($codigo_extintor)) {
        if (array_key_exists($codigo_extintor, $extintor_cache)) {
            $extintor_id = $extintor_cache[$codigo_extintor];
        } else {
            $stmt_extintor = $conn->prepare("SELECT id FROM bd_extintores WHERE codigo = ?");
            $stmt_extintor->bind_param('s', $codigo_extintor);
            $stmt_extintor->execute();
            $stmt_extintor->bind_result($extintor_id);
            $stmt_extintor->fetch();
            $stmt_extintor->close();
            $extintor_cache[$codigo_extintor] = $extintor_id;
        }
    }

    $sql = "INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, data_hora, detalhes) 
            VALUES (?, ?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issss', $user_id, $user_level, $acao, $extintor_id, $detalhes);
    $stmt->execute();
    $stmt->close();
}

if (!function_exists('registrar_auditoria')) {
    function registrar_auditoria($conn, $user_id, $action, $details) {
        $sql = "INSERT INTO auditoria_logs (user_id, action, detalhes) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}
?>
