<?php

require_once __DIR__ . '/MockDatabase.php';
require_once __DIR__ . '/../benchmark.php';

class BenchmarkTest extends MiniTestCase {

    public function testBenchmarkBaseline() {
        global $conn;
        $conn = new MockConnection();

        $start = microtime(true);
        $time = benchmark_baseline(2);

        $this->assertTrue(is_float($time), "Expected float return value from benchmark_baseline");
        $this->assertTrue($time > 0, "Expected benchmark time to be greater than 0");
    }

    public function testBenchmarkOptimized() {
        global $conn;
        $conn = new MockConnection();

        $start = microtime(true);
        $time = benchmark_optimized(2);

        $this->assertTrue(is_float($time), "Expected float return value from benchmark_optimized");
        $this->assertTrue($time > 0, "Expected benchmark time to be greater than 0");
    }
}
