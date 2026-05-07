<?php
/**
 * Wrapper for testing sair.php
 */

class MockSairStream {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mock://', '', $path);

        if (!file_exists($realPath)) {
            return false;
        }

        $content = file_get_contents($realPath);

        // Intercept session functions
        $content = preg_replace('/(?<!->|::)\bsession_start\s*\(\s*\)/i', 'echo "[MOCK_SESSION_START]\n"', $content);
        $content = preg_replace('/(?<!->|::)\bsession_destroy\s*\(\s*\)/i', 'echo "[MOCK_SESSION_DESTROY]\n"', $content);

        // Intercept header
        $content = preg_replace('/(?<!->|::)\bheader\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', 'echo "[MOCK_HEADER] $1\n"', $content);

        // Intercept setcookie - Replace with a call to a mock function
        $content = preg_replace('/(?<!->|::)\bsetcookie\s*\(/i', 'mock_setcookie(', $content);

        // Debug session before and after clear
        $content = str_replace('$_SESSION = array();', 'echo "[SESSION_BEFORE_CLEAR] " . count($_SESSION) . "\n"; $_SESSION = array(); echo "[SESSION_AFTER_CLEAR] " . count($_SESSION) . "\n";', $content);

        // Ensure ini_get("session.use_cookies") returns true for testing that branch
        $content = str_replace('ini_get("session.use_cookies")', 'true', $content);

        $this->content = $content;
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

    public function url_stat($path, $flags) {
        return [
            'dev' => 0, 'ino' => 0, 'mode' => 0100644, 'nlink' => 1,
            'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 1000,
            'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => -1, 'blocks' => -1
        ];
    }

    public function stream_set_option($option, $arg1, $arg2) {
        return true;
    }
}

if (!function_exists('mock_setcookie')) {
    function mock_setcookie(...$args) {
        echo "[MOCK_SETCOOKIE]\n";
        return true;
    }
}

stream_wrapper_register("mock", "MockSairStream");

// Mock SESSION
$_SESSION = ['user_id' => 1, 'user_name' => 'test_user'];

// Execute the target script
try {
    $targetPath = realpath(__DIR__ . '/../sair.php');
    include 'mock://' . $targetPath;
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "IN FILE: " . $e->getFile() . " ON LINE " . $e->getLine() . "\n";
}
?>
