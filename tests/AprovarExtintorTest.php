<?php

require_once __DIR__ . '/MockDatabase.php';

// Force the inclusion without executing the script
$_SERVER['SCRIPT_FILENAME'] = 'phpunit.php';
require_once __DIR__ . '/../aprovar_extintor.php';

// Include the original auditoria.php, but override its behavior or intercept it?
// The project has `auditoria.php`. Since it's procedural and tested, we can just let it run
// and intercept it if we need, or simply include it, but then the DB queries in it will run on MockDatabase.
// To capture the call, since we can't redefine `auditoria`, we can just check if MockDatabase received the query.
// Wait, `auditoria.php` does:
// $sql = "INSERT INTO auditoria ...";
// $stmt = $conn->prepare($sql);
// So we can check `$conn->statements` for the INSERT INTO auditoria.

// Let's remove the function_exists mock.

class AprovarExtintorTest extends MiniTestCase {

    public function setUp() {
        $GLOBALS['auditoria_calls'] = [];
    }

    public function testRedirectsWhenNoSession() {
        $conn = new MockConnection();
        $session = [];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: index.php', $result);
    }

    public function testRedirectsWhenNotAdmin() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'fornecedor'
        ];
        $post = [];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
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

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: aprovar_extintores.php?message=Erro%3A+M%C3%A9todo+inv%C3%A1lido.', $result);
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

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: aprovar_extintores.php?message=Erro%3A+Falha+na+valida%C3%A7%C3%A3o+de+seguran%C3%A7a.', $result);
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

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: aprovar_extintores.php?message=Erro%3A+Falha+na+valida%C3%A7%C3%A3o+de+seguran%C3%A7a.', $result);
    }

    public function testRedirectsWhenCodigoNotPassed() {
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: aprovar_extintores.php?message=Erro%3A+C%C3%B3digo+do+extintor+n%C3%A3o+encontrado.', $result);
    }

    public function testApprovesExtintorSuccessfully() {
        $this->setUp();
        $conn = new MockConnection();
        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token',
            'codigo' => 'EXT001'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = aprovar_extintor_logic($conn, $session, $post, $server);

        $this->assertEquals('Location: aprovar_extintores.php?message=Extintor+aprovado+com+sucesso.', $result);

        $this->assertTrue(count($conn->statements) > 0);
        $this->assertEquals('EXT001', $conn->statements[0]->params[0]);
    }

    public function testRedirectsWhenPrepareFails() {
        $conn = new MockConnection();
        // Override prepare to return false
        $conn = new class extends MockConnection {
            public function prepare($query) {
                return false;
            }
        };

        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token',
            'codigo' => 'EXT001'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: aprovar_extintores.php?message=Erro%3A+N%C3%A3o+foi+poss%C3%ADvel+preparar+o+statement+para+aprova%C3%A7%C3%A3o+do+extintor.', $result);
    }

    public function testRedirectsWhenExecuteFails() {
        $conn = new MockConnection();
        // Override prepare to return a statement that fails to execute
        $conn = new class extends MockConnection {
            public function prepare($query) {
                return new class($query, $this) extends MockStatement {
                    public function execute() {
                        return false;
                    }
                };
            }
        };

        $session = [
            'user_id' => 1,
            'user_level' => 'admin',
            'csrf_token' => 'valid_token'
        ];
        $post = [
            'csrf_token' => 'valid_token',
            'codigo' => 'EXT001'
        ];
        $server = ['REQUEST_METHOD' => 'POST'];

        $result = aprovar_extintor_logic($conn, $session, $post, $server);
        $this->assertEquals('Location: aprovar_extintores.php?message=Erro%3A+N%C3%A3o+foi+poss%C3%ADvel+aprovar+o+extintor.', $result);
    }
}
?>