<?php
require_once __DIR__ . '/tests/MockDatabase.php';

class MockConnectionWithMulti extends MockConnection {
    public $queries_multi = [];

    public function multi_query($query) {
        $this->queries_multi[] = $query;
        return true; // Simulate success
    }
}
$conn = new MockConnectionWithMulti();
$conn->multi_query("SELECT 1; SELECT 2");
var_dump($conn->queries_multi);
