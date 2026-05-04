<?php
require_once __DIR__ . '/runner.php';
require_once __DIR__ . '/MockDatabase.php';

class CodigoBarrasTest extends MiniTestCase {

    private function runCodigobarras($get = [], $session = [], $globals = []) {
        $getExport = var_export($get, true);
        $sessionExport = var_export($session, true);
        $globalsExport = var_export($globals, true);

        // Include MockDatabase definition manually to avoid file path resolution issues in exec
        $testsDir = __DIR__;
        $mockDbContent = file_get_contents(__DIR__ . '/MockDatabase.php');
        $mockDbContent = str_replace('<?php', '', $mockDbContent);

        $script = <<<EOT
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
\$_GET = $getExport;
\$_SESSION = $sessionExport;

foreach ($globalsExport as \$key => \$val) {
    \$GLOBALS[\$key] = \$val;
}

$mockDbContent

require_once '{$testsDir}/wrapper_codigobarras.php';
EOT;
        $tempFile = tempnam(sys_get_temp_dir(), 'test_codigobarras_') . '.php';
        file_put_contents($tempFile, $script);

        ob_start();
        exec(PHP_BINARY . ' ' . escapeshellarg($tempFile) . ' 2>&1', $output, $returnVar);
        ob_end_clean();

        unlink($tempFile);
        return implode("\n", $output);
    }

    public function testSemCodigo() {
        $output = $this->runCodigobarras();
        $this->assertTrue(strpos($output, 'C&oacute;digo de barras n&atilde;o fornecido.') !== false || strpos($output, 'Código de barras não fornecido.') !== false, "Deve exibir mensagem quando o código não é fornecido. Output: " . substr($output, 0, 500));
    }

    public function testCodigoInvalido() {
        $output = $this->runCodigobarras(['codigo' => 'invalido!@#']);
        $this->assertTrue(strpos($output, 'C&oacute;digo inv&aacute;lido.') !== false || strpos($output, 'Código inválido.') !== false, "Deve exibir mensagem de código inválido para formatos não permitidos. Output: " . substr($output, 0, 500));
    }

    public function testExtintorEncontrado() {
        $output = $this->runCodigobarras(['codigo' => 'EXT-001'], [], ['mock_query_results' => [
            "
        SELECT e.*,
               e.usuario AS usuario_inspecao_nivel1,
               e.usuario_n2 AS usuario_manutencao_nivel2
        FROM bd_extintores e
        WHERE e.codigo = ?
        LIMIT 1" => [[
                'codigo' => 'EXT-001',
                'Predio' => 'Prédio A',
                'Atividade' => 'Escritório',
                'Local_Exato' => 'Sala 1',
                'tip_extintor' => 'AP',
                'carga' => '10L',
                'inspecao_trimestral_nivel1' => '2023-01-01',
                'usuario_inspecao_nivel1' => 'João',
                'manutencao_n2' => '2022-01-01',
                'proxima_manutencao_n2' => '2024-01-01',
                'usuario_manutencao_nivel2' => 'Maria',
                'comentarios' => 'Teste',
                'foto' => 'foto.jpg'
            ]]
        ]]);
        $this->assertTrue(strpos($output, 'Detalhes do Extintor') !== false, "Deve exibir Detalhes do Extintor.");
        $this->assertTrue(strpos($output, 'EXT-001') !== false, "Deve exibir o código do extintor.");
    }

    public function testExtintorNaoEncontrado() {
        $output = $this->runCodigobarras(['codigo' => 'EXT-002'], [], ['mock_query_results' => [
            "
        SELECT e.*,
               e.usuario AS usuario_inspecao_nivel1,
               e.usuario_n2 AS usuario_manutencao_nivel2
        FROM bd_extintores e
        WHERE e.codigo = ?
        LIMIT 1" => []
        ]]);
        $this->assertTrue(strpos($output, 'Nenhum extintor encontrado com o c&oacute;digo fornecido.') !== false || strpos($output, 'Nenhum extintor encontrado com o código fornecido.') !== false, "Deve informar que o extintor não foi encontrado.");
    }

    public function testErroNoBanco() {
        $output = $this->runCodigobarras(['codigo' => 'EXT-003'], [], ['mock_prepare_result' => false]);
        $this->assertTrue(strpos($output, 'Erro ao executar a consulta') !== false, "Deve exibir mensagem de erro de execução. Output: " . substr($output, 0, 500));
    }

    public function testBotaoInspecaoBombeiro() {
        $output = $this->runCodigobarras(
            ['codigo' => 'EXT-001'],
            ['user_level' => 'bombeiro', 'user_id' => 1],
            ['mock_query_results' => [
                "
        SELECT e.*,
               e.usuario AS usuario_inspecao_nivel1,
               e.usuario_n2 AS usuario_manutencao_nivel2
        FROM bd_extintores e
        WHERE e.codigo = ?
        LIMIT 1" => [[
                    'codigo' => 'EXT-001',
                    'Predio' => 'Prédio A',
                    'Atividade' => 'Escritório',
                    'Local_Exato' => 'Sala 1',
                    'tip_extintor' => 'AP',
                    'carga' => '10L',
                    'inspecao_trimestral_nivel1' => '2023-01-01',
                    'usuario_inspecao_nivel1' => 'João',
                    'manutencao_n2' => '2022-01-01',
                    'proxima_manutencao_n2' => '2024-01-01',
                    'usuario_manutencao_nivel2' => 'Maria',
                    'comentarios' => 'Teste',
                    'foto' => 'foto.jpg'
                ]]
            ], 'liberado_inspecao' => true]
        );
        $this->assertTrue(strpos($output, 'Realizar Inspe&ccedil;&atilde;o de N&iacute;vel 1') !== false || strpos($output, 'Realizar Inspeção de Nível 1') !== false, "Deve exibir o botão de Inspeção para bombeiros. Output: " . substr($output, 2000, 3000));
    }

    public function testBotaoManutencaoFornecedor() {
        $output = $this->runCodigobarras(
            ['codigo' => 'EXT-001'],
            ['user_level' => 'fornecedor', 'user_id' => 1],
            ['mock_query_results' => [
                "
        SELECT e.*,
               e.usuario AS usuario_inspecao_nivel1,
               e.usuario_n2 AS usuario_manutencao_nivel2
        FROM bd_extintores e
        WHERE e.codigo = ?
        LIMIT 1" => [[
                    'codigo' => 'EXT-001',
                    'Predio' => 'Prédio A',
                    'Atividade' => 'Escritório',
                    'Local_Exato' => 'Sala 1',
                    'tip_extintor' => 'AP',
                    'carga' => '10L',
                    'inspecao_trimestral_nivel1' => '2023-01-01',
                    'usuario_inspecao_nivel1' => 'João',
                    'manutencao_n2' => '2022-01-01',
                    'proxima_manutencao_n2' => '2024-01-01',
                    'usuario_manutencao_nivel2' => 'Maria',
                    'comentarios' => 'Teste',
                    'foto' => 'foto.jpg'
                ]]
            ], 'liberado_manutencao' => true]
        );
        $this->assertTrue(strpos($output, 'Realizar Manuten&ccedil;&atilde;o de N&iacute;vel 2') !== false || strpos($output, 'Realizar Manutenção de Nível 2') !== false, "Deve exibir o botão de Manutenção para fornecedores.");
    }

    public function testSemPermissao() {
        $output = $this->runCodigobarras(
            ['codigo' => 'EXT-001'],
            ['user_level' => 'comum', 'user_id' => 1],
            ['mock_query_results' => [
                "
        SELECT e.*,
               e.usuario AS usuario_inspecao_nivel1,
               e.usuario_n2 AS usuario_manutencao_nivel2
        FROM bd_extintores e
        WHERE e.codigo = ?
        LIMIT 1" => [[
                    'codigo' => 'EXT-001',
                    'Predio' => 'Prédio A',
                    'Atividade' => 'Escritório',
                    'Local_Exato' => 'Sala 1',
                    'tip_extintor' => 'AP',
                    'carga' => '10L',
                    'inspecao_trimestral_nivel1' => '2023-01-01',
                    'usuario_inspecao_nivel1' => 'João',
                    'manutencao_n2' => '2022-01-01',
                    'proxima_manutencao_n2' => '2024-01-01',
                    'usuario_manutencao_nivel2' => 'Maria',
                    'comentarios' => 'Teste',
                    'foto' => 'foto.jpg'
                ]]
            ], 'liberado_inspecao' => false, 'liberado_manutencao' => false]
        );
        $this->assertTrue(strpos($output, 'Realizar Inspeção de Nível 1') === false, "Nao deve exibir o botão de Inspeção para outro user.");
        $this->assertTrue(strpos($output, 'Realizar Manutenção de Nível 2') === false, "Nao deve exibir o botão de Manutenção para outro user.");
    }
}
