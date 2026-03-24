<?php

class NovoExtintorTest extends MiniTestCase {

    private function runWrapper($sessionData) {
        $wrapper_script = __DIR__ . '/wrapper_novo_extintor.php';
        $json_data = escapeshellarg(json_encode(['session' => $sessionData]));

        // Execute the wrapper and capture output
        $cmd = "php {$wrapper_script} {$json_data} 2>&1";

        exec($cmd, $output, $return_var);

        return [
            'output' => implode("\n", $output),
            'status' => $return_var
        ];
    }

    private function stripPhpNotices($output) {
        // Remove PHP notices about session_start from the output for clean assertion
        $lines = explode("\n", $output);
        $cleanLines = array_filter($lines, function($line) {
            return strpos($line, 'PHP Notice:  session_start(): Ignoring session_start()') === false;
        });
        return implode("\n", $cleanLines);
    }

    public function testRedirectsWhenNoSession() {
        $result = $this->runWrapper([]);
        $cleanOutput = $this->stripPhpNotices($result['output']);

        // We expect either headers to be sent, or empty output because of exit()
        $this->assertTrue(
            strpos($cleanOutput, '[TEST_HEADERS_SENT]') !== false || empty(trim($cleanOutput)),
            "Expected a redirect header to be sent (or script exited) when the user is not logged in. Output was: " . $cleanOutput
        );

        $this->assertTrue(
            strpos($cleanOutput, 'Adicionar Novo Extintor') === false,
            "Form should not be rendered for unauthorized users."
        );
    }

    public function testRedirectsWhenNotBombeiro() {
        $session = [
            'user_id' => 1,
            'user_level' => 'admin'
        ];

        $result = $this->runWrapper($session);
        $cleanOutput = $this->stripPhpNotices($result['output']);

        $this->assertTrue(
            strpos($cleanOutput, '[TEST_HEADERS_SENT]') !== false || empty(trim($cleanOutput)),
            "Expected a redirect header to be sent (or script exited) when the user is not a bombeiro. Output was: " . $cleanOutput
        );

        $this->assertTrue(
            strpos($cleanOutput, 'Adicionar Novo Extintor') === false,
            "Form should not be rendered for non-bombeiro users."
        );
    }

    public function testRendersFormWhenBombeiro() {
        $session = [
            'user_id' => 1,
            'user_level' => 'bombeiro'
        ];

        $result = $this->runWrapper($session);
        $cleanOutput = $this->stripPhpNotices($result['output']);

        $this->assertTrue(
            strpos($cleanOutput, '[TEST_HEADERS_SENT]') === false,
            "Expected NO redirect headers to be sent when the user is authorized."
        );

        $this->assertTrue(
            strpos($cleanOutput, 'Adicionar Novo Extintor') !== false,
            "Form should be rendered for authorized bombeiro users."
        );

        $this->assertTrue(
            strpos($cleanOutput, 'name="novo_predio"') !== false,
            "Form should contain the novo_predio select field."
        );
    }
}
?>