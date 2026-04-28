<?php

class RejeitarExtintorTest extends MiniTestCase {

    private function runWrapper($stateData) {
        $wrapper_script = __DIR__ . '/wrapper_rejeitar_extintor.php';
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

    public function testRedirectsWhenNotPostMethod() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'GET'
            ]
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Método+inválido.') !== false,
            "Expected a redirect with invalid method message."
        );
    }

    public function testRedirectsWhenCsrfInvalid() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'csrf_token' => 'invalid_token'
            ]
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Falha+na+validação+de+segurança.') !== false,
            "Expected a redirect when CSRF token is invalid."
        );
    }

    public function testRedirectsWhenCodigoMissing() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'csrf_token' => 'valid_token_123'
            ]
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Código+do+extintor+não+fornecido.') !== false,
            "Expected a redirect when codigo is not sent in POST."
        );
    }

    public function testRedirectsWhenExtintorNotFound() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'csrf_token' => 'valid_token_123',
                'codigo' => 'EXT-002'
            ],
            'extintor_not_found' => true
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Extintor+não+encontrado.') !== false,
            "Expected a redirect when extintor is not found in database."
        );
    }

    public function testDbExecuteFails() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'csrf_token' => 'valid_token_123',
                'codigo' => 'EXT-001'
            ],
            'db_delete_extintor_error' => true
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Não+foi+possível+remover+o+extintor.') !== false,
            "Expected a redirect when statement execution fails for deleting the extintor."
        );
    }

    public function testSuccessScenario() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'csrf_token' => 'valid_token_123',
                'codigo' => 'EXT-001'
            ]
        ];
        $result = $this->runWrapper($state);

        // Assert Success Redirect
        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Extintor+rejeitado+e+removido+com+sucesso.') !== false,
            "Expected success redirect message."
        );
    }
}
?>