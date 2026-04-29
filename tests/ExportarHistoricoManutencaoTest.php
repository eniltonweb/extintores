<?php

require_once __DIR__ . '/runner.php';

class ExportarHistoricoManutencaoTest extends MiniTestCase {

    private function runWrapper($state) {
        $wrapper_script = __DIR__ . '/wrapper_exportar_historico_manutencao.php';
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

    public function testExportAllWithResults() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin'
            ],
            'get' => [
                'cobertura' => 'all'
            ],
            'db_results' => [
                'SELECT' => [
                    [
                        'extintor_codigo' => 'EXT001',
                        'local_exato' => 'Hall',
                        'predio' => 'Prédio A',
                        'cobertura' => 1,
                        'usuario_nome' => 'Admin',
                        'data_manutencao' => '2023-10-27'
                    ]
                ]
            ]
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(strpos($result['output'], '<title>Histórico de Manutenções de Extintores</title>') !== false, "Expected title not found in output");
        $this->assertTrue(strpos($result['output'], 'EXT001') !== false, "Expected extintor_codigo not found in output");
        $this->assertTrue(strpos($result['output'], 'Prédio A') !== false, "Expected predio not found in output");
        $this->assertTrue(strpos($result['output'], 'Hall') !== false, "Expected local_exato not found in output");
        $this->assertTrue(strpos($result['output'], 'Sim') !== false, "Expected cobertura 'Sim' not found in output");
        $this->assertTrue(strpos($result['output'], '27/10/2023') !== false, "Expected formatted date not found in output");
    }

    public function testExportCoberturaSimWithResults() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin'
            ],
            'get' => [
                'cobertura' => 'sim'
            ],
            'db_results' => [
                'SELECT' => [
                    [
                        'extintor_codigo' => 'EXT002',
                        'local_exato' => 'Copa',
                        'predio' => 'Prédio B',
                        'cobertura' => 1,
                        'usuario_nome' => 'Admin',
                        'data_manutencao' => '2023-10-28'
                    ]
                ]
            ]
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(strpos($result['output'], 'EXT002') !== false, "Expected extintor_codigo not found in output for cobertura=sim");
        $this->assertTrue(strpos($result['output'], 'Prédio B') !== false, "Expected predio not found in output for cobertura=sim");
    }

    public function testExportNoResults() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin'
            ],
            'get' => [
                'cobertura' => 'all'
            ],
            'db_results' => [
                'SELECT' => []
            ]
        ];

        $result = $this->runWrapper($state);

        $this->assertTrue(strpos($result['output'], '<title>Histórico de Manutenções - Nenhum Registro Encontrado</title>') !== false, "Expected 'Nenhum Registro Encontrado' title not found");
        $this->assertTrue(strpos($result['output'], 'Não foram encontrados registros de manutenção') !== false, "Expected 'no records' message not found");
    }
}
