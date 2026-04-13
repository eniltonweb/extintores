<?php
require_once __DIR__ . '/MockDatabase.php';

$_SERVER['SCRIPT_FILENAME'] = 'phpunit.php';
require_once __DIR__ . '/../registrar_usuario.php';

// Mock da função auditoria para testes
if (!function_exists('auditoria')) {
    function auditoria($acao, $id_extintor, $user_id, $user_level, $descricao) {
        $GLOBALS['auditoria_chamada'] = true;
        $GLOBALS['auditoria_dados'] = func_get_args();
    }
}

class RegistrarUsuarioTest extends MiniTestCase {

    public function setUp() {
        $GLOBALS['auditoria_chamada'] = false;
        $GLOBALS['auditoria_dados'] = [];
    }

    protected function assertFalse($condition, $message = '') {
        if ($condition !== false) {
            throw new Exception("Expected false, but got " . var_export($condition, true) . ". {$message}");
        }
    }

    public function testRegistrationSuccess() {
        $this->setUp();
        $mockConn = new MockConnection();
        $GLOBALS['conn'] = $mockConn;

        // We simulate the fetch logic by setting mock rows
        // SELECT (username check - 0 rows means not exists)
        $mockConn->mock_query_results["SELECT id FROM usuarios WHERE username = ?"] = [];

        // INSERT (success) - executed=true mock handled implicitly by MockStatement

        $session_data = [
            'csrf_token' => 'test_token',
            'user_id' => 1,
            'user_level' => 'admin'
        ];

        $post_data = [
            'csrf_token' => 'test_token',
            'username' => 'newuser',
            'password' => 'secret123',
            'user_level' => 'bombeiro'
        ];

        // If the real auditoria function is loaded, it will prepare this statement
        $mockConn->mock_query_results["SELECT id FROM bd_extintores WHERE codigo = ?"] = [];

        $result = process_registration($mockConn, $post_data, $session_data);

        $this->assertEquals("Usuário registrado com sucesso.", $result);

        if (isset($GLOBALS['auditoria_chamada']) && $GLOBALS['auditoria_chamada']) {
            $this->assertTrue($GLOBALS['auditoria_chamada']);
            $this->assertEquals('Registro de usuário', $GLOBALS['auditoria_dados'][0]);
        } else {
            // Verify real auditoria query was executed
            $auditoriaExecuted = false;
            foreach ($mockConn->queries as $q) {
                if (strpos($q, 'INSERT INTO auditoria_logs') !== false) {
                    $auditoriaExecuted = true;
                    break;
                }
            }
            $this->assertTrue($auditoriaExecuted, "Auditoria query was not executed");
        }
    }

    public function testRegistrationExistingUsername() {
        $this->setUp();
        $mockConn = new MockConnection();

        // SELECT (username check - 1 row means exists)
        $mockConn->mock_query_results["SELECT id FROM usuarios WHERE username = ?"] = [['id' => 1]];

        $session_data = [
            'csrf_token' => 'test_token'
        ];

        $post_data = [
            'csrf_token' => 'test_token',
            'username' => 'existinguser',
            'password' => 'secret123',
            'user_level' => 'admin'
        ];

        $result = process_registration($mockConn, $post_data, $session_data);

        $this->assertEquals("Nome de usuário já existe.", $result);
        $this->assertFalse($GLOBALS['auditoria_chamada']);
    }

    public function testRegistrationCsrfError() {
        $this->setUp();
        $mockConn = new MockConnection();

        $session_data = [
            'csrf_token' => 'valid_token'
        ];

        $post_data = [
            'csrf_token' => 'invalid_token',
            'username' => 'testuser',
            'password' => 'secret123',
            'user_level' => 'admin'
        ];

        $result = process_registration($mockConn, $post_data, $session_data);

        $this->assertEquals("Erro de validação. Tente novamente.", $result);
        $this->assertFalse($GLOBALS['auditoria_chamada']);
    }
}
?>
