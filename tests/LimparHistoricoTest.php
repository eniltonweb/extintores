<?php

require_once __DIR__ . '/MockDatabase.php';

// Force the inclusion without executing the script
$_SERVER['SCRIPT_FILENAME'] = 'phpunit.php';
require_once __DIR__ . '/../limpar_historico.php';

class LimparHistoricoTest extends MiniTestCase {

    public function testRedirectsWhenNoSession() {
        $conn = new MockConnection();
        $session = [];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = limpar_historico_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: index.php', $result);
    }

    public function testRedirectsWhenNotAdmin() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'bombeiro'
        ];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = limpar_historico_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: index.php', $result);
    }

    public function testRedirectsWhenNotPostMethod() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'admin'
        ];
        $post = [];
        $server = ['REQUEST_METHOD' => 'GET'];

        $result = limpar_historico_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: historico_manutencao.php', $result);
    }

    public function testRedirectsWhenCsrfTokenMissing() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = limpar_historico_logic($conn, $session, $post, $server);
        $this->assertTrue(strpos($result, 'message=Erro%3A+Token+CSRF+inv%C3%A1lido.') !== false);
    }

    public function testRedirectsWhenCsrfTokenInvalid() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'invalid_token'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = limpar_historico_logic($conn, $session, $post, $server);
        $this->assertTrue(strpos($result, 'message=Erro%3A+Token+CSRF+inv%C3%A1lido.') !== false);
    }

    public function testClearsHistorySuccessfully() {
        $conn = new class extends MockConnection {
            public $query_executed = false;
            public function query($query) {
                $this->queries[] = $query;
                if (strpos($query, 'UPDATE bd_extintores') !== false && strpos($query, 'SET manutencao_n2 = NULL') !== false) {
                    $this->query_executed = true;
                    return true; // Simulate success
                }
                return parent::query($query);
            }
        };

        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = limpar_historico_logic($conn, $session, $post, $server);

        $this->assertTrue(strpos($result, 'message=Hist%C3%B3rico+de+manuten%C3%A7%C3%B5es+limpo+com+sucesso') !== false);
        $this->assertTrue($conn->query_executed);
    }

    public function testHandlesDatabaseFailure() {
        $conn = new class extends MockConnection {
            public function query($query) {
                $this->queries[] = $query;
                if (strpos($query, 'UPDATE bd_extintores') !== false && strpos($query, 'SET manutencao_n2 = NULL') !== false) {
                    return false; // Simulate failure
                }
                return parent::query($query);
            }
            public $error = "Mock DB Error";
        };

        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = limpar_historico_logic($conn, $session, $post, $server);

        $this->assertTrue(strpos($result, 'message=Erro+ao+limpar+o+hist%C3%B3rico+de+manuten%C3%A7%C3%A3o.') !== false);
    }
}
?>
