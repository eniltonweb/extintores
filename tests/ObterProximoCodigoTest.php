<?php
class ObterProximoCodigoTest extends MiniTestCase {

    private function runWrapper($stateData) {
        $wrapper_script = __DIR__ . '/wrapper_obter_proximo_codigo.php';
        $json_data = escapeshellarg(json_encode($stateData));

        $cmd = PHP_BINARY . " {$wrapper_script} {$json_data} 2>&1";
        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    public function testReturns403WhenNoSession() {
        $state = [];
        $result = $this->runWrapper($state);

        $json_response = json_decode($result['output'], true);

        $this->assertTrue(
            isset($json_response['erro']) && $json_response['erro'] === 'Não autorizado',
            "Expected 'Não autorizado' error when there is no session."
        );
    }

    public function testReturns403WhenNotBombeiro() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'admin'
            ]
        ];
        $result = $this->runWrapper($state);

        $json_response = json_decode($result['output'], true);

        $this->assertTrue(
            isset($json_response['erro']) && $json_response['erro'] === 'Não autorizado',
            "Expected 'Não autorizado' error when user is not a bombeiro."
        );
    }

    public function testReturnsNextCodeWhenAuthorized() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'bombeiro'
            ],
            'get' => [
                'predio' => 'PREDIO'
            ]
        ];
        $result = $this->runWrapper($state);

        $json_response = json_decode($result['output'], true);

        $this->assertTrue(
            !isset($json_response['erro']),
            "Expected no error when user is an authorized bombeiro."
        );
        $this->assertTrue(
            isset($json_response['proximo_codigo']) && $json_response['proximo_codigo'] === 'PREDIO-101',
            "Expected the correct incremented code."
        );
    }
}
?>
