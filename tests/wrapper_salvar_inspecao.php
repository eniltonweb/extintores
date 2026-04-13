<?php
class MockDBStream {
    private $position;
    private $content;
    private $fp;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);

        // bypass mock db for files in /tmp so finfo_file works reliably
        if (strpos($realPath, sys_get_temp_dir()) !== false) {
            stream_wrapper_restore("file");
            $success = false;
            if (file_exists($realPath)) {
                $this->content = file_get_contents($realPath);
                $this->fp = fopen($realPath, $mode);
                $success = true;
            } else if (strpos($mode, 'w') !== false || strpos($mode, 'a') !== false || strpos($mode, 'c') !== false) {
                // If it's a write mode, let's open it to allow creation
                $this->fp = fopen($realPath, $mode);
                $this->content = '';
                $success = ($this->fp !== false);
            }
            stream_wrapper_unregister("file");
            stream_wrapper_register("file", "MockDBStream");
            $this->position = 0;
            return $success;
        } elseif (strpos($realPath, 'config/db_conexao.php') !== false) {
            $this->content = "<?php
                class MockMySQLiResult {
                    public \$num_rows = 1;
                    public function fetch_assoc() { return ['username' => 'mock_user']; }
                }

                class MockMySQLiStmt {
                    public \$error = '';
                    private \$has_fetched = false;

                    public function bind_param(...\$args) {}
                    public function execute() { return true; }
                    public function bind_result(&\$username) {
                        \$username = 'mock_user';
                    }
                    public function fetch() {
                        if (!\$this->has_fetched) {
                            \$this->has_fetched = true;
                            return true;
                        }
                        return false;
                    }
                    public function get_result() { return new MockMySQLiResult(); }
                    public function close() {}
                }

                class MockMySQLi {
                    public \$error = '';
                    public function set_charset(\$charset) {}
                    public function query(\$query) { return new MockMySQLiResult(); }
                    public function prepare(\$query) { return new MockMySQLiStmt(); }
                    public function close() {}
                }

                \$conn = new MockMySQLi();
            ?>";
            $this->fp = false;
        } elseif (strpos($realPath, 'auditoria.php') !== false) {
            $this->content = "<?php function auditoria(\$acao, \$codigo_extintor, \$user_id, \$user_level, \$detalhes) {} ?>";
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

    public function url_stat($path, $flags) {
        $realPath = str_replace('mockdb://', '', $path);
        if (strpos($realPath, sys_get_temp_dir()) !== false) {
             stream_wrapper_restore("file");
             if (file_exists($realPath)) {
                $stat = stat($realPath);
                stream_wrapper_unregister("file");
                stream_wrapper_register("file", "MockDBStream");
                return $stat;
             }
             stream_wrapper_unregister("file");
             stream_wrapper_register("file", "MockDBStream");
        }
        return ['dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000, 'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1];
    }

    public function stream_cast($cast_as) {
        return $this->fp;
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
    if (!empty($captured_headers)) {
        echo "\n[TEST_HEADERS_SENT]\n";
    }
});

session_start();

if (isset($argv[1])) {
    $state = json_decode($argv[1], true);
    if (isset($state['session'])) $_SESSION = $state['session'];
    if (isset($state['post'])) $_POST = $state['post'];

    if (isset($state['files'])) {
        $_FILES = $state['files'];
        if (isset($_FILES['foto']['tmp_name'])) {
            if ($_FILES['foto']['tmp_name'] === 'CREATE_TEXT_FILE') {
                $tmp = sys_get_temp_dir() . '/test_' . uniqid() . '.txt';
                file_put_contents($tmp, "Just some text data");
                $_FILES['foto']['tmp_name'] = $tmp;
            } elseif ($_FILES['foto']['tmp_name'] === 'CREATE_IMAGE_FILE') {
                $tmp = sys_get_temp_dir() . '/test_' . uniqid() . '.jpg';
                $imageContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
                file_put_contents($tmp, $imageContent);
                $_FILES['foto']['tmp_name'] = $tmp;
            }
        }
    }

    if (isset($state['server'])) {
        foreach($state['server'] as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }
}

ob_start();

try {
    include __DIR__ . '/../salvar_inspecao.php';
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage();
}

$output = ob_get_clean();
echo $output;

if (isset($_FILES['foto']['tmp_name']) && file_exists($_FILES['foto']['tmp_name'])) {
    unlink($_FILES['foto']['tmp_name']);
}

stream_wrapper_restore("file");
?>
