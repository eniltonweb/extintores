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

        $alertas = [];
        while ($row = $result->fetch_assoc()) {
            $alertas[] = '<li>O extintor com código <strong>' . htmlspecialchars($row['codigo']) . '</strong> está com manutenção pendente. Próxima manutenção: ' . htmlspecialchars($row['proxima_manutencao_n2']) . '</li>';
        }

        if (!empty($alertas)) {
            try {
                $mail->addAddress($mailRecipient);
                $mail->Subject = 'Resumo de Alertas de Manutenção Pendente';
                $mail->Body = '<h3>Alertas de Manutenção Pendente</h3><ul>' . implode('', $alertas) . '</ul>';

                $mail->send();
                echo 'Resumo de alertas enviado com sucesso.<br>';
            } catch (Exception $e) {
                echo "O resumo de alertas não pôde ser enviado. Erro: {$mail->ErrorInfo}";
            } finally {
                $mail->clearAddresses();
            }
        }
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