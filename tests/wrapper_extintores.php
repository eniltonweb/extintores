<?php
/**
 * Wrapper for testing extintores.php without side effects.
 * We pass arguments to simulate different session and POST states.
 */

function mock_header($str, $replace = true, $http_response_code = 0) {
    echo "\n[TEST_HEADERS_SENT]\n";
}

class MockDBStream {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockMySQLiResult {
                    public \$num_rows = 0;
                    public function fetch_assoc() { return null; }
                }

                class MockMySQLi {
                    public function set_charset(\$charset) {}
                    public function query(\$query) { return new MockMySQLiResult(); }
                    public function prepare(\$query) { return new class {
                        public function bind_param(...\$args) {}
                        public function execute() { return true; }
                        public function get_result() { return new MockMySQLiResult(); }
                        public \$error = '';
                        public function close() {}
                    }; }
                    public function close() {}
                }

                \$conn = new MockMySQLi();

                if (session_status() === PHP_SESSION_ACTIVE && empty(\$_SESSION['csrf_token'])) {
                    \$_SESSION['csrf_token'] = 'test_session_token';
                }
            ?>";
        } else {
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                $this->content = preg_replace('/(?<!->|::)\bheader\s*\(/i', '\mock_header(', $this->content);
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStream");
                return false;
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStream");
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
stream_wrapper_register("file", "MockDBStream");

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

if (isset($argv[1])) {
    $state = json_decode($argv[1], true);
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
    include __DIR__ . '/../extintores.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}
$output = ob_get_clean();
echo $output;

stream_wrapper_restore("file");
