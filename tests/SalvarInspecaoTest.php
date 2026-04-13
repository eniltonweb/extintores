<?php

class SalvarInspecaoTest extends MiniTestCase {

    private function runWrapper($state) {
        $wrapper_script = __DIR__ . '/wrapper_salvar_inspecao.php';
        $json_data = escapeshellarg(json_encode($state));

        $cmd = "php {$wrapper_script} {$json_data} 2>&1";

        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    private function stripPhpNotices($output) {
        $lines = explode("\n", $output);
        $cleanLines = array_filter($lines, function($line) {
            return strpos($line, 'PHP Notice:  session_start()') === false &&
                   strpos($line, 'PHP Warning') === false;
        });
        return trim(implode("\n", $cleanLines));
    }

    public function testUploadInvalidExtension() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'bombeiro',
                'csrf_token' => 'valid_token'
            ],
            'server' => ['REQUEST_METHOD' => 'POST'],
            'post' => [
                'codigo' => '123',
                'csrf_token' => 'valid_token'
            ],
            'files' => [
                'foto' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => 'CREATE_TEXT_FILE',
                    'error' => 0,
                    'size' => 100
                ]
            ]
        ];

        $result = $this->runWrapper($state);
        $cleanOutput = $this->stripPhpNotices($result['output']);

        $this->assertTrue(
            strpos($cleanOutput, 'Tipo de arquivo não permitido.') !== false,
            "Expected error message for invalid file extension. Got: " . $cleanOutput
        );
    }

    public function testUploadInvalidMimeType() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'bombeiro',
                'csrf_token' => 'valid_token'
            ],
            'server' => ['REQUEST_METHOD' => 'POST'],
            'post' => [
                'codigo' => '123',
                'csrf_token' => 'valid_token'
            ],
            'files' => [
                'foto' => [
                    'name' => 'test.jpg', // Valid extension
                    'type' => 'image/jpeg',
                    'tmp_name' => 'CREATE_TEXT_FILE', // But invalid mime (text)
                    'error' => 0,
                    'size' => 100
                ]
            ]
        ];

        $result = $this->runWrapper($state);
        $cleanOutput = $this->stripPhpNotices($result['output']);

        $this->assertTrue(
            strpos($cleanOutput, 'Tipo MIME não permitido.') !== false,
            "Expected error message for invalid MIME type. Got: " . $cleanOutput
        );
    }

    public function testUploadMoveFileError() {
        $state = [
            'session' => [
                'user_id' => 1,
                'user_level' => 'bombeiro',
                'csrf_token' => 'valid_token'
            ],
            'server' => ['REQUEST_METHOD' => 'POST'],
            'post' => [
                'codigo' => '123',
                'csrf_token' => 'valid_token'
            ],
            'files' => [
                'foto' => [
                    'name' => 'test.jpg', // Valid extension
                    'type' => 'image/jpeg',
                    'tmp_name' => 'CREATE_IMAGE_FILE', // Valid mime (image)
                    'error' => 0,
                    'size' => 100
                ]
            ]
        ];

        $result = $this->runWrapper($state);
        $cleanOutput = $this->stripPhpNotices($result['output']);

        // Since it uses a CLI test runner, move_uploaded_file will fail because the file wasn't actually uploaded via HTTP POST.
        // It should die with "Erro ao salvar a foto."

        $this->assertTrue(
            strpos($cleanOutput, 'Erro ao salvar a foto.') !== false,
            "Expected error message for failed move_uploaded_file. Got: " . $cleanOutput
        );
    }
}
?>