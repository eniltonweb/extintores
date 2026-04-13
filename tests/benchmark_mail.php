<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Mock PHPMailer to avoid real network calls but simulate overhead
class MockPHPMailer extends PHPMailer {
    public function isSMTP() {}
    public function send() {
        // Simulate some processing time for connection/handshake if we wanted,
        // but even instantiation has overhead.
        usleep(1000); // 1ms simulated delay
        return true;
    }
}

function benchmark($iterations, $optimize = false) {
    $start = microtime(true);

    // Environment variables configured without fallback string values for sensitive secrets
    // to prevent automated security scans from flagging them as hardcoded vulnerabilities.
    // Ensure these variables are provided by the environment during execution.
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.example.com';
    $smtpUser = getenv('SMTP_USER') ?: 'seu_email@example.com';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpSecure = getenv('SMTP_SECURE') ?: 'tls';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
    $mailFrom = getenv('MAIL_FROM') ?: 'seu_email@example.com';
    $mailRecipient = getenv('MAIL_RECIPIENT') ?: 'destinatario@example.com';

    if ($optimize) {
        $mail = new MockPHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;
        $mail->setFrom($mailFrom, 'Sistema de Manutenção');
        $mail->isHTML(true);
        $mail->SMTPKeepAlive = true;

        for ($i = 0; $i < $iterations; $i++) {
            try {
                $mail->addAddress($mailRecipient);
                $mail->Subject = 'Alerta de Manutenção Pendente';
                $mail->Body = 'O extintor com código 100-' . $i . ' está com manutenção pendente.';
                $mail->send();
                $mail->clearAddresses();
            } catch (Exception $e) {}
        }
        $mail->smtpClose();
    } else {
        for ($i = 0; $i < $iterations; $i++) {
            $mail = new MockPHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPass;
                $mail->SMTPSecure = $smtpSecure;
                $mail->Port = $smtpPort;
                $mail->setFrom($mailFrom, 'Sistema de Manutenção');
                $mail->addAddress($mailRecipient);
                $mail->isHTML(true);
                $mail->Subject = 'Alerta de Manutenção Pendente';
                $mail->Body = 'O extintor com código 100-' . $i . ' está com manutenção pendente.';
                $mail->send();
            } catch (Exception $e) {}
        }
    }

    return microtime(true) - $start;
}

$iterations = 100;
echo "Running benchmark with $iterations iterations...\n";

$baseline = benchmark($iterations, false);
echo "Baseline (instantiate in loop): " . number_format($baseline, 4) . " seconds\n";

$optimized = benchmark($iterations, true);
echo "Optimized (reuse instance): " . number_format($optimized, 4) . " seconds\n";

$improvement = ($baseline - $optimized) / $baseline * 100;
echo "Improvement: " . number_format($improvement, 2) . "%\n";
