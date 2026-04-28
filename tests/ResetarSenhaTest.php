<?php

class ResetarSenhaTest extends MiniTestCase {

    private function runWrapper($stateData) {
        $wrapper_script = __DIR__ . '/wrapper_resetar_senha.php';
        $json_data = escapeshellarg(json_encode($stateData));

        $cmd = "php {$wrapper_script} {$json_data} 2>&1";
        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    private function getValidSession() {
        return [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token_123'
        ];
    }

    public function testRedirectsWhenNoSession() {
        $result = $this->runWrapper([]);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: index.php') !== false,
            "Should redirect to index.php when no session is present."
        );
    }

    public function testRedirectsWhenNotAdmin() {
        $result = $this->runWrapper([
            'session' => [
                'user_id' => 1,
                'user_level' => 'operador'
            ]
        ]);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: index.php') !== false,
            "Should redirect to index.php when user is not admin."
        );
    }

    public function testGetRedirectsWhenIdMissing() {
        $result = $this->runWrapper([
            'session' => $this->getValidSession(),
            'method' => 'GET'
        ]);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: registrar_usuario.php') !== false,
            "Should redirect to registrar_usuario.php when GET ID is missing."
        );
    }

    public function testPostFailsWithMissingCsrfToken() {
        $result = $this->runWrapper([
            'session' => $this->getValidSession(),
            'method' => 'POST',
            'post' => [
                'id' => 2,
                'nova_senha' => '123'
            ],
            'get' => ['id' => 2]
        ]);

        $this->assertTrue(
            strpos($result['output'], 'Erro de validação CSRF.') !== false,
            "Should show CSRF error message when token is missing."
        );
    }

    public function testPostFailsWithInvalidCsrfToken() {
        $result = $this->runWrapper([
            'session' => $this->getValidSession(),
            'method' => 'POST',
            'post' => [
                'id' => 2,
                'nova_senha' => '123',
                'csrf_token' => 'invalid_token'
            ],
            'get' => ['id' => 2]
        ]);

        $this->assertTrue(
            strpos($result['output'], 'Erro de validação CSRF.') !== false,
            "Should show CSRF error message when token is invalid."
        );
    }

    public function testPostSucceedsWithValidCsrfToken() {
        $result = $this->runWrapper([
            'session' => $this->getValidSession(),
            'method' => 'POST',
            'post' => [
                'id' => 2,
                'nova_senha' => '123',
                'csrf_token' => 'valid_token_123'
            ],
            'get' => ['id' => 2]
        ]);

        $this->assertTrue(
            strpos($result['output'], 'Senha resetada com sucesso.') !== false,
            "Should reset password successfully when valid CSRF token is provided."
        );
    }

    public function testPostFailsWhenDbError() {
        $result = $this->runWrapper([
            'session' => $this->getValidSession(),
            'method' => 'POST',
            'post' => [
                'id' => 2,
                'nova_senha' => '123',
                'csrf_token' => 'valid_token_123'
            ],
            'get' => ['id' => 2],
            'db_stmt_error' => true
        ]);

        $this->assertTrue(
            strpos($result['output'], 'Erro ao resetar senha:') !== false,
            "Should show DB error message when database operation fails."
        );
    }
}
