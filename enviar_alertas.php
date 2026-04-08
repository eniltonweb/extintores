<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carrega o autoloader do Composer.
// O diretório `vendor` é criado após a instalação do PHPMailer.
require 'vendor/autoload.php';
require_once __DIR__ . '/config/db_conexao.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

$sql = "SELECT * FROM bd_extintores WHERE dias_para_expirar_n2 <= 30";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpSecure = getenv('SMTP_SECURE') ?: '';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
    $mailFrom = getenv('MAIL_FROM') ?: '';
    $mailRecipient = getenv('MAIL_RECIPIENT') ?: '';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;

        $mail->setFrom($mailFrom, 'Sistema de Manutenção');
        $mail->isHTML(true);
        $mail->SMTPKeepAlive = true; // Mantém a conexão SMTP aberta para múltiplos envios

        while ($row = $result->fetch_assoc()) {
            try {
                $mail->addAddress($mailRecipient);

                $mail->Subject = 'Alerta de Manutenção Pendente';
                $mail->Body = 'O extintor com código ' . $row['codigo'] . ' está com manutenção pendente. Próxima manutenção: ' . $row['proxima_manutencao_n2'];

                $mail->send();
                echo 'Mensagem enviada para ' . $row['codigo'] . '<br>';

                // Limpa todos os destinatários após o envio para o próximo loop
                $mail->clearAddresses();
            } catch (Exception $e) {
                echo "A mensagem não pôde ser enviada. Erro: {$mail->ErrorInfo}";
                // Limpa destinatários em caso de erro para evitar acumular
                $mail->clearAddresses();
            }
        }
        $mail->smtpClose(); // Fecha a conexão SMTP após o término do loop
    } catch (Exception $e) {
        echo "Erro ao configurar o envio de emails: {$mail->ErrorInfo}";
    }
} else {
    echo "Nenhum extintor com manutenção pendente.";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Envio de Alertas</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Envio de Alertas por Email</h2>
</body>
</html>