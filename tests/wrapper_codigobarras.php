<?php

class MockDBStream {
    private $position;
    private $content;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $realPath = str_replace('mockdb://', '', $path);
        if (!file_exists($realPath)) {
            return false;
        }

        $this->content = file_get_contents($realPath);

        // Remove a inclusão real do banco de dados e auditoria, pois as definiremos manualmente para o teste
        $this->content = str_replace("require_once __DIR__ . '/config/db_conexao.php';", "/* db mocked */", $this->content);
        $this->content = str_replace("include 'auditoria.php';", "/* auditoria mocked */", $this->content);

        // Intercepta e converte qualquer uso de header para um echo amigável com testes
        $this->content = preg_replace('/(?<!->|::)\bheader\s*\(\s*([^)]+)\s*\)/i', 'echo "[MOCK_HEADER] " . $1;', $this->content);

        // Intercepta chamadas exit() ou die() para que os testes capturem e lidem com a saída sem matar o PHP unit
        $this->content = preg_replace('/\b(exit|die)\s*\(/i', 'echo "[MOCK_EXIT]"; return;', $this->content);

        // Remove includes que causam problemas no context de teste
        $this->content = preg_replace('/include\s+[\'"]\.\.\/templates\/header.*?\.php[\'"];/i', '/* header mocked */', $this->content);

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
        $realPath = str_replace('mockdb://', '', $path);
        return file_exists($realPath) ? stat($realPath) : false;
    }

    public function stream_set_option($option, $arg1, $arg2) {
        return false;
    }
}

if (!in_array('mockdb', stream_get_wrappers())) {
    stream_wrapper_register('mockdb', 'MockDBStream');
}

ob_start();
register_shutdown_function(function() {
    echo ob_get_clean();
});

// Mock dependencies
global $conn;
if (!isset($conn)) {
    if (isset($GLOBALS['mock_prepare_result']) && $GLOBALS['mock_prepare_result'] === false) {
        $conn = new class extends MockConnection {
            public $error = 'Erro forçado mock_prepare_result';
            public function prepare($query) {
                $stmt = new class {
                    public $error = 'Erro forçado mock_prepare_result';
                    public function bind_param() { return false; }
                    public function execute() { return false; }
                    public function get_result() { return false; }
                };
                return $stmt;
            }
        };
    } else {
        $conn = new MockConnection();
        if (isset($GLOBALS['mock_query_results'])) {
            $conn->mock_query_results = $GLOBALS['mock_query_results'];
        }
    }
}

if (!function_exists('registrar_auditoria')) {
    function registrar_auditoria() {}
}

if (!function_exists('auditoria')) {
    function auditoria() {}
}

// Injetar variáveis ausentes que geram Notice no código original
global $liberado_inspecao, $liberado_manutencao;
$liberado_inspecao = $GLOBALS['liberado_inspecao'] ?? false;
$liberado_manutencao = $GLOBALS['liberado_manutencao'] ?? false;

try {
    include 'mockdb://' . realpath(__DIR__ . '/../codigobarras.php');
} catch (Exception $e) {
    // Catch exceptions so they don't break the test wrapper abruptly
}
