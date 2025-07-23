<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
include '../config/db_conexao.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

$sql = "SELECT * FROM bd_extintores WHERE dias_para_expirar_n2 <= 30";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'seu_email@example.com';
            $mail->Password = 'sua_senha';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('seu_email@example.com', 'Sistema de Manutenção');
            $mail->addAddress('destinatario@example.com');

            $mail->isHTML(true);
            $mail->Subject = 'Alerta de Manutenção Pendente';
            $mail->Body = 'O extintor com código ' . $row['codigo'] . ' está com manutenção pendente. Próxima manutenção: ' . $row['proxima_manutencao_n2'];

            $mail->send();
            echo 'Mensagem enviada para ' . $row['codigo'] . '<br>';
        } catch (Exception $e) {
            echo "A mensagem não pôde ser enviada. Erro: {$mail->ErrorInfo}";
        }
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