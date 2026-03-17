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
        $this->assertTrue(strpos($this->mail_captured['headers'], 'From: no-reply@enilton.com.br') !== false);
    }

    public function testEnviarEmailReturnsFalseIfMailFails() {
        $para = 'test@example.com';
        $assunto = 'Test Subject';
        $mensagem = 'Test Message';

        $this->mail_return_value = false;
        $result = enviarEmail($para, $assunto, $mensagem, [$this, 'mockMail']);

        $this->assertTrue($result === false, "enviarEmail should return false if mail() fails");
    }
}
?>
