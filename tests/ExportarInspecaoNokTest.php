<?php
// Mock classes for testing
class MockStmtExportarInspecaoNok {
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

    public $execute_returns_false = false;

    public function execute() {
        $this->execute_called = true;
        if ($this->execute_returns_false) {
            return false;
        }
        return true;
    }

    public function close() {
        $this->close_called = true;
        return true;
    }
}

class MockConnExportarInspecaoNok {
    public $last_stmt = null;
    public $prepare_returns_false = false;
    public $execute_returns_false = false;
    public $error = '';

    public function prepare($sql) {
        if ($this->prepare_returns_false) {
            return false;
        }
        $this->last_stmt = new MockStmtExportarInspecaoNok($sql);
        if ($this->execute_returns_false) {
            $this->last_stmt->execute_returns_false = true;
        }
        return $this->last_stmt;
    }
}

// Override $_SERVER variables so exportar_inspecao_nok.php does not execute its global scope logic
$_SERVER['SCRIPT_FILENAME'] = 'phpunit.php';

require_once __DIR__ . '/MockDatabase.php';
require_once __DIR__ . '/../exportar_inspecao_nok.php';
// If it was already defined (e.g. by ExportarDadosTest), we can still test it,
// but we must be sure we are testing the right one.
// Since they are identical, it's fine.

class ExportarInspecaoNokTest extends MiniTestCase {
    public function testRegistrarAuditoriaExecutesCorrectSqlAndBindsParams() {
        $conn = new MockConnection();
        $user_id = 99;
        $action = 'Test Action';
        $details = 'Test Details';

        // Call the function
        registrar_auditoria($conn, $user_id, $action, $details);

        // Verify that prepare was called with the correct SQL
        $expected_sql = "INSERT INTO auditoria_logs (user_id, action, detalhes) VALUES (?, ?, ?)";
        $this->assertTrue(in_array($expected_sql, $conn->queries), "prepare() should be called with correct SQL");
        $stmt = $conn->statements[0];

        // Verify bind_param
        $this->assertEquals('iss', $stmt->types, "bind_param should use 'iss' types");
        $this->assertEquals(3, count($stmt->params), "bind_param should receive 3 variables");
        $this->assertEquals($user_id, $stmt->params[0], "First variable should be user_id");
        $this->assertEquals($action, $stmt->params[1], "Second variable should be action");
        $this->assertEquals($details, $stmt->params[2], "Third variable should be details");

        // Verify execution and close
        $this->assertTrue($stmt->executed, "execute() should be called on the statement");
        $this->assertTrue($stmt->closed, "close() should be called on the statement");
    }

    public function testRegistrarAuditoriaHandlesPrepareFailureGracefully() {
        // Create a mock connection that returns false on prepare
        $conn = new class extends MockConnection {
            public $error = "Mock prepare error";
            public function prepare($query) {
                return false;
            }
        };

        $user_id = 101;
        $action = 'Fail Action';
        $details = 'Fail Details';

        // Call the function; it should log an error and not crash (no fatal error)
        registrar_auditoria($conn, $user_id, $action, $details);

        // If it reaches here without a fatal error, the test passes.
        $this->assertTrue(true, "registrar_auditoria should handle prepare failure gracefully");
    }

    public function testRegistrarAuditoriaHandlesExecuteFailure() {
        // Create a mock connection where execute returns false
        $conn = new MockConnection();
        $stmt_mock = new class("mock query", $conn) extends MockStatement {
            public function execute() {
                return false;
            }
        };

        $conn = new class($stmt_mock) extends MockConnection {
            public $stmt_mock;
            public function __construct($stmt_mock) {
                $this->stmt_mock = $stmt_mock;
            }
            public function prepare($query) {
                $this->queries[] = $query;
                $this->statements[] = $this->stmt_mock;
                return $this->stmt_mock;
            }
        };

        $user_id = 102;
        $action = 'Exec Fail Action';
        $details = 'Exec Fail Details';

        // Call the function
        registrar_auditoria($conn, $user_id, $action, $details);

        $stmt = $conn->statements[0];

        $this->assertEquals('iss', $stmt->types, "bind_param should use 'iss' types");
        $this->assertEquals(3, count($stmt->params), "bind_param should receive 3 variables");

        // Execute failure should be handled or at least not crash
        $this->assertTrue($stmt->closed, "close() should still be called even if execute fails, or at least no fatal error");
    }

    public function testRegistrarAuditoriaFailsWhenPrepareReturnsFalse() {
        $conn = new MockConnExportarInspecaoNok();
        $conn->prepare_returns_false = true;

        // With the fix, registrar_auditoria should handle the false statement gracefully
        // and not throw an error.
        $exceptionThrown = false;
        try {
            registrar_auditoria($conn, 99, 'Test Action', 'Test Details');
        } catch (\Error $e) {
            $exceptionThrown = true;
        }

        $this->assertEquals(false, $exceptionThrown, "No Error should be thrown when prepare returns false as it is now handled gracefully");
    }

    public function testRegistrarAuditoriaWhenExecuteReturnsFalse() {
        $conn = new MockConnExportarInspecaoNok();
        $conn->execute_returns_false = true;

        // Function does not currently check execute() return value, so it should just proceed without throwing.
        $exceptionThrown = false;
        try {
            registrar_auditoria($conn, 99, 'Test Action', 'Test Details'); $exceptionThrown = false;
        } catch (\Exception $e) {
            $exceptionThrown = true;
        } catch (\Error $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown === false, "No exception should be thrown if execute() returns false");

        // Still verify it was called
        $this->assertTrue($conn->last_stmt->execute_called, "execute() should still be called");
        $this->assertTrue($conn->last_stmt->close_called, "close() should still be called even if execute returned false");
    }
}
