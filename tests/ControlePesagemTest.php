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

        // O formulário de registro (e a renderização de opções) foi removido como parte de melhorias de saúde de código.
        // O teste é focado agora no histórico e funcionalidade remanescente.

        // Verifica a tabela do histórico
        $this->assertTrue(strpos($output, '<td>EXT-001</td>') !== false, 'Tabela deve conter EXT-001');
        $this->assertTrue(strpos($output, '<td>✅ OK</td>') !== false, 'Tabela deve mostrar situação ✅ OK para aprovado');
        $this->assertTrue(strpos($output, '<td>EXT-002</td>') !== false, 'Tabela deve conter EXT-002');
        $this->assertTrue(strpos($output, '<td>❌ NOK</td>') !== false, 'Tabela deve mostrar situação ❌ NOK para reprovado');
    }
}
