<?php
require_once __DIR__ . '/MockDatabase.php';

class LoginValidationTest extends MiniTestCase {

    public function testLoginConsolidatedValidation() {
        $mockConn = new MockConnection();
        $GLOBALS['conn'] = $mockConn;

        // Ensure db_conexao doesn't overwrite $conn, but we need it required
        // so we require login.php directly which will require db_conexao
        // BUT, wait, db_conexao might overwrite $conn. We can just require login.php
        // and then pass our mockConn to process_login.

        // Actually, if we just use a wrapper to include login.php it might be better,
        // but since we wrapped login.php in a function `process_login`, we can just
        // include it. `db_conexao.php` sets `$conn = new mysqli(...)` which might fail
        // if not configured. We can suppress it or it's already mocked in runner.
        // Wait, tests/runner.php or similar already has a strategy for db_conexao?
        // Let's just include login.php and suppress errors, then inject $mockConn.

        $session = [
            'csrf_token' => 'test_csrf'
        ];
        $_SESSION = $session; // Needed for login.php's top level code

        // Include the target file, suppressing the exception from db_conexao if it fails
        try {
            @include_once __DIR__ . '/../login.php';
        } catch (Exception $e) {
            // Ignore DB connection errors in db_conexao.php during tests
        }

        $post = [
            'csrf_token' => 'test_csrf',
            'username' => 'testuser',
            'password' => 'testpass'
        ];

        // Result for login query
        $mockConn->mock_query_results["SELECT * FROM usuarios WHERE username = ?"] = [
            [
                'id' => 1,
                'username' => 'testuser',
                'password' => password_hash('testpass', PASSWORD_DEFAULT),
                'nivel_acesso' => 'admin'
            ]
        ];

        $error = null;
        $result = process_login($mockConn, $post, $session, $error);

        $this->assertTrue($result, "process_login should return true on success");
        $this->assertTrue(isset($session['user_id']), "User ID should be set in session");
        $this->assertEquals(1, $session['user_id']);
        $this->assertEquals('admin', $session['user_level']);
        $this->assertEquals('testuser', $session['user_name']);
    }

    public function testLoginEmptyFields() {
        $mockConn = new MockConnection();
        $GLOBALS['conn'] = $mockConn;

        $session = [
            'csrf_token' => 'test_csrf'
        ];
        $_SESSION = $session;

        try {
            @include_once __DIR__ . '/../login.php';
        } catch (Exception $e) {
            // Ignore DB connection errors in db_conexao.php during tests
        }

        $post = [
            'csrf_token' => 'test_csrf',
            'username' => '',
            'password' => ''
        ];

        $error = null;
        $result = process_login($mockConn, $post, $session, $error);

        $this->assertEquals(false, $result, "process_login should return false on empty fields");
        $this->assertEquals("Preencha todos os campos.", $error);
    }
}
