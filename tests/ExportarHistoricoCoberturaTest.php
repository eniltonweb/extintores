<?php

require_once __DIR__ . '/runner.php';

class ExportarHistoricoCoberturaTest extends MiniTestCase {

    private function runWrapper($state) {
        $wrapper_script = __DIR__ . '/wrapper_exportar_historico_cobertura.php';
        $json_data = escapeshellarg(json_encode($state));

        // Use PHP_BINARY if available for better portability
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $cmd = "{$php} {$wrapper_script} {$json_data} 2>&1";

        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    public function testAccessDeniedForNonAdmin() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'bombeiro' // Not admin
            ]
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(strpos($result['output'], '[REDIRECT] index.php') !== false, "Expected redirect to index.php for non-admin user. Got: " . $result['output']);
    }

    public function testExportWithResults() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin'
            ],
            'db_results' => [
                'SELECT' => [
                    [
                        'codigo' => 'EXT-COB-01',
                        'Predio' => 'Prédio C',
                        'Local_Exato' => 'Corredor',
                        'usuario_nome' => 'Admin',
                        'tip_extintor' => 'AP',
                        'carga' => '10L',
                        'manutencao_n2' => '2023-10-01',
                        'proxima_manutencao_n2' => '2024-10-01',
                        'dias_para_expirar_n2' => 300,
                        'cobertura' => 1
                    ]
                ]
            ]
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(strpos($result['output'], '<title>Relatório de Coberturas</title>') !== false, "Expected title not found in output");
        $this->assertTrue(strpos($result['output'], 'EXT-COB-01') !== false, "Expected codigo not found in output");
        $this->assertTrue(strpos($result['output'], 'Prédio C') !== false, "Expected predio not found in output");
        $this->assertTrue(strpos($result['output'], 'Corredor') !== false, "Expected local_exato not found in output");
        $this->assertTrue(strpos($result['output'], 'Admin') !== false, "Expected usuario_nome not found in output");
        $this->assertTrue(strpos($result['output'], '10L') !== false, "Expected carga not found in output");
        $this->assertTrue(strpos($result['output'], '2023-10-01') !== false, "Expected data not found in output");
    }

    public function testExportNoResults() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin'
            ],
            'db_results' => [
                'SELECT' => []
            ]
        ];

        $result = $this->runWrapper($state);

        // Deve renderizar a tabela, mas sem linhas com dados dentro do tbody
        $this->assertTrue(strpos($result['output'], '<title>Relatório de Coberturas</title>') !== false, "Expected title not found in output");
        $this->assertTrue(strpos($result['output'], '<th>Código do Extintor</th>') !== false, "Expected table headers not found");
        $this->assertTrue(strpos($result['output'], 'EXT-COB-01') === false, "Not expected any extintor record");
    }
}
