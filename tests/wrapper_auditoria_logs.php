<?php
/**
 * Wrapper for testing auditoria_logs.php without side effects.
 * We pass arguments to simulate different session and POST states.
 */

function mock_header($str, $replace = true, $http_response_code = 0) {
    echo "\n[TEST_HEADERS_SENT]\n";
}

class MockDBStreamAuditoriaLogs {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockMySQLiResult {
                    public \$num_rows = 0;
                    public \$rows = [];
                    public function __construct(\$rows = []) {
                        \$this->rows = \$rows;
                        \$this->num_rows = count(\$rows);
                    }
                    public function fetch_assoc() {
                        return array_shift(\$this->rows);
                    }
                }

                class MockStatement {
                    public \$success = true;
                    public \$error = '';
                    public \$query;
                    public \$result;
                    public \$executed = false;

                    public function __construct(\$query, &\$conn) {
                        \$this->query = \$query;
                        if (isset(\$conn->state['db_stmt_error'])) {
                            \$this->success = false;
                            \$this->error = \$conn->state['db_stmt_error'];
                        }
                    }

                    public function bind_param(...\$args) { return true; }

                    public function execute() {
                        \$this->executed = true;
                        return \$this->success;
                    }

                    public function get_result() {
                        return \$this->result;
                    }

                    public function close() {}
                }

                class MockMySQLi {
                    public \$state;
                    public \$error = '';

                    public function __construct(\$state) {
                        \$this->state = \$state;
                        if (isset(\$state['db_prepare_error'])) {
                            \$this->error = \$state['db_prepare_error'];
                        }
                    }

                    public function prepare(\$query) {
                        if (isset(\$this->state['db_prepare_error'])) {
                            return false;
                        }
                        return new MockStatement(\$query, \$this);
                    }

                    public function query(\$query) {
                        if (isset(\$this->state['db_query_error'])) {
                            \$this->error = \$this->state['db_query_error'];
                            return false;
                        }

                        if (strpos(\$query, 'COUNT(*) AS total FROM auditoria_logs') !== false) {
                            return new MockMySQLiResult([['total' => 0]]);
                        }

                        if (strpos(\$query, 'SELECT al.*') !== false) {
                            return new MockMySQLiResult([]);
                        }

                        if (strpos(\$query, 'DELETE FROM auditoria_logs') !== false) {
                            return true; // Simulate success deletion
                        }

                        if (strpos(\$query, 'Todos os logs de auditoria foram apagados') !== false) {
                            return new MockMySQLiResult([['id' => 99]]);
                        }

                        return true;
                    }

                    public function close() {}
                }

                global \$test_state;
                \$conn = new MockMySQLi(\$test_state);

                if (session_status() === PHP_SESSION_ACTIVE && empty(\$_SESSION['csrf_token'])) {
                    \$_SESSION['csrf_token'] = 'test_session_token';
                }
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
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                $this->content = preg_replace('/(?<!->|::)\bheader\s*\(/i', '\mock_header(', $this->content);
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamAuditoriaLogs");
                return false;
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamAuditoriaLogs");
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

stream_wrapper_unregister("file");
stream_wrapper_register("file", "MockDBStreamAuditoriaLogs");

$captured_headers = [];
set_error_handler(function($errno, $errstr) use (&$captured_headers) {
    if (strpos($errstr, 'Cannot modify header information') !== false) {
        $captured_headers[] = "header_modified";
        return true;
    }
    return false;
});

register_shutdown_function(function() use (&$captured_headers) {
    $code = http_response_code();
    if (!empty($captured_headers) || ($code >= 300 && $code < 400)) {
        echo "\n[TEST_HEADERS_SENT]\n";
    }
});

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
        $_SERVER['REQUEST_METHOD'] = 'POST';
    } else {
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}

ob_start();
try {
    include __DIR__ . '/../auditoria_logs.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}
$output = ob_get_clean();
echo $output;

stream_wrapper_restore("file");
