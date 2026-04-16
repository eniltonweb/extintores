<?php
require_once __DIR__ . '/../enviar_emails.php';

class EnviarEmailTest extends MiniTestCase {
    private $mail_captured = [];
    private $mail_return_value = true;

    public function mockMail($para, $assunto, $mensagem, $headers) {
        $this->mail_captured = [
            'para' => $para,
            'assunto' => $assunto,
            'mensagem' => $mensagem,
            'headers' => $headers
        ];
        return $this->mail_return_value;
    }

    public function testEnviarEmailCallsMailWithCorrectParameters() {
        $para = 'test@example.com';
        $assunto = 'Test Subject';
        $mensagem = 'Test Message';

        $this->mail_return_value = true;
        $result = enviarEmail($para, $assunto, $mensagem, [$this, 'mockMail']);

        $this->assertTrue($result, "enviarEmail should return true on success");
        $this->assertEquals($para, $this->mail_captured['para']);
        $this->assertEquals($assunto, $this->mail_captured['assunto']);
        $this->assertEquals($mensagem, $this->mail_captured['mensagem']);

        // Check all headers
        $this->assertTrue(strpos($this->mail_captured['headers'], 'From: no-reply@enilton.com.br') !== false, "From header should be correct");
        $this->assertTrue(strpos($this->mail_captured['headers'], 'Reply-To: no-reply@enilton.com.br') !== false, "Reply-To header should be correct");
        $this->assertTrue(strpos($this->mail_captured['headers'], 'X-Mailer: PHP/' . phpversion()) !== false, "X-Mailer header should include correct PHP version");
    }

    public function testEnviarEmailReturnsFalseIfMailFails() {
        $para = 'test@example.com';
        $assunto = 'Test Subject';
        $mensagem = 'Test Message';

        $this->mail_return_value = false;
        $result = enviarEmail($para, $assunto, $mensagem, [$this, 'mockMail']);

        $this->assertTrue($result === false, "enviarEmail should return false if mail() fails");
    }

    public function testEnviarEmailWithEmptyInputs() {
        $para = 'test@example.com';
        $assunto = '';
        $mensagem = '';

        $this->mail_return_value = true;
        $result = enviarEmail($para, $assunto, $mensagem, [$this, 'mockMail']);

        $this->assertTrue($result, "enviarEmail should return true even with empty subject/message");
        $this->assertEquals('', $this->mail_captured['assunto']);
        $this->assertEquals('', $this->mail_captured['mensagem']);
    }

    public function testEnviarEmailWithSpecialCharacters() {
        $para = 'test+suffix@example.com';
        $assunto = 'Assunto com acentuação: Áéíóú';
        $mensagem = "Mensagem com\nquebras de linha\ne símbolos: !@#$%^&*()";

        $this->mail_return_value = true;
        $result = enviarEmail($para, $assunto, $mensagem, [$this, 'mockMail']);

        $this->assertTrue($result, "enviarEmail should handle special characters");
        $this->assertEquals($para, $this->mail_captured['para']);
        $this->assertEquals($assunto, $this->mail_captured['assunto']);
        $this->assertEquals($mensagem, $this->mail_captured['mensagem']);
    }
}
?>
