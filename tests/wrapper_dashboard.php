<?php
/**
 * Wrapper for testing dashboard.php without side effects.
 */

function mock_header($str, $replace = true, $http_response_code = 0) {
    echo "\n[TEST_HEADERS_SENT]\n";
}

class MockDBStreamDashboard {
    private $position;
    private $content;
    private $fp;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        // bypass mock db for files in /tmp so tempnam/file_put_contents work
        if (strpos($realPath, sys_get_temp_dir()) !== false) {
            stream_wrapper_restore("file");
            $success = false;
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                $this->fp = fopen($realPath, $mode);
                $success = true;
            } else if (strpos($mode, 'w') !== false || strpos($mode, 'a') !== false || strpos($mode, 'c') !== false) {
                $this->fp = fopen($realPath, $mode);
                $this->content = '';
                $success = ($this->fp !== false);
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamDashboard");
            $this->position = 0;
            return $success;
        } elseif (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockMySQLiResult {
                    private \$rows = [];
                    private \$position = 0;
                    public \$num_rows = 0;

                    public function __construct(\$rows = []) {
                        \$this->rows = \$rows;
                        \$this->num_rows = count(\$rows);
                    }

                    public function fetch_assoc() {
                        if (\$this->position < count(\$this->rows)) {
                            return \$this->rows[\$this->position++];
                        }
                        return null;
                    }
                }

                class MockMySQLi {
                    public function set_charset(\$charset) {}
                    public function query(\$query) {
                        if (strpos(\$query, 'historico_manutencao') !== false) {
                            return new MockMySQLiResult([
                                ['tipo_manutencao' => 'Preventiva', 'total' => 10],
                                ['tipo_manutencao' => 'Corretiva', 'total' => 5]
                            ]);
                        } elseif (strpos(\$query, 'proxima_manutencao_n2') !== false) {
                            return new MockMySQLiResult([
                                ['proxima_manutencao_n2' => '2025-01-01', 'total' => 20]
                            ]);
                        } elseif (strpos(\$query, 'tip_extintor') !== false) {
                            return new MockMySQLiResult([
                                ['tip_extintor' => 'AP', 'total' => 30],
                                ['tip_extintor' => 'CO2', 'total' => 10]
                            ]);
                        }
                        return new MockMySQLiResult();
                    }
                    public function close() {}
                }

                \$conn = new MockMySQLi();
            ?>";
            $this->fp = false;
        } else {
            stream_wrapper_restore("file");
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                if ($this->content === false) {
                    stream_wrapper_unregister("file");
                    stream_wrapper_register("file", "MockDBStreamDashboard");
                    return false;
                }
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamDashboard");
                return false;
            }

            // Mock headers
            $this->content = preg_replace('/(?<!->|::)\bheader\s*\(/i', '\mock_header(', $this->content);

            // Change cache directory to /tmp
            if (strpos($realPath, 'dashboard.php') !== false) {
                // Change __DIR__ . '/cache' to sys_get_temp_dir() . '/cache'
                $this->content = str_replace("\$cacheDir = __DIR__ . '/cache';", "\$cacheDir = sys_get_temp_dir() . '/cache';", $this->content);
            }

            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamDashboard");
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
            $written = fwrite($this->fp, $data);
            if ($written !== false) {
                $this->content .= $data;
                $this->position += $written;
            }
            return $written;
        }
        return false;
    }

    public function stream_eof() {
        return $this->position >= strlen($this->content);
    }

    public function stream_stat() { return []; }
    public function stream_set_option($option, $arg1, $arg2) { return false; }
    public function stream_cast($cast_as) { return $this->fp; }
    public function stream_metadata($path, $option, $value) { return true; }

    public function rename($path_from, $path_to) {
        $realFrom = str_replace('mockdb://', '', $path_from);
        $realTo = str_replace('mockdb://', '', $path_to);
        stream_wrapper_restore("file");
        $result = rename($realFrom, $realTo);
        stream_wrapper_unregister("file");
        stream_wrapper_register("file", "MockDBStreamDashboard");
        return $result;
    }

    public function url_stat($path, $flags) {
        $realPath = str_replace('mockdb://', '', $path);
        if (strpos($realPath, sys_get_temp_dir()) !== false) {
             stream_wrapper_restore("file");
             if (file_exists($realPath)) {
                $stat = stat($realPath);
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamDashboard");
                return $stat;
             }
             stream_wrapper_unregister("file");
             stream_wrapper_register("file", "MockDBStreamDashboard");
             return false; // Return false if temp file doesn't exist yet
        }
        return ['dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000, 'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1];
    }
}

// Clean cache
$cacheDir = sys_get_temp_dir() . '/cache';
if (file_exists($cacheDir . '/dashboard_data.json')) {
    unlink($cacheDir . '/dashboard_data.json');
}

stream_wrapper_unregister("file");
stream_wrapper_register("file", "MockDBStreamDashboard");

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
    if (isset($state['cache_data'])) {
        stream_wrapper_restore("file");
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        // Write the cache file using regular file functions before starting the stream wrapper test
        file_put_contents($cacheDir . '/dashboard_data.json', json_encode($state['cache_data']));
        stream_wrapper_unregister("file");
        stream_wrapper_register("file", "MockDBStreamDashboard");
    }
}

ob_start();

try {
    include __DIR__ . '/../dashboard.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

$output = ob_get_clean();
echo $output;

stream_wrapper_restore("file");
?>