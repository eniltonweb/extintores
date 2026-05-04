<?php

require_once __DIR__ . '/runner.php';

class ControlePesagemTest extends MiniTestCase {
    public function testRedirecionaSemSessao() {
        $state = json_encode(['session' => []]);
        $cmd = PHP_BINARY . " " . __DIR__ . "/wrapper_controle_pesagem.php '{$state}'";
        $output = shell_exec($cmd);

        $this->assertTrue(strpos($output, '[TEST_HEADERS_SENT]') !== false, 'Deveria redirecionar quando não há usuário logado');
    }

    public function testRenderizaPaginaComSessaoEDadosDoBanco() {
        $state = json_encode(['session' => ['user_id' => 1]]);
        $cmd = PHP_BINARY . " " . __DIR__ . "/wrapper_controle_pesagem.php '{$state}'";
        $output = shell_exec($cmd);

        // Verifica elementos da página
        $this->assertTrue(strpos($output, 'Controle de Pesagem Extintores CO₂') !== false, 'Título da página deve estar presente');

        // Verifica se os extintores vieram do mock do banco (EXT-001 e EXT-002)
        $this->assertTrue(strpos($output, "<option value='1'>EXT-001</option>") !== false, 'Opção do extintor 1 deve ser renderizada');
        $this->assertTrue(strpos($output, "<option value='2'>EXT-002</option>") !== false, 'Opção do extintor 2 deve ser renderizada');

        // Verifica a tabela do histórico
        $this->assertTrue(strpos($output, '<td>EXT-001</td>') !== false, 'Tabela deve conter EXT-001');
        $this->assertTrue(strpos($output, '<td>✅ OK</td>') !== false, 'Tabela deve mostrar situação ✅ OK para aprovado');
        $this->assertTrue(strpos($output, '<td>EXT-002</td>') !== false, 'Tabela deve conter EXT-002');
        $this->assertTrue(strpos($output, '<td>❌ NOK</td>') !== false, 'Tabela deve mostrar situação ❌ NOK para reprovado');
    }

    public function testUsaCacheSeValido() {
        $cacheContent = "<option value=\"999\">EXT-CACHE</option>";
        $state = json_encode([
            'session' => ['user_id' => 1],
            'cache_data' => $cacheContent
        ]);

        $cmd = PHP_BINARY . " " . __DIR__ . "/wrapper_controle_pesagem.php '{$state}'";
        $output = shell_exec($cmd);

        // Deve mostrar a opção do cache
        $this->assertTrue(strpos($output, "<option value=\"999\">EXT-CACHE</option>") !== false, 'A página deve usar os dados em cache para as opções de extintor');

        // Não deve mostrar as opções do banco se usou cache
        $this->assertTrue(strpos($output, "<option value='1'>EXT-001</option>") === false, 'A página não deve fazer query se o cache for válido');
    }
}
