<?php
require_once __DIR__ . '/MockDatabase.php';

class LoginValidationTest extends MiniTestCase {

    private function getWrappedLoginCode() {
        $login_code = file_get_contents(__DIR__ . '/../login.php');

        // Remove session calls
        $login_code = str_replace('session_start();', '//session_start();', $login_code);
        $login_code = str_replace('session_regenerate_id(true);', '//session_regenerate_id(true);', $login_code);

        // Fix path to db_conexao
        $login_code = str_replace("require_once __DIR__ . '/config/db_conexao.php';", "", $login_code);

        // Replace header and exit
        $login_code = preg_replace('/header\(.*\);/', '$GLOBALS["redirected"] = true;', $login_code);
        $login_code = str_replace('exit();', 'return;', $login_code);

        // Replace filter_input for POST as it doesn't work well in CLI
        $login_code = preg_replace(
            '/filter_input\s*\(\s*INPUT_POST\s*,\s*\'(\w+)\'\s*,\s*FILTER_SANITIZE_SPECIAL_CHARS\s*\)/',
            'htmlspecialchars((string)($_POST[\'$1\'] ?? ""), ENT_QUOTES, "UTF-8")',
            $login_code
        );
        $login_code = preg_replace(
            '/filter_input\s*\(\s*INPUT_POST\s*,\s*\'(\w+)\'\s*,\s*FILTER_DEFAULT\s*\)/',
            '($_POST[\'$1\'] ?? "")',
            $login_code
        );

        // Inject global $conn
        $login_code = str_replace('<?php', '<?php global $conn, $error;', $login_code);

        return $login_code;
    }

    public function testLoginConsolidatedValidation() {
        $mockConn = new MockConnection();
        $GLOBALS['conn'] = $mockConn;

        // Mock SESSION
        $_SESSION = [
            'csrf_token' => 'test_csrf'
        ];

        // Mock POST
        $_POST = [
            'csrf_token' => 'test_csrf',
            'username' => 'testuser',
            'password' => 'testpass'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Result for login query
        $mockConn->mock_query_results["SELECT * FROM usuarios WHERE username = ?"] = [
            [
                'id' => 1,
                'username' => 'testuser',
                'password' => password_hash('testpass', PASSWORD_DEFAULT),
                'nivel_acesso' => 'admin'
            ]
        ];

        ob_start();
        $login_code = $this->getWrappedLoginCode();
        $error = null;
        eval('?>' . $login_code);
        ob_end_clean();

        $this->assertTrue(isset($_SESSION['user_id']), "User ID should be set in session");
        $this->assertEquals(1, $_SESSION['user_id']);
        $this->assertEquals('admin', $_SESSION['user_level']);
        $this->assertEquals('testuser', $_SESSION['user_name']);
    }

    public function testLoginEmptyFields() {
        $mockConn = new MockConnection();
        $GLOBALS['conn'] = $mockConn;

        $_SESSION = [
            'csrf_token' => 'test_csrf'
        ];

        $_POST = [
            'csrf_token' => 'test_csrf',
            'username' => '',
            'password' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        ob_start();
        $login_code = $this->getWrappedLoginCode();
        $error = null;
        eval('?>' . $login_code);
        ob_end_clean();

        $this->assertEquals("Preencha todos os campos.", $error);
    }
}
