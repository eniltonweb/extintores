<?php
function enviarEmail($para, $assunto, $mensagem, $mail_func = 'mail') {
    $headers = 'From: no-reply@enilton.com.br' . "\r\n" .
               'Reply-To: no-reply@enilton.com.br' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    return $mail_func($para, $assunto, $mensagem, $headers);
}

// Exemplo de uso
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $para = 'usuario@example.com';
    $assunto = 'Notificação de Manutenção Pendente';
    $mensagem = 'Olá, você tem uma manutenção pendente para o extintor de código 100-02.';
    enviarEmail($para, $assunto, $mensagem);
}
?>
