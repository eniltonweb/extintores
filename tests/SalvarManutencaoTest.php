<?php

require_once __DIR__ . '/MockDatabase.php';

// Force the inclusion without executing the script
$_SERVER['SCRIPT_FILENAME'] = 'phpunit.php';
require_once __DIR__ . '/../salvar_manutencao.php';

class SalvarManutencaoTest extends MiniTestCase {

    public function testRedirectsWhenNoSession() {
        $conn = new MockConnection();
        $session = [];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: index.php', $result);
    }

    public function testRedirectsWhenNotFornecedor() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'bombeiro'
        ];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: index.php', $result);
    }

    public function testRedirectsWhenCsrfTokenMissing() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'codigo' => 'EXT001'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: formulario_manutencao.php?message=Erro%3A+Falha+na+valida%C3%A7%C3%A3o+de+seguran%C3%A7a.', $result);
    }

    public function testRedirectsWhenBothTokensEmpty() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor',
            'csrf_token' => ''
        ];
        $post = [
            'csrf_token' => '',
            'codigo' => 'EXT001'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: formulario_manutencao.php?message=Erro%3A+Falha+na+valida%C3%A7%C3%A3o+de+seguran%C3%A7a.', $result);
    }

    public function testRedirectsWhenCsrfTokenInvalid() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'invalid_token',
            'codigo' => 'EXT001'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: formulario_manutencao.php?message=Erro%3A+Falha+na+valida%C3%A7%C3%A3o+de+seguran%C3%A7a.', $result);
    }

    public function testHandlesPrepareFailure() {
        $conn = new class extends MockConnection {
            public $error = "Mock prepare error";
            public function prepare($query) {
                return false;
            }
        };
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor',
            'user_name' => 'testuser',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token',
            'codigo' => 'EXT001',
            'manutencao_n2' => '1',
            'cobertura' => '0'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        // Suppress error log output during testing
        ob_start();
        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        ob_end_clean();

        $this->assertEquals('Location: formulario_manutencao.php?message=Erro+interno+ao+atualizar+a+manuten%C3%A7%C3%A3o.+Erro+interno+ao+atualizar+os+dias+para+expirar.', $result);
    }

    public function testHandlesExecuteFailure() {
        $conn = new class extends MockConnection {
            public function prepare($query) {
                $stmt = parent::prepare($query);
                $stmt->executeResult = false;
                $stmt->error = "Mock execute error";
                return $stmt;
            }
        };
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor',
            'user_name' => 'testuser',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token',
            'codigo' => 'EXT001',
            'manutencao_n2' => '1',
            'cobertura' => '0'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        // Suppress error log output during testing
        ob_start();
        $result = salvar_manutencao_logic($conn, $session, $post, $server);
        ob_end_clean();

        $this->assertEquals('Location: formulario_manutencao.php?message=Erro+interno+ao+atualizar+a+manuten%C3%A7%C3%A3o.+Erro+interno+ao+atualizar+os+dias+para+expirar.', $result);
    }

    public function testProcessesWithValidCsrfToken() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor',
            'user_name' => 'testuser',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token',
            'codigo' => 'EXT001',
            'manutencao_n2' => '1',
            'cobertura' => '0'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = salvar_manutencao_logic($conn, $session, $post, $server);

        $this->assertEquals('Location: formulario_manutencao.php?message=Manuten%C3%A7%C3%A3o+e+pr%C3%B3xima+manuten%C3%A7%C3%A3o+registradas+com+sucesso%21+Dias+para+expirar+atualizados+com+sucesso%21', $result);
    }
}
