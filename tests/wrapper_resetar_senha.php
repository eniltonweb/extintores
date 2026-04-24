<?php
/**
 * Wrapper for testing resetar_senha.php without side effects.
 * We pass arguments to simulate different states.
 */

class MockDBStreamResetarSenha {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockResetarSenhaStatement {
                    public \$success = true;
                    public \$error = 'mock_stmt_error';

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

                class MockResetarSenhaMySQLi {
                    public \$state;
                    public \$error = 'mock_db_error';

                    public function __construct(\$state) {
                        \$this->state = \$state;
                    }

                    public function prepare(\$query) {
                        if (isset(\$this->state['db_prepare_error'])) {
                            return false;
                        }
                        return new MockResetarSenhaStatement(\$this->state);
                    }

                    public function close() {}
                }

                global \$test_state;
                \$conn = new MockResetarSenhaMySQLi(\$test_state);
            ?>";
        } elseif (strpos($realPath, 'auditoria.php') !== false) {
            $this->content = "<?php
                function auditoria(\$acao, \$codigo_extintor, \$user_id, \$user_level, \$detalhes = '') {
                    echo \"\\n[MOCK_AUDITORIA] \$acao | \$codigo_extintor | \$user_id | \$user_level | \$detalhes\\n\";
                }
            ?>";
        } else {
            // Restore wrapper and intercept headers in the target file
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $content = file_get_contents($realPath);

                // Simple regex to convert header(...) to echo "[MOCK_HEADER] ..."
                $content = preg_replace('/header\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', 'echo "\n[MOCK_HEADER] $1\n"', $content);
                // Replace filter_input so we can test it from CLI
                $content = str_replace("filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)", "(\$_POST['id'] ?? null)", $content);
                $content = str_replace("filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT)", "(\$_GET['id'] ?? null)", $content);
                $this->content = $content;
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamResetarSenha");
                return false;
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamResetarSenha");
        }

        $this->position = 0;
        return true;
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

    public function stream_set_option($option, $arg1, $arg2) {
        return false;
    }

    public function url_stat($path, $flags) {
        return [
            'dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1,
            'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000,
            'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1
        ];
    }
}

// Intercept file inclusions
stream_wrapper_unregister("file");
stream_wrapper_register("file", "MockDBStreamResetarSenha");

session_start();

global $test_state;
$test_state = [];

if (isset($argv[1])) {
    $state = json_decode($argv[1], true);
    $test_state = $state;

    if (isset($state['session'])) {
        $_SESSION = $state['session'];
    }

    if (isset($state['post'])) {
        $_POST = $state['post'];
    }

    if (isset($state['get'])) {
        $_GET = $state['get'];
    }

    if (isset($state['server'])) {
        $_SERVER = array_merge($_SERVER, $state['server']);
    }
}

// Execute the target script
try {
    include __DIR__ . '/../resetar_senha.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

stream_wrapper_restore("file");
?>