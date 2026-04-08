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

require_once __DIR__ . '/../exportar_inspecao_nok.php';
// If it was already defined (e.g. by ExportarDadosTest), we can still test it,
// but we must be sure we are testing the right one.
// Since they are identical, it's fine.

class ExportarInspecaoNokTest extends MiniTestCase {
    public function testRegistrarAuditoriaExecutesCorrectSqlAndBindsParams() {
        $conn = new MockConnExportarInspecaoNok();
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

    public function testRegistrarAuditoriaFailsWhenPrepareReturnsFalse() {
        $conn = new MockConnExportarInspecaoNok();
        $conn->prepare_returns_false = true;

        $exceptionThrown = false;
        try {
            registrar_auditoria($conn, 99, 'Test Action', 'Test Details');
        } catch (\Error $e) {
            $exceptionThrown = true;
            // The exact error message could be "Call to a member function bind_param() on false"
            // or "...on bool" depending on the PHP version, so we check for both.
            $this->assertTrue(
                strpos($e->getMessage(), 'bind_param() on false') !== false ||
                strpos($e->getMessage(), 'bind_param() on bool') !== false,
                "Expected error message about calling bind_param on false/bool"
            );
        }

        $this->assertTrue($exceptionThrown, "An Error should be thrown when prepare returns false");
    }

    public function testRegistrarAuditoriaWhenExecuteReturnsFalse() {
        $conn = new MockConnExportarInspecaoNok();
        $conn->execute_returns_false = true;

        // Function does not currently check execute() return value, so it should just proceed without throwing.
        $exceptionThrown = false;
        try {
            registrar_auditoria($conn, 99, 'Test Action', 'Test Details');
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
