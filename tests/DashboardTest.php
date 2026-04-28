<?php

class DashboardTest extends MiniTestCase {

    private function runWrapper($state) {
        $wrapper_script = __DIR__ . '/wrapper_dashboard.php';
        $json_data = escapeshellarg(json_encode($state));

        $cmd = "php {$wrapper_script} {$json_data} 2>&1";

        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    private function getCacheDir() {
        return sys_get_temp_dir() . '/cache';
    }

    private function cleanCache() {
        $cacheDir = $this->getCacheDir();
        $cacheFile = $cacheDir . '/dashboard_data.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function tearDown() {
        $this->cleanCache();
    }

    public function setUp() {
        $this->cleanCache();
    }

    public function testRedirectIfUnauthenticated() {
        $state = [
            'session' => [] // No user_id
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '[TEST_HEADERS_SENT]') !== false,
            "Expected redirect header for unauthenticated user."
        );
    }

    public function testDashboardRenderedWhenAuthenticated() {
        $state = [
            'session' => ['user_id' => 1]
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(
            strpos($result['output'], '<canvas id="manutencaoChart"></canvas>') !== false,
            "Expected manutencaoChart canvas to be rendered."
        );
        $this->assertTrue(
            strpos($result['output'], '<canvas id="proximasChart"></canvas>') !== false,
            "Expected proximasChart canvas to be rendered."
        );
        $this->assertTrue(
            strpos($result['output'], '<canvas id="extintoresChart"></canvas>') !== false,
            "Expected extintoresChart canvas to be rendered."
        );
    }

    public function testDashboardCacheHit() {
        $cacheData = [
            'manutencaoChart' => 'cacheHit', // just random key to test
            'manutencoes' => [['tipo_manutencao' => 'CACHE_MANUT', 'total' => 999]],
            'proximas_manutencoes' => [['proxima_manutencao_n2' => '2099-01-01', 'total' => 888]],
            'extintores' => [['tip_extintor' => 'CACHE_EXT', 'total' => 777]]
        ];

        $state = [
            'session' => ['user_id' => 1],
            'cache_data' => $cacheData
        ];

        $result = $this->runWrapper($state);

        // Check if cached data is present in the output
        $this->assertTrue(
            strpos($result['output'], 'CACHE_MANUT') !== false,
            "Expected cached manutencoes data to be rendered."
        );
        $this->assertTrue(
            strpos($result['output'], '2099-01-01') !== false,
            "Expected cached proximas data to be rendered."
        );
        $this->assertTrue(
            strpos($result['output'], 'CACHE_EXT') !== false,
            "Expected cached extintores data to be rendered."
        );

        // Assert mock data from wrapper is NOT present
        $this->assertTrue(
            strpos($result['output'], 'Preventiva') === false,
            "Did not expect mock db data when cache hits."
        );
    }

    public function testDashboardCacheMiss() {
        $state = [
            'session' => ['user_id' => 1]
        ];

        // Ensure cache is empty
        $this->cleanCache();

        $result = $this->runWrapper($state);

        // Check if mock db data is present in the output
        $this->assertTrue(
            strpos($result['output'], 'Preventiva') !== false,
            "Expected mock db manutencoes data to be rendered."
        );
        $this->assertTrue(
            strpos($result['output'], '2025-01-01') !== false,
            "Expected mock db proximas data to be rendered."
        );
        $this->assertTrue(
            strpos($result['output'], 'AP') !== false,
            "Expected mock db extintores data to be rendered."
        );

        // Verify cache file was created
        $cacheDir = $this->getCacheDir();
        $cacheFile = $cacheDir . '/dashboard_data.json';
        $this->assertTrue(
            file_exists($cacheFile),
            "Expected cache file to be created after a miss."
        );
    }
}
?>