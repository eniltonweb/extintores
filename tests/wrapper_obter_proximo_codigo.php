<?php
/**
 * Wrapper for testing obter_proximo_codigo.php without side effects.
 * We pass arguments to simulate different session states.
 */

// A custom stream wrapper to intercept `config/db_conexao.php` and provide a mock
// database connection that always succeeds, preventing the script from exiting
// early when the actual database is unavailable.
class MockDBStreamObterProximoCodigo {
    public $context;
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
                    public \$num_rows = 1;
                    public function fetch_assoc() {
                        return ['codigo' => 'PREDIO-100'];
                    }
                }

                class MockMySQLi {
                    public function set_charset(\$charset) {}
                    public function query(\$query) { return new MockMySQLiResult(); }
                    public function prepare(\$query) { return new class {
                        public function bind_param(...\$args) {}
                        public function execute() { return true; }
                        public function get_result() { return new MockMySQLiResult(); }
                        public \$error = '';
                    };}
                    public function close() {}
                }
                \$conn = new MockMySQLi();
            ?>";
            $this->position = 0;
            return true;
        }

        $this->content = file_get_contents($realPath);

        // Capture header calls and mock them if they exist in the file.
        $this->content = preg_replace('/(?<!->|::)\bheader\s*\(\s*(.*?)\s*\)/i', 'mock_header($1)', $this->content);

        // Replace config inclusion with mock include
        $this->content = str_replace(
            "require_once __DIR__ . '/config/db_conexao.php';",
            "include 'mockdb://' . __DIR__ . '/config/db_conexao.php';",
            $this->content
        );

        // Mock filter_input because CLI does not populate INPUT_GET
        $this->content = str_replace(
            "filter_input(INPUT_GET, 'predio', FILTER_SANITIZE_SPECIAL_CHARS)",
            "isset(\$_GET['predio']) ? htmlspecialchars(\$_GET['predio'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null",
            $this->content
        );

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

    public function url_stat($path, $flags) {
        $realPath = str_replace('mockdb://', '', $path);
        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            return [
                'dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1,
                'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1024,
                'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1
            ];
        }
        return stat($realPath);
    }

    public function stream_stat() {
        return [];
    }

    public function stream_set_option($option, $arg1, $arg2) {
        return false; // Not implemented
    }
}

// Ensure error reporting doesn't output warnings for redefined constants
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$json_data = $argv[1] ?? '{}';
$state = json_decode($json_data, true);

function mock_header($str) {
    echo "\n[MOCK_HEADER] $str\n";
}

stream_wrapper_register('mockdb', 'MockDBStreamObterProximoCodigo');

// Capture output to prevent raw HTML/headers from spilling into the console
ob_start();

// We register a shutdown function to ensure that if the included script calls exit(),
// we still capture and flush the output buffers cleanly.
register_shutdown_function(function() {
    $output = ob_get_clean();
    echo $output;
});

// Seed global state
if (isset($state['session'])) {
    session_start();
    $_SESSION = $state['session'];
}

if (isset($state['get']['predio'])) {
    $_GET['predio'] = $state['get']['predio'];
}

// Overwrite filter_input since we cannot populate INPUT_GET reliably from CLI in some SAPIs
function filter_input_mock($type, $var_name, $filter = FILTER_DEFAULT) {
    if ($type === INPUT_GET && isset($_GET[$var_name])) {
        return $_GET[$var_name];
    }
    return null;
}

// Mock filter_input using stream wrapper manipulation
// Since filter_input behavior can't be easily mocked directly, we rewrite it on the fly

// We include via our mock wrapper.
try {
    include 'mockdb://' . realpath(__DIR__ . '/../obter_proximo_codigo.php');
} catch (Exception $e) {
    echo "Exception caught in wrapper: " . $e->getMessage();
}

?>