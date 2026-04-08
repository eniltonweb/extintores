<?php
require_once __DIR__ . '/runner.php';
require_once __DIR__ . '/MockDatabase.php';
require_once __DIR__ . '/../atualizar_dias_expiracao.php';

class AtualizarDiasExpiracaoTest extends MiniTestCase {
    public function testAtualizarDiasExpiracaoSuccess() {
        $mockConn = new MockConnection();
        $mockConn->mock_query_results = [
            "UPDATE bd_extintores
            SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE())
            WHERE proxima_manutencao_n2 IS NOT NULL" => [true]
        ];

        $result = atualizar_dias_expiracao($mockConn);

        $this->assertTrue($result, "Function should return true on success");
        $this->assertTrue(count($mockConn->queries) === 1, "Should have executed 1 query");

        $expectedSql = "UPDATE bd_extintores
            SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE())
            WHERE proxima_manutencao_n2 IS NOT NULL";

        $this->assertEquals(
            preg_replace('/\s+/', ' ', trim($expectedSql)),
            preg_replace('/\s+/', ' ', trim($mockConn->queries[0])),
            "The executed SQL should match the expected SQL"
        );
    }

    public function testAtualizarDiasExpiracaoQueryFailure() {
        // We need a way to make MockConnection return FALSE
        // MockConnection::query returns new MockResult, which is truthy
        // Let's create a specialized mock for failure
        $mockConn = new class {
            public $queries = [];
            public $error = "Mock SQL Error";
            public function query($sql) {
                $this->queries[] = $sql;
                return false;
            }
        };

        $result = atualizar_dias_expiracao($mockConn);

        $this->assertTrue($result === false, "Function should return false on query failure");
    }

    public function testAtualizarDiasExpiracaoNoConnection() {
        $result = atualizar_dias_expiracao(null);
        $this->assertTrue($result === false, "Function should return false when connection is null");
    }
}
