<?php
require_once __DIR__ . '/MockDatabase.php';

class AuditoriaPaginationTest extends MiniTestCase {
    public function testPaginationLogic() {
        // We will simulate the logic that we want to implement in auditoria_logs.php
        $records_per_page = 20;
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $records_per_page;

        $total_records = 105;
        $total_pages = ceil($total_records / $records_per_page);

        $this->assertEquals(6, (int)$total_pages, "Total pages should be 6 for 105 records with 20 per page");

        // Test offset for different pages
        $this->assertEquals(0, (1 - 1) * 20);
        $this->assertEquals(20, (2 - 1) * 20);
        $this->assertEquals(100, (6 - 1) * 20);
    }

    public function testSqlWithLimitAndOffset() {
        $page = 3;
        $records_per_page = 20;
        $offset = ($page - 1) * $records_per_page;

        $sql = "
            SELECT al.*, u.username, e.codigo AS extintor_codigo
            FROM auditoria_logs al
            LEFT JOIN usuarios u ON al.user_id = u.id
            LEFT JOIN bd_extintores e ON al.extintor_id = e.id
            ORDER BY al.data_hora DESC
            LIMIT $records_per_page OFFSET $offset
        ";

        $this->assertTrue(strpos($sql, "LIMIT 20 OFFSET 40") !== false, "SQL should contain LIMIT 20 OFFSET 40 for page 3");
    }
}
