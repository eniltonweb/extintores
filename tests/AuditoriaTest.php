<?php

require_once __DIR__ . '/../auditoria.php';

class MockStatement {
    public $query;
    public $conn;
    public $params = [];
    public $types = '';
    public $bound_result_vars = [];
    public $executed = false;
    public $closed = false;
    public $mock_result_data = null;

    public function __construct($query, $conn) {
        $this->query = $query;
        $this->conn = $conn;
    }

    public function bind_param($types, &...$vars) {
        $this->types = $types;
        $this->params = [];
        foreach ($vars as $var) {
            $this->params[] = $var;
        }
        return true;
    }

    public function execute() {
        $this->executed = true;
        return true;
    }

    public function bind_result(&...$vars) {
        $this->bound_result_vars = [];
        foreach ($vars as &$var) {
            $this->bound_result_vars[] = &$var;
        }
        return true;
    }

    public function fetch() {
        if (!empty($this->bound_result_vars) && $this->mock_result_data !== null) {
            $this->bound_result_vars[0] = $this->mock_result_data;
        }
    }

    public function close() {
        $this->closed = true;
    }
}

class MockConnection {
    public $queries = [];
    public $statements = [];
    public $mock_results = [];

    public function prepare($query) {
        $this->queries[] = $query;
        $stmt = new MockStatement($query, $this);
        if (isset($this->mock_results[$query])) {
            $stmt->mock_result_data = $this->mock_results[$query];
        }
        $this->statements[] = $stmt;
        return $stmt;
    }
}

class AuditoriaTest extends MiniTestCase {

    private $original_conn;

    public function setUp() {
        global $conn;
        if (isset($conn)) {
            $this->original_conn = $conn;
        }
    }

    public function tearDown() {
        global $conn;
        if (isset($this->original_conn)) {
            $conn = $this->original_conn;
        } else {
            unset($conn);
        }
    }

    public function testAuditoriaWithCodigoExtintor() {
        global $conn;

        $conn = new MockConnection();
        $conn->mock_results["SELECT id FROM bd_extintores WHERE codigo = ?"] = 42;

        $acao = "Teste de auditoria";
        $codigo_extintor = "EXT-123";
        $user_id = 1;
        $user_level = "admin";
        $detalhes = "Detalhes do teste";

        auditoria($acao, $codigo_extintor, $user_id, $user_level, $detalhes);

        $this->assertEquals(2, count($conn->statements), "Deveriam ter sido criados 2 statements (1 para select, 1 para insert)");

        $select_stmt = $conn->statements[0];
        $this->assertEquals("SELECT id FROM bd_extintores WHERE codigo = ?", $select_stmt->query);
        $this->assertEquals('s', $select_stmt->types);
        $this->assertEquals([$codigo_extintor], $select_stmt->params);
        $this->assertTrue($select_stmt->executed);
        $this->assertTrue($select_stmt->closed);

        $insert_stmt = $conn->statements[1];
        $expected_query = "INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, data_hora, detalhes) \n            VALUES (?, ?, ?, ?, NOW(), ?)";
        // Let's normalize spaces for comparison to avoid issues with newlines and indentation
        $normalized_expected = preg_replace('/\s+/', ' ', $expected_query);
        $normalized_actual = preg_replace('/\s+/', ' ', $insert_stmt->query);
        $this->assertEquals($normalized_expected, $normalized_actual);
        $this->assertEquals('issss', $insert_stmt->types);
        $this->assertEquals([$user_id, $user_level, $acao, 42, $detalhes], $insert_stmt->params);
        $this->assertTrue($insert_stmt->executed);
        $this->assertTrue($insert_stmt->closed);
    }

    public function testAuditoriaWithoutCodigoExtintor() {
        global $conn;

        $conn = new MockConnection();

        $acao = "Teste de auditoria sem extintor";
        $codigo_extintor = null;
        $user_id = 2;
        $user_level = "operador";
        $detalhes = "Outros detalhes";

        auditoria($acao, $codigo_extintor, $user_id, $user_level, $detalhes);

        $this->assertEquals(1, count($conn->statements), "Deveria ter sido criado 1 statement (apenas insert)");

        $insert_stmt = $conn->statements[0];
        $expected_query = "INSERT INTO auditoria_logs (user_id, user_level, action, extintor_id, data_hora, detalhes) \n            VALUES (?, ?, ?, ?, NOW(), ?)";
        // Let's normalize spaces for comparison to avoid issues with newlines and indentation
        $normalized_expected = preg_replace('/\s+/', ' ', $expected_query);
        $normalized_actual = preg_replace('/\s+/', ' ', $insert_stmt->query);
        $this->assertEquals($normalized_expected, $normalized_actual);
        $this->assertEquals('issss', $insert_stmt->types);
        $this->assertEquals([$user_id, $user_level, $acao, null, $detalhes], $insert_stmt->params);
        $this->assertTrue($insert_stmt->executed);
        $this->assertTrue($insert_stmt->closed);
    }
}
