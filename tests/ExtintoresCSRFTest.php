<?php

class ExtintoresCSRFTest extends MiniTestCase {

    private function runWrapper($sessionData, $postData = null) {
        $wrapper_script = __DIR__ . '/wrapper_extintores.php';
        $json_data = escapeshellarg(json_encode([
            'session' => $sessionData,
            'post' => $postData
        ]));

        $cmd = "php {$wrapper_script} {$json_data} 2>&1";
        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    public function testPostFailsWithoutCSRFToken() {
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];

        $post = [
            'inserir' => '1',
            'codigo' => 'TEST001',
            'predio' => 'Predio 1',
            'atividade' => 'Atividade 1',
            'local_exato' => 'Local 1',
            'tipo_extintor' => 'AP',
            'carga' => '10L'
        ];

        $result = $this->runWrapper($session, $post);

        $this->assertTrue(
            strpos($result['output'], 'Erro de validação de segurança') !== false,
            "Expected CSRF error message when token is missing. Output was: " . $result['output']
        );
        $this->assertTrue(
            strpos($result['output'], 'Extintor inserido com sucesso!') === false,
            "Extintor should NOT be inserted when CSRF token is missing."
        );
    }

    public function testPostFailsWithInvalidCSRFToken() {
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];

        $post = [
            'inserir' => '1',
            'codigo' => 'TEST001',
            'csrf_token' => 'invalid_token'
        ];

        $result = $this->runWrapper($session, $post);

        $this->assertTrue(
            strpos($result['output'], 'Erro de validação de segurança') !== false,
            "Expected CSRF error message when token is invalid. Output was: " . $result['output']
        );
        $this->assertTrue(
            strpos($result['output'], 'Extintor inserido com sucesso!') === false,
            "Extintor should NOT be inserted when CSRF token is invalid."
        );
    }

    public function testPostSucceedsWithValidCSRFToken() {
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];

        $post = [
            'inserir' => '1',
            'codigo' => 'TEST001',
            'predio' => 'Predio 1',
            'atividade' => 'Atividade 1',
            'local_exato' => 'Local 1',
            'tipo_extintor' => 'AP',
            'carga' => '10L',
            'csrf_token' => 'valid_token'
        ];

        $result = $this->runWrapper($session, $post);

        $this->assertTrue(
            strpos($result['output'], 'Extintor inserido com sucesso!') !== false,
            "Expected 'Extintor inserido com sucesso!' when valid CSRF token is provided. Output was: " . $result['output']
        );
    }
}
