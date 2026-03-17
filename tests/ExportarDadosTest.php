<?php
// Mock classes for testing
class MockStmtExportarDados {
    public $sql;
    public $bind_types;
    public $bind_vars;
    public $execute_called = false;
    public $close_called = false;

    public function __construct($sql) {
        $this->sql = $sql;
    }

    public function bind_param($types, ...$vars) {
        $this->bind_types = $types;
        $this->bind_vars = $vars;
        return true;
    }

    public function execute() {
        $this->execute_called = true;
        return true;
    }

    public function close() {
        $this->close_called = true;
        return true;
    }
}

class MockConnExportarDados {
    public $last_stmt = null;

    public function prepare($sql) {
        $this->last_stmt = new MockStmtExportarDados($sql);
        return $this->last_stmt;
    }
}

// Override $_SERVER variables so exportar_dados.php does not execute its global scope logic
$_SERVER['SCRIPT_FILENAME'] = 'phpunit.php';

require_once __DIR__ . '/../exportar_dados.php';

class ExportarDadosTest extends MiniTestCase {
    public function testRegistrarAuditoriaExecutesCorrectSqlAndBindsParams() {
        $conn = new MockConnExportarDados();
        $user_id = 99;
        $action = 'Test Action';
        $details = 'Test Details';

        // Call the function
        registrar_auditoria($conn, $user_id, $action, $details);

        // Verify that prepare was called with the correct SQL
        $expected_sql = "INSERT INTO auditoria_logs (user_id, action, detalhes) VALUES (?, ?, ?)";
        $this->assertTrue($conn->last_stmt !== null, "prepare() should be called and return a statement");
        $this->assertEquals($expected_sql, $conn->last_stmt->sql, "prepare() should be called with correct SQL");

        // Verify bind_param
        $this->assertEquals('iss', $conn->last_stmt->bind_types, "bind_param should use 'iss' types");
        $this->assertEquals(3, count($conn->last_stmt->bind_vars), "bind_param should receive 3 variables");
        $this->assertEquals($user_id, $conn->last_stmt->bind_vars[0], "First variable should be user_id");
        $this->assertEquals($action, $conn->last_stmt->bind_vars[1], "Second variable should be action");
        $this->assertEquals($details, $conn->last_stmt->bind_vars[2], "Third variable should be details");

        // Verify execution and close
        $this->assertTrue($conn->last_stmt->execute_called, "execute() should be called on the statement");
        $this->assertTrue($conn->last_stmt->close_called, "close() should be called on the statement");
    }
}
