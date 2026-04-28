<?php

class DeletarUsuarioTest extends MiniTestCase {
    private $wrapper_script = __DIR__ . '/wrapper_deletar_usuario.php';

    private function runWrapper($state) {
        $state_json = escapeshellarg(json_encode($state));
        $cmd = "php {$this->wrapper_script} {$state_json} 2>&1";
        exec($cmd, $output_lines, $return_var);
        return implode("\n", $output_lines);
    }

    public function testRedirectsWhenNoSession() {
        $state = [
            'session' => [],
            'post' => []
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Expected redirect when no session");
    }

    public function testRedirectsWhenNotAdmin() {
        $state = [
            'session' => ['user_id' => 1, 'user_level' => 'bombeiro'],
            'post' => []
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Expected redirect when not admin");
    }

    public function testRedirectsWhenNotPostMethod() {
        $state = [
            'session' => ['user_id' => 1, 'user_level' => 'admin'],
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Expected redirect when method is not POST");
    }

    public function testRedirectsWhenCsrfInvalid() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'invalid_token'
            ]
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Expected redirect when CSRF token is invalid");
    }

    public function testMissingId() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token'
            ]
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, 'ID do usuário não fornecido.') !== false, "Expected error when ID is missing");
    }

    public function testUserNotFound() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'id' => 999
            ],
            'user_exists' => false
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, 'Usuário não encontrado.') !== false, "Expected error when user is not found");
    }

    public function testSelectPrepareFails() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'id' => 1
            ],
            'db_prepare_error_queries' => ['SELECT username FROM usuarios']
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, 'Erro ao preparar a consulta') !== false, "Expected error when SELECT prepare fails");
    }

    public function testAuditoriaPrepareFails() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'id' => 1
            ],
            'user_exists' => true,
            'db_prepare_error_queries' => ['DELETE FROM auditoria_logs']
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, 'Erro ao preparar a consulta de deleção de auditoria') !== false, "Expected error when DELETE auditoria_logs prepare fails");
    }

    public function testAuditoriaExecuteFails() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'id' => 1
            ],
            'user_exists' => true,
            'db_stmt_error' => 'Mock DB Statement Error'
        ];
        $output = $this->runWrapper($state);
        // Execute falls through for the first stmt (SELECT), fails for DELETE auditoria_logs
        $this->assertTrue(strpos($output, 'Erro ao deletar registros de auditoria') !== false, "Expected error when DELETE auditoria_logs execute fails");
    }

    public function testDeletarUsuarioPrepareFails() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'id' => 1
            ],
            'user_exists' => true,
            'db_prepare_error_queries' => ['DELETE FROM usuarios']
        ];
        $output = $this->runWrapper($state);
        $this->assertTrue(strpos($output, 'Erro ao preparar a consulta de deleção') !== false, "Expected error when DELETE usuarios prepare fails");
    }

    public function testSuccessScenario() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'id' => 1
            ],
            'user_exists' => true
        ];
        $output = $this->runWrapper($state);

        $this->assertTrue(strpos($output, '[MOCK_AUDITORIA]') !== false, "Expected auditoria to be called on success");
        $this->assertTrue(strpos($output, 'Deletar um usuário') !== false, "Expected auditoria to log correct action");
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Expected redirect on success");
    }
}
?>
