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
        ];
    }

    public function testRedirectsWhenNoSession() {
        $result = $this->runWrapper([]);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: index.php') !== false,
            "Expected a redirect to index.php when there is no session."
        );
    }

    public function testRedirectsWhenNotAdmin() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'bombeiro'
            ]
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: index.php') !== false,
            "Expected a redirect to index.php when user is not admin."
        );
    }

    public function testRedirectsWhenGetAndNoId() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'GET'
            ],
            // get is implicitly empty
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: registrar_usuario.php') !== false,
            "Expected a redirect to registrar_usuario.php when GET lacks id."
        );
    }

    public function testRenderPageWithId() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'GET'
            ],
            'get' => [
                'id' => '123'
            ]
        ];
        $result = $this->runWrapper($state);

        // Ensure no redirect headers occurred
        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER]') === false,
            "Expected no redirect headers."
        );

        // Ensure we rendered the form with the right value
        $this->assertTrue(
            strpos($result['output'], '<title>Resetar Senha</title>') !== false,
            "Expected page to render with 'Resetar Senha' title."
        );
        $this->assertTrue(
            strpos($result['output'], '<input type="hidden" name="id" value="123">') !== false,
            "Expected form to render with id=123."
        );
    }

    public function testSuccessScenario() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'id' => '123',
                'nova_senha' => 'new_password_123'
            ],
            'get' => [
                'id' => '123' // needed to avoid redirecting in the bottom half of the script
            ]
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], 'Senha resetada com sucesso.') !== false,
            "Expected success message in the output."
        );
    }

    public function testDbExecuteFails() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'id' => '123',
                'nova_senha' => 'new_password_123'
            ],
            'get' => [
                'id' => '123'
            ],
            'db_stmt_error' => true
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], 'Erro ao resetar senha:') !== false,
            "Expected database execution error message."
        );
    }
}
?>