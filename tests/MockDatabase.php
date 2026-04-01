<?php

class MockStatement {
    public $query;
    public $conn;
    public $params = [];
    public $types = '';
    public $bound_result_vars = [];
    public $executed = false;
    public $closed = false;
    public $mock_result_data = null;
    public $mock_rows = [];
    public $current_row = 0;

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
            return true;
        }
        return false;
    }

    public function fetch_assoc() {
        if ($this->current_row < count($this->mock_rows)) {
            return $this->mock_rows[$this->current_row++];
        }
        return null;
    }

    public function close() {
        $this->closed = true;
    }
}

class MockResult {
    public $rows = [];
    public $current_row = 0;
    public $num_rows = 0;

    public function __construct($rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->current_row < count($this->rows)) {
            return $this->rows[$this->current_row++];
        }
        return null;
    }
}

class MockConnection {
    public $queries = [];
    public $statements = [];
    public $mock_results = [];
    public $mock_query_results = [];

    public function prepare($query) {
        $this->queries[] = $query;
        $stmt = new MockStatement($query, $this);
        if (isset($this->mock_results[$query])) {
            $stmt->mock_result_data = $this->mock_results[$query];
        }
        if (isset($this->mock_query_results[$query])) {
            $stmt->mock_rows = $this->mock_query_results[$query];
        }
        $this->statements[] = $stmt;
        return $stmt;
    }

    public function query($query) {
        $this->queries[] = $query;
        if (isset($this->mock_query_results[$query])) {
            return new MockResult($this->mock_query_results[$query]);
        }
        return new MockResult([]);
    }

    public function real_escape_string($string) {
        return addslashes($string);
    }

    public function set_charset($charset) {
        return true;
    }

    public function close() {
        return true;
    }
}
