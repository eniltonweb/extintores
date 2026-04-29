<?php
/**
 * Wrapper for testing exportar_historico_cobertura.php without side effects.
 */

function mock_header($str, $replace = true, $http_response_code = 0) {
    if (stripos($str, 'Location:') === 0) {
        echo "\n[REDIRECT] " . trim(substr($str, 9)) . "\n";
    }
    if (stripos($str, 'Content-Type:') === 0) {
        echo "\n[CONTENT-TYPE] " . trim(substr($str, 13)) . "\n";
    }
}

class MockDBStream {
    private $position;
    private $content;
    private $fp;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        if (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockMySQLiResult {
                    public \$num_rows = 0;
                    public \$rows = [];
                    public \$current_row = 0;
                    public function __construct(\$rows = []) {
                        \$this->rows = \$rows;
                        \$this->num_rows = count(\$rows);
                    }
                    public function fetch_assoc() {
                        return \$this->current_row < \$this->num_rows ? \$this->rows[\$this->current_row++] : null;
                    }
                }

                class MockMySQLi {
                    public \$queries = [];
                    public \$mock_results = [];
                    public function set_charset(\$charset) {}
                    public function query(\$query) {
                        \$this->queries[] = \$query;
                        foreach (\$this->mock_results as \$pattern => \$rows) {
                            if (strpos(\$query, \$pattern) !== false) {
                                return new MockMySQLiResult(\$rows);
                            }
                        }
                        return new MockMySQLiResult();
                    }
                    public function close() {}
                }

                \$conn = new MockMySQLi();
                if (isset(\$GLOBALS['mock_db_results'])) {
                    \$conn->mock_results = \$GLOBALS['mock_db_results'];
                }
            ?>";
            $this->fp = false;
        } else {
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                $this->fp = fopen($realPath, $mode);
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStream");
                return false;
            }

            // Rewrite header(...) calls to use our mock function to safely capture headers in CLI
            // We use a negative lookbehind to avoid matching object method calls like `$obj->header()`
            $this->content = preg_replace('/(?<!->|::)\bheader\s*\(/i', '\mock_header(', $this->content);

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

    public function stream_write($data) {
        if ($this->fp) {
            return fwrite($this->fp, $data);
        }
        return 0;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->content);
    }

    public function stream_stat() {
        return [];
    }

    public function url_stat($path, $flags) {
        $realPath = str_replace('mockdb://', '', $path);
        stream_wrapper_restore("file");
        if (file_exists($realPath)) {
            $stat = stat($realPath);
        } else {
            $stat = [
                'dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1,
                'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000,
                'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1
            ];
        }
        stream_wrapper_unregister("file");
        stream_wrapper_register("file", "MockDBStream");
        return $stat;
    }

    public function stream_cast($cast_as) {
        return $this->fp;
    }
}

stream_wrapper_unregister("file");
stream_wrapper_register("file", "MockDBStream");

// Ensure output is flushed even on exit()
register_shutdown_function(function() {
    $output = ob_get_clean();
    echo $output;
    stream_wrapper_restore("file");
});

session_start();

if (isset($argv[1])) {
    $state = json_decode($argv[1], true);
    if (isset($state['session'])) {
        $_SESSION = $state['session'];
    }
    if (isset($state['get'])) {
        $_GET = $state['get'];
    }
    if (isset($state['db_results'])) {
        $GLOBALS['mock_db_results'] = $state['db_results'];
    }
}

ob_start();

try {
    include __DIR__ . '/../exportar_historico_cobertura.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}
?>
