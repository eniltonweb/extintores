<?php

class AprovarExtintorTest extends MiniTestCase {

    private function runWrapper($stateData) {
        $wrapper_script = __DIR__ . '/wrapper_aprovar_extintor.php';
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
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Código+do+extintor+não+encontrado.') !== false,
            "Expected a redirect when codigo is not sent in POST."
        );
    }

    public function testDbPrepareFails() {
        $state = [
            'session' => $this->getValidSession(),
            'server' => [
                'REQUEST_METHOD' => 'POST'
            ],
            'post' => [
                'csrf_token' => 'valid_token_123',
                'codigo' => 'EXT-001'
            ],
            'db_prepare_error' => true
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Não+foi+possível+preparar+o+statement+para+aprovação+do+extintor.') !== false,
            "Expected a redirect when statement preparation fails."
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
            'db_stmt_error' => true
        ];
        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Erro:+Não+foi+possível+aprovar+o+extintor.') !== false,
            "Expected a redirect when statement execution fails."
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

        // Assert Audit Logs were triggered
        $this->assertTrue(
            strpos($result['output'], '[MOCK_AUDITORIA] Aprovação de Extintor | EXT-001 | 1 | admin | Extintor aprovado com sucesso') !== false,
            "Expected auditoria to be logged."
        );

        // Assert Success Redirect
        $this->assertTrue(
            strpos($result['output'], '[MOCK_HEADER] Location: aprovar_extintores.php?message=Extintor+aprovado+com+sucesso.') !== false,
            "Expected success redirect message."
        );
    }
}
?>