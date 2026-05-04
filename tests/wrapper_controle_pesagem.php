<?php
/**
 * Wrapper for testing controle_pesagem.php sem efeitos colaterais.
 */

function mock_header($str, $replace = true, $http_response_code = 0) {
    echo "\n[TEST_HEADERS_SENT]\n";
}

class MockDBStreamControlePesagem {
    private $position;
    private $content;
    private $fp;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

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
            stream_wrapper_register("file", "MockDBStreamControlePesagem");
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
                        if (strpos(\$query, 'SELECT id, codigo FROM bd_extintores') !== false) {
                            return new MockMySQLiResult([
                                ['id' => 1, 'codigo' => 'EXT-001'],
                                ['id' => 2, 'codigo' => 'EXT-002']
                            ]);
                        }
                        if (strpos(\$query, 'pesagens_extintores') !== false) {
                            return new MockMySQLiResult([
                                [
                                    'codigo' => 'EXT-001',
                                    'peso_aferido' => 10.50,
                                    'percentual_perda' => 0.5,
                                    'situacao' => 'Aprovado',
                                    'data_pesagem' => '2023-01-01',
                                    'proxima_pesagem' => '2023-07-01',
                                    'usuario' => 'Inspetor 1'
                                ],
                                [
                                    'codigo' => 'EXT-002',
                                    'peso_aferido' => 8.00,
                                    'percentual_perda' => 15.0,
                                    'situacao' => 'Reprovado',
                                    'data_pesagem' => '2023-02-01',
                                    'proxima_pesagem' => '2023-08-01',
                                    'usuario' => 'Inspetor 2'
                                ]
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
                    stream_wrapper_register("file", "MockDBStreamControlePesagem");
                    return false;
                }
            } else {
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamControlePesagem");
                return false;
            }

            // Mocks
            $this->content = preg_replace('/(?<!->|::)\bheader\s*\(/i', '\mock_header(', $this->content);

            if (strpos($realPath, 'controle_pesagem.php') !== false) {
                // Modifica o cache para usar sys_get_temp_dir e ajusta is_dir/mkdir para ignorar em mock
                $this->content = str_replace("\$cacheDir = __DIR__ . '/cache';", "\$cacheDir = sys_get_temp_dir() . '/cache';", $this->content);
                $this->content = str_replace("if (!is_dir(\$cacheDir)) {", "if (!is_dir(\$cacheDir) && false) {", $this->content);
                // Modify filemtime call directly in content to allow cache hits since url_stat is tricky
                $this->content = str_replace("time() - filemtime(\$cacheFile)", "0", $this->content);
            }

            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStreamControlePesagem");
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
        stream_wrapper_register("file", "MockDBStreamControlePesagem");
        return $result;
    }

    public function url_stat($path, $flags) {
        $realPath = str_replace('mockdb://', '', $path);
        if (strpos($realPath, sys_get_temp_dir()) !== false) {
             stream_wrapper_restore("file");
             if (file_exists($realPath)) {
                $stat = stat($realPath);
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStreamControlePesagem");
                return $stat;
             }
             stream_wrapper_unregister("file");
             stream_wrapper_register("file", "MockDBStreamControlePesagem");
             return false;
        }
        return ['dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000, 'atime' => 0, 'mtime' => time(), 'ctime' => time(), 'blksize' => -1, 'blocks' => -1];
    }
}

// Limpa cache
$cacheDir = sys_get_temp_dir() . '/cache';
if (file_exists($cacheDir . '/co2_extintores_options.html')) {
    unlink($cacheDir . '/co2_extintores_options.html');
}

stream_wrapper_unregister("file");
stream_wrapper_register("file", "MockDBStreamControlePesagem");

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
        file_put_contents($cacheDir . '/co2_extintores_options.html', $state['cache_data']);
        stream_wrapper_unregister("file");
        stream_wrapper_register("file", "MockDBStreamControlePesagem");
    }
}

ob_start();

try {
    include __DIR__ . '/../controle_pesagem.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

$output = ob_get_clean();
echo $output;

stream_wrapper_restore("file");
?>
