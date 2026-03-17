<?php
// EnviarAlertasTest.php
require_once __DIR__ . '/runner.php';

class EnviarAlertasTest extends MiniTestCase {
    public function testPHPMailerExceptionOutput() {
        $script = <<<'EOT'
<?php
// Mock PHPMailer
namespace PHPMailer\PHPMailer {
    class Exception extends \Exception {}
    class PHPMailer {
        public $ErrorInfo = 'Mocked Mail Error';
        public $Host; public $SMTPAuth; public $Username; public $Password;
        public $SMTPSecure; public $Port; public $SMTPKeepAlive;
        public $Subject; public $Body;
        public function __construct($exceptions = null) {}
        public function isSMTP() {}
        public function setFrom($address, $name = '', $auto = true) {}
        public function addAddress($address, $name = '') {}
        public function send() {
            throw new Exception("Test Exception");
        }
        public function clearAddresses() {}
        public function smtpClose() {}
        public function isHTML($isHtml = true) {}
    }
}

namespace {
    // Session setup
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['user_level'] = 'admin';

    $code = file_get_contents(__DIR__ . '/enviar_alertas.php');
    $code = str_replace(
        "require_once __DIR__ . '/config/db_conexao.php';",
        "\$conn = new class {
            public function set_charset() { return true; }
            public function query() {
                return new class {
                    public \$num_rows = 1;
                    private \$returned = false;
                    public function fetch_assoc() {
                        if (!\$this->returned) {
                            \$this->returned = true;
                            return ['codigo' => 'TEST-001', 'proxima_manutencao_n2' => '2023-12-31'];
                        }
                        return null;
                    }
                };
            }
            public function close() { return true; }
        };",
        $code
    );

    // Remove the opening <?php tag from the file content so we can eval it
    $code = str_replace("<?php", "", $code);

    // Evaluate the modified code
    ob_start();
    eval($code);
    echo ob_get_clean();
}
EOT;

        $temp_file = __DIR__ . '/../temp_test_enviar_alertas.php';
        file_put_contents($temp_file, $script);

        // Execute it and capture output
        exec('php ' . escapeshellarg($temp_file) . ' 2>&1', $output, $return_var);
        $output_str = implode("\n", $output);

        unlink($temp_file);

        $this->assertEquals(0, $return_var, "Script should return 0 exit code. Output: " . $output_str);

        $this->assertTrue(
            strpos($output_str, 'A mensagem não pôde ser enviada. Erro: Mocked Mail Error') !== false,
            "Output should contain the exception error message. Got: " . $output_str
        );
    }
}
