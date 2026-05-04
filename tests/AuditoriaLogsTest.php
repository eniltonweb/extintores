<?php
require_once __DIR__ . '/runner.php';
require_once __DIR__ . '/MockDatabase.php';

// Carregar silenciosamente para definir a função sem executar lógica global
try {
    @include_once __DIR__ . '/../auditoria_logs.php';
} catch (Exception $e) {}

class AuditoriaLogsTest extends MiniTestCase {
    public function testApagarLogsVazio() {
        $conn = new MockConnection();
        $message = apagar_logs_selecionados($conn, []);
        $this->assertEquals("Nenhum log foi selecionado para exclusão.", $message);
        $this->assertEquals(0, count($conn->statements));
    }

    public function testApagarLogsNaoArray() {
        $conn = new MockConnection();
        $message = apagar_logs_selecionados($conn, "1,2,3");
        $this->assertEquals("Nenhum log foi selecionado para exclusão.", $message);
        $this->assertEquals(0, count($conn->statements));
    }

    public function testApagarApenasLogExclusaoGeral() {
        $conn = new MockConnection();
        $conn->mock_query_results["SELECT id FROM auditoria_logs WHERE detalhes = 'Todos os logs de auditoria foram apagados'"] = [["id" => 99]];

        // Simular a seleção apenas do log de exclusão, que deve ser filtrado
        $message = apagar_logs_selecionados($conn, [99]);

        $this->assertEquals("Nenhum log foi selecionado para exclusão.", $message);
        $this->assertEquals(0, count($conn->statements)); $this->assertEquals(1, count($conn->queries));
    }

    public function testErroPrepareExclusao() {
        $conn = new class extends MockConnection {
            public function prepare($q) {
                $stmt = new MockStatement($q, $this);
                $this->statements[] = $stmt;
                if (strpos($q, 'DELETE FROM auditoria_logs') !== false) {
                    return false;
                }
                return $stmt;
            }
        };
        $conn->mock_query_results["SELECT id FROM auditoria_logs WHERE detalhes = 'Todos os logs de auditoria foram apagados'"] = [["id" => 99]];

        $message = apagar_logs_selecionados($conn, [1, 2]);
        $this->assertEquals("Erro ao preparar a exclusão.", $message);
    }

    public function testSucessoExclusao() {
        $conn = new MockConnection();
        $conn->mock_query_results["SELECT id FROM auditoria_logs WHERE detalhes = 'Todos os logs de auditoria foram apagados'"] = [["id" => 99]];

        $message = apagar_logs_selecionados($conn, [1, 2, 99]); // 99 deve ser filtrado

        $this->assertEquals("Logs selecionados foram apagados.", $message);
        $this->assertEquals(1, count($conn->statements)); $this->assertEquals(2, count($conn->queries));

        $delete_stmt = $conn->statements[0];
        $this->assertEquals("DELETE FROM auditoria_logs WHERE id IN (?,?)", $delete_stmt->query);
        $this->assertEquals('ii', $delete_stmt->types);
        $this->assertEquals([1, 2], $delete_stmt->params);
        $this->assertTrue($delete_stmt->executed);
        $this->assertTrue($delete_stmt->closed);
    }
}