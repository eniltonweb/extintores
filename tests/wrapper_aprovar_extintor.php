<?php
/**
 * Wrapper for testing aprovar_extintor.php without side effects.
 * We pass arguments to simulate different states.
 */

class MockDBStreamAprovar {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockAprovarStatement {
                    public \$success = true;
                    public \$error_state = null;

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

                class MockAprovarMySQLi {
                    public \$state;

                    public function __construct(\$state) {
                        \$this->state = \$state;
                    }

                    public function prepare(\$query) {
                        if (isset(\$this->state['db_prepare_error'])) {
                            return false;
                        }
                        return new MockAprovarStatement(\$this->state);
                    }

                    public function close() {}
                }

                global \$test_state;
                \$conn = new MockAprovarMySQLi(\$test_state);
            ?>";
        } elseif (strpos($realPath, 'auditoria.php') !== false) {
            $this->content = "<?php
                if (!function_exists('auditoria')) {
                    function auditoria(\$acao, \$codigo_extintor, \$user_id, \$user_level, \$detalhes = '') {
                        echo \"\\n[MOCK_AUDITORIA] \$acao | \$codigo_extintor | \$user_id | \$user_level | \$detalhes\\n\";
                    }
                }
            ?>";
        } else {
            // Restore wrapper and intercept headers in the target file
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $content = file_get_contents($realPath);

                // Simple regex to convert header(...) to echo "[MOCK_HEADER] ..."
                $content = preg_replace('/(?<!->|::)\bheader\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', 'echo "\n[MOCK_HEADER] $1\n"', $content);
                $content = preg_replace('/(?<!->|::)\bheader\s*\(\s*(\$redirect)\s*\)/i', 'echo "\n[MOCK_HEADER] " . $1 . "\n"', $content);

                // Replace filter_input so we can test it from CLI
                $content = str_replace("filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS)", "(\$_POST['codigo'] ?? null)", $content);
                $this->content = $content;
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamAprovar");
                return false;
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamAprovar");
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
stream_wrapper_register("file", "MockDBStreamAprovar");

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

    if (isset($state['server'])) {
        $_SERVER = array_merge($_SERVER, $state['server']);
    }
}

// Execute the target script
try {
    $target = realpath(__DIR__ . '/../aprovar_extintor.php');
    $_SERVER['SCRIPT_FILENAME'] = $target;
    include $target;
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

stream_wrapper_restore("file");
?>