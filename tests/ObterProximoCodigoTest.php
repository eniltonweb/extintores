<?php

require_once __DIR__ . '/runner.php';

class ObterProximoCodigoTest extends MiniTestCase {

    private function runWrapper($stateData) {
        $wrapper_script = __DIR__ . '/wrapper_obter_proximo_codigo.php';
        $json_data = escapeshellarg(json_encode($stateData));

        $cmd = "php {$wrapper_script} {$json_data} 2>&1";
        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    public function testPredioVazioRetornaVazio() {
        $state = [
            'get' => [] // $_GET['predio'] is empty/null
        ];

        $result = $this->runWrapper($state);
        $json = json_decode($result['output'], true);

        $this->assertEquals(
            '',
            $json['proximo_codigo'],
            "Expected empty proximo_codigo when predio is not provided."
        );
    }

    public function testPredioSemExtintoresRetornaCodigo1() {
        $state = [
            'get' => [
                'predio' => 'A'
            ],
            'db_rows' => [] // No extintores found
        ];

        $result = $this->runWrapper($state);
        $json = json_decode($result['output'], true);

        $this->assertEquals(
            'A-1',
            $json['proximo_codigo'],
            "Expected proximo_codigo to be A-1 when no extintores exist."
        );
    }

    public function testIncrementaCodigoValido() {
        $state = [
            'get' => [
                'predio' => 'B'
            ],
            'db_rows' => [
                ['codigo' => 'B-5']
            ]
        ];

        $result = $this->runWrapper($state);
        $json = json_decode($result['output'], true);

        $this->assertEquals(
            'B-6',
            $json['proximo_codigo'],
            "Expected proximo_codigo to increment B-5 to B-6."
        );
    }

    public function testFormatoInvalidoRetornaCodigo1() {
        $state = [
            'get' => [
                'predio' => 'C'
            ],
            'db_rows' => [
                ['codigo' => 'C-B'] // Invalid format (not numeric after hyphen)
            ]
        ];

        $result = $this->runWrapper($state);
        $json = json_decode($result['output'], true);

        $this->assertEquals(
            'C-1',
            $json['proximo_codigo'],
            "Expected proximo_codigo to fallback to C-1 when format is invalid."
        );
    }
}
?>
