<?php
/**
 * Wrapper for testing novo_extintor.php without side effects.
 * We pass arguments to simulate different session states.
 */

function mock_header($str, $replace = true, $http_response_code = 0) {
    // We echo the test signal for any header to maintain parity with the old behavior
    // that captured any "Cannot modify header information" warning.
    echo "\n[TEST_HEADERS_SENT]\n";
}

// A custom stream wrapper to intercept `config/db_conexao.php` and provide a mock
// database connection that always succeeds, preventing the script from exiting
// early when the actual database is unavailable.
class MockDBStream {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        // If the script tries to include the actual db_conexao.php,
        // we intercept it and return our mock code.
        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                // Mock db_conexao.php
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
                    }; }
                    public function close() {}
                }

                \$conn = new MockMySQLi();

                if (session_status() === PHP_SESSION_ACTIVE && empty(\$_SESSION['csrf_token'])) {
                    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            ?>";
        } else {
            // Restore original file wrapper to read the file, avoiding recursion
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                // Rewrite header() calls to mock_header()
                $this->content = preg_replace('/\bheader\s*\(/i', 'mock_header(', $this->content);
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStream");
                return false;
            }

            // Rewrite header(...) calls to use our mock function to safely capture headers in CLI
            // We use a negative lookbehind to avoid matching object method calls like `$obj->header()`
            $this->content = preg_replace('/(?<!->|::)\bheader\s*\(/i', '\mock_header(', $this->content);

            // Re-register the mock wrapper
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
        // Return a dummy stat array to satisfy require/include
        return [
            'dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1,
            'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000,
            'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1
        ];
    }
}

// Register our mock stream wrapper
stream_wrapper_unregister("file");
stream_wrapper_register("file", "MockDBStream");

// In PHP 5.3+, we can reliably check the response code to see if a redirect
// header was sent, as header() correctly updates the response code even in CLI.
// We handle errors because if the script already started outputting data
// before calling header(), PHP throws a "Cannot modify header information" warning.
$captured_headers = [];
set_error_handler(function($errno, $errstr) use (&$captured_headers) {
    if (strpos($errstr, 'Cannot modify header information') !== false) {
        $captured_headers[] = "header_modified";
        return true;
    }
    return false;
});

// Output the detected redirect at the end so the test runner can assert on it.
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
}

// We'll capture the output
ob_start();

// Include the target file using the mock stream wrapper
try {
    include __DIR__ . '/../novo_extintor.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

$output = ob_get_clean();
echo $output;

// Restore original file wrapper just in case
stream_wrapper_restore("file");
?>