<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once __DIR__ . '/config/db_conexao.php';
session_start();

// Define uma senha secreta para o Cron Job
$cron_token_secreto = 'michelin_extintores_2026_secreto';

// Verifica se é um admin logado OU se a URL tem o token secreto correto
$is_admin = (isset($_SESSION['user_id']) && $_SESSION['user_level'] == 'admin');
$is_cron  = (isset($_GET['token']) && $_GET['token'] === $cron_token_secreto);

if (!$is_admin && !$is_cron) {
    // Se não for nenhum dos dois, bloqueia o acesso
    header('Location: index.php');
    exit();
}

$sql = "SELECT * FROM bd_extintores WHERE dias_para_expirar_n2 <= 30";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.example.com';
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpSecure = getenv('SMTP_SECURE') ?: 'tls';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
    $mailFrom = getenv('MAIL_FROM') ?: 'sistema@example.com';
    $mailRecipient = getenv('MAIL_RECIPIENT') ?: 'destinatario@example.com';

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

        $mensagens = [];
        $codigos = [];

        while ($row = $result->fetch_assoc()) {
            $mensagens[] = "O extintor com código {$row['codigo']} está com manutenção pendente. Próxima manutenção: {$row['proxima_manutencao_n2']}";
            $codigos[] = $row['codigo'];
        }

        if (!empty($mensagens)) {
            try {
                $mail->addAddress($mailRecipient);
                $mail->Subject = 'Alerta de Manutenção Pendente - Extintores';
                $mail->Body = implode('<br><br>', $mensagens);

                $mail->send();

                foreach ($codigos as $codigo) {
                    echo 'Alerta enviado sobre o extintor ' . $codigo . '<br>';
                }
            } catch (Exception $e) {
                echo "A mensagem não pôde ser enviada. Erro: {$mail->ErrorInfo}";
            }
        }
    } catch (Exception $e) {
        echo "Erro ao configurar o envio de emails: {$mail->ErrorInfo}";
    }
} else {
    echo "Nenhum extintor com manutenção pendente. Tudo certo!";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Envio de Alertas</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Processamento de Alertas por E-mail Concluído</h2>
    <p>Verifique as mensagens acima para confirmar o envio.</p>
    <footer class="footer mt-4">
        <div class="container text-center">
            <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
        </div>
    </footer>
</body>
</html>