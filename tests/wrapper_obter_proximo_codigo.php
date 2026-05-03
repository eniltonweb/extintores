<?php
/**
 * Wrapper for testing obter_proximo_codigo.php without side effects.
 * We pass arguments to simulate different states.
 */

class MockDBStreamProximoCodigo {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockResultObterCodigo {
                    public \$rows;
                    public \$num_rows;
                    private \$position = 0;

                    public function __construct(\$rows) {
                        \$this->rows = \$rows;
                        \$this->num_rows = count(\$rows);
                    }

                    public function fetch_assoc() {
                        if (\$this->position < \$this->num_rows) {
                            return \$this->rows[\$this->position++];
                        }
                        return null;
                    }
                }

                class MockStatementObterCodigo {
                    public \$state;
                    public function __construct(\$state) {
                        \$this->state = \$state;
                    }
                    public function bind_param(...\$args) { return true; }
                    public function execute() { return true; }
                    public function get_result() {
                        return new MockResultObterCodigo(\$this->state['db_rows'] ?? []);
                    }
                    public function close() {}
                }

                class MockMySQLiObterCodigo {
                    public \$state;
                    public function __construct(\$state) {
                        \$this->state = \$state;
                    }
                    public function prepare(\$query) {
                        return new MockStatementObterCodigo(\$this->state);
                    }
                    public function close() {}
                }

                global \$test_state;
                \$conn = new MockMySQLiObterCodigo(\$test_state);
            ?>";
        } else {
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $content = file_get_contents($realPath);

                // Replace filter_input so we can test it from CLI
                $content = str_replace("filter_input(INPUT_GET, 'predio', FILTER_SANITIZE_SPECIAL_CHARS)", "(\$_GET['predio'] ?? null)", $content);
                $this->content = $content;
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamProximoCodigo");
                return false;
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamProximoCodigo");
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
stream_wrapper_register("file", "MockDBStreamProximoCodigo");

global $test_state;
$test_state = [];

if (isset($argv[1])) {
    $state = json_decode($argv[1], true);
    $test_state = $state;

    if (isset($state['get'])) {
        $_GET = $state['get'];
    }
}

// Execute the target script
try {
    $target = realpath(__DIR__ . '/../obter_proximo_codigo.php');
    $_SERVER['SCRIPT_FILENAME'] = $target;
    include $target;
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

stream_wrapper_restore("file");
?>
