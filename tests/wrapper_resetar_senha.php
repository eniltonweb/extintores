<?php
/**
 * Wrapper for testing resetar_senha.php without side effects.
 * We pass arguments to simulate different states.
 */

class MockDBStreamResetar {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockResetarStatement {
                    public \$success = true;
                    public \$error = 'Mock DB Error';

                    public function __construct(\$state) {
                        if (isset(\$state['db_stmt_error'])) {
                            \$this->success = false;
                        }
                    }

                    public function bind_param(...\$args) { return true; }

                    public function execute() {
                        return \$this->success;
                    }

                    public function close() {}
                }

                class MockResetarMySQLi {
                    public \$state;
                    public \$error = 'Mock Connection Error';

                    public function __construct(\$state) {
                        \$this->state = \$state;
                    }

                    public function prepare(\$sql) {
                        if (isset(\$this->state['db_prepare_error'])) {
                            return false;
                        }
                        return new MockResetarStatement(\$this->state);
                    }

                    public function close() {}
                }

                global \$conn;
                \$conn = new MockResetarMySQLi(json_decode(urldecode('" . urlencode(json_encode($GLOBALS['test_state'])) . "'), true));
            ?>";
            return true;
        }

        if (strpos($realPath, 'auditoria.php') !== false) {
            $this->content = "<?php
                function registrar_auditoria(\$conn, \$usuario_id, \$acao, \$detalhes, \$codigo_extintor = null) {
                    echo '[MOCK_AUDITORIA] ' . \$acao . ' - ' . \$detalhes . \"\n\";
                }
            ?>";
            return true;
        }

        if (file_exists($realPath)) {
            $content = file_get_contents($realPath);

            // Bypass session_start to avoid "headers already sent"
            $content = preg_replace('/session_start\s*\(\)\s*;/', '', $content);

            // Bypass header calls
            $content = preg_replace('/header\s*\((.*?)\)\s*;/', 'echo "[MOCK_HEADER] " . $1 . "\n";', $content);

            $this->content = $content;
            return true;
        }

        return false;
    }

    public function stream_read($count) {
        $ret = substr($this->content, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->content);
    }

    public function stream_stat() {
        return [];
    }
}

stream_wrapper_register('mockdb', 'MockDBStreamResetar');

// Obter estado inicial do JSON
$state_json = $argv[1] ?? '{}';
$state = json_decode($state_json, true);
$GLOBALS['test_state'] = $state;

// Configurar estado de Mock
$_SESSION = $state['session'] ?? [];
$_POST = $state['post'] ?? [];
$_GET = $state['get'] ?? [];
$_SERVER['REQUEST_METHOD'] = $state['method'] ?? 'GET';

// Se não tiver password_hash, usamos uma genérica ou vamos reescrever
// Mas o PHP tem password_hash.

// Incluir o arquivo de destino através do wrapper
ob_start();
try {
    include 'mockdb://' . __DIR__ . '/../resetar_senha.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Error: " . $e->getMessage();
}
$output = ob_get_clean();

echo $output;
