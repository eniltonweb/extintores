<?php
require_once __DIR__ . '/runner.php';
require_once __DIR__ . '/MockDatabase.php';

// Carregar silenciosamente para definir a função sem executar lógica global
try {
    @include_once __DIR__ . '/../auditoria_logs.php';
} catch (Exception $e) {}

class AuditoriaLogsTest extends MiniTestCase {
    public function testRedirectsWhenNoSession() {
        $state = ['session' => []];
        $output = shell_exec(PHP_BINARY . ' ' . __DIR__ . '/wrapper_auditoria_logs.php ' . escapeshellarg(json_encode($state)));
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Output does not contain header marker");
    }

    public function testRedirectsWhenNotAdmin() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'fornecedor',
                'csrf_token' => 'test_token'
            ]
        ];
        $output = shell_exec(PHP_BINARY . ' ' . __DIR__ . '/wrapper_auditoria_logs.php ' . escapeshellarg(json_encode($state)));
        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, "Output does not contain header marker");
    }

    public function testCsrfValidationFails() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'invalid_token',
                'delete_all' => '1'
            ]
        ];
        $output = shell_exec(PHP_BINARY . ' ' . __DIR__ . '/wrapper_auditoria_logs.php ' . escapeshellarg(json_encode($state)));
        $this->assertTrue(strpos($output, 'Erro de validação de segurança CSRF.') !== false, "Output does not contain CSRF error");
    }

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

    public function testDeleteAllSuccess() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'delete_all' => '1'
            ]
        ];
        $output = shell_exec(PHP_BINARY . ' ' . __DIR__ . '/wrapper_auditoria_logs.php ' . escapeshellarg(json_encode($state)));
        $this->assertTrue(strpos($output, 'Todos os logs foram apagados.') !== false, "Output does not contain success message");
        $this->assertTrue(strpos($output, '[MOCK_AUDITORIA] Apagar Todos os Logs') !== false, "Output does not contain audit log");
    }

    public function testDeleteAllDatabaseError() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'delete_all' => '1'
            ],
            'db_query_error' => 'Simulated DB query error'
        ];
        $output = shell_exec(PHP_BINARY . ' ' . __DIR__ . '/wrapper_auditoria_logs.php ' . escapeshellarg(json_encode($state)));
        $this->assertTrue(strpos($output, 'Erro ao apagar todos os logs: Simulated DB query error') !== false, "Output does not contain error message");
    }

    public function testDeleteSelectedSuccess() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin',
                'csrf_token' => 'valid_token'
            ],
            'post' => [
                'csrf_token' => 'valid_token',
                'delete_selected' => '1',
                'logs' => [1, 2, 3]
            ]
        ];
        $output = shell_exec(PHP_BINARY . ' ' . __DIR__ . '/wrapper_auditoria_logs.php ' . escapeshellarg(json_encode($state)));
        $this->assertTrue(strpos($output, 'Logs selecionados foram apagados.') !== false, "Output does not contain success message for selected logs");
    }
}