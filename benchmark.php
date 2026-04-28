<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MockPHPMailer extends PHPMailer {
    public function isSMTP() {}
    public function send() {
        // Simulate network delay for SMTP sending
        usleep(100000); // 100ms
        return true;
    }
}

function benchmark_baseline($num_rows) {
    $mail = new MockPHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPKeepAlive = true;

    $start = microtime(true);

    for ($i = 0; $i < $num_rows; $i++) {
        try {
            $mail->addAddress('destinatario@example.com');
            $mail->Subject = 'Alerta de Manutenção Pendente';
            $mail->Body = 'O extintor com código ' . $i . ' está com manutenção pendente.';
            $mail->send();
            $mail->clearAddresses();
        } catch (Exception $e) {}
    }

    return microtime(true) - $start;
}

function benchmark_optimized($num_rows) {
    $mail = new MockPHPMailer(true);
    $mail->isSMTP();

    $start = microtime(true);

    $body = "";
    for ($i = 0; $i < $num_rows; $i++) {
        $body .= 'O extintor com código ' . $i . ' está com manutenção pendente.<br>';
    }

    try {
        $mail->addAddress('destinatario@example.com');
        $mail->Subject = 'Alerta de Manutenção Pendente';
        $mail->Body = $body;
        $mail->send();
        $mail->clearAddresses();
    } catch (Exception $e) {}

    return microtime(true) - $start;
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $rows = 50;
    echo "Running benchmark with $rows simulated rows...\n";

    $baseline = benchmark_baseline($rows);
    echo "Baseline (one email per row): " . number_format($baseline, 4) . " seconds\n";

    $optimized = benchmark_optimized($rows);
    echo "Optimized (single batched email): " . number_format($optimized, 4) . " seconds\n";

    $improvement = ($baseline - $optimized) / $baseline * 100;
    echo "Improvement: " . number_format($improvement, 2) . "%\n";
}
