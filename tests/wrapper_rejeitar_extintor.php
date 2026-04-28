<?php
/**
 * Wrapper for testing rejeitar_extintor.php without side effects.
 * We pass arguments to simulate different states.
 */

class MockDBStreamRejeitar {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockRejeitarResult {
                    public \$num_rows = 1;
                    public \$state;

                    public function __construct(\$state) {
                        \$this->state = \$state;
                        if (isset(\$state['extintor_not_found'])) {
                            \$this->num_rows = 0;
                        }
                    }

                    public function fetch_assoc() {
                        return ['id' => 999];
                    }
                }

                class MockRejeitarStatement {
                    public \$success = true;
                    public \$state = null;
                    public \$query;

                    public function __construct(\$state, \$query) {
                        \$this->state = \$state;
                        \$this->query = \$query;
                        if (isset(\$state['db_stmt_error'])) {
                            \$this->success = false;
                        }
                    }

                    public function bind_param(...\$args) { return true; }

                    public function execute() {
                        if (\$this->query === 'DELETE FROM bd_extintores WHERE id = ?' && isset(\$this->state['db_delete_extintor_error'])) {
                            return false;
                        }
                        return \$this->success;
                    }

                    public function get_result() {
                        return new MockRejeitarResult(\$this->state);
                    }

                    public function close() {}
                }

                class MockRejeitarMySQLi {
                    public \$state;

                    public function __construct(\$state) {
                        \$this->state = \$state;
                    }

                    public function prepare(\$query) {
                        if (isset(\$this->state['db_prepare_error'])) {
                            return false;
                        }
                        return new MockRejeitarStatement(\$this->state, \$query);
                    }

                    public function close() {}
                }

                global \$test_state;
                \$conn = new MockRejeitarMySQLi(\$test_state);
            ?>";
        } else {
            // Restore wrapper and intercept headers in the target file
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $content = file_get_contents($realPath);

                // Simple regex to convert header(...) to echo "\n[MOCK_HEADER] ...\n"
                $content = preg_replace('/header\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', 'echo "\n[MOCK_HEADER] $1\n"', $content);
                // Replace filter_input so we can test it from CLI
                $content = str_replace("filter_input(INPUT_POST, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS)", "(\$_POST['codigo'] ?? null)", $content);
                $this->content = $content;
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamRejeitar");
                return false;
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamRejeitar");
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
stream_wrapper_register("file", "MockDBStreamRejeitar");

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
    include __DIR__ . '/../rejeitar_extintor.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

stream_wrapper_restore("file");
?>