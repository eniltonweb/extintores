<?php
require_once __DIR__ . '/runner.php';

class SairTest extends MiniTestCase {

    public function testSairSuccess() {
        $wrapper_script = __DIR__ . '/wrapper_sair.php';

        $cmd = "php {$wrapper_script} 2>&1";
        exec($cmd, $output, $return_var);

        $output_str = implode("\n", $output);

        $this->assertTrue(
            strpos($output_str, '[MOCK_SESSION_START]') !== false,
            "Deve iniciar a sessão."
        );

        $this->assertTrue(
            strpos($output_str, '[SESSION_BEFORE_CLEAR] 2') !== false,
            "Deve ter variáveis na sessão antes de limpar."
        );

        $this->assertTrue(
            strpos($output_str, '[SESSION_AFTER_CLEAR] 0') !== false,
            "A sessão deve estar vazia após a limpeza."
        );

        $this->assertTrue(
            strpos($output_str, '[MOCK_SETCOOKIE]') !== false,
            "Deve tentar limpar o cookie da sessão."
        );

        $this->assertTrue(
            strpos($output_str, '[MOCK_SESSION_DESTROY]') !== false,
            "Deve destruir a sessão."
        );

        $this->assertTrue(
            strpos($output_str, '[MOCK_HEADER] Location: login.php') !== false,
            "Deve redirecionar para login.php."
        );
    }
}
