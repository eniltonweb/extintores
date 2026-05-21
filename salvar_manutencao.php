<?php
if (!function_exists('salvar_manutencao_logic')) {
    function salvar_manutencao_logic($conn, $session, $post, $server) {
        if (!isset($session['user_id']) || $session['user_level'] != 'fornecedor') {
            return 'Location: index.php';
        }

        if ($server['REQUEST_METHOD'] !== 'POST') {
            return 'Location: formulario_manutencao.php?message=' . urlencode('Erro: Método inválido.');
        }

        if (empty($session['csrf_token']) || !isset($post['csrf_token']) || !hash_equals($session['csrf_token'], $post['csrf_token'])) {
            return 'Location: formulario_manutencao.php?message=' . urlencode('Erro: Falha na validação de segurança.');
        }

        // Capturar dados do formulário
        $codigo = filter_var($post['codigo'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $cobertura = isset($post['cobertura']) && $post['cobertura'] == '1' ? 1 : 0;
        $manutencao_n2 = isset($post['manutencao_n2']) && $post['manutencao_n2'] == '1' ? 1 : 0;
        
        // NOVO: Captura do selo INMETRO
        $novo_selo_inmetro = filter_var($post['novo_selo_inmetro'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);

        // Garantir que o código não esteja vazio
        if (empty($codigo)) {
            return 'Location: formulario_manutencao.php?message=' . urlencode('Erro: Código do extintor não especificado.');
        }

        // Verificar autorização: o extintor deve estar liberado para manutenção por fornecedor
        $sql_check_auth = "SELECT 1 FROM liberacao_manutencao WHERE codigo_extintor = ? AND liberado_para = 'fornecedor' LIMIT 1";
        $stmt_check_auth = $conn->prepare($sql_check_auth);

        if ($stmt_check_auth === false) {
            error_log("Erro ao preparar consulta para verificar autorização: " . $conn->error);
            return 'Location: formulario_manutencao.php?message=' . urlencode('Erro interno ao verificar autorização.');
        }

        $stmt_check_auth->bind_param("s", $codigo);
        $stmt_check_auth->execute();
        $result_auth = $stmt_check_auth->get_result();
        $authorized = false;

        if ($result_auth && $result_auth->num_rows > 0) {
            $authorized = true;
        }
        $stmt_check_auth->close();

        if (!$authorized) {
            return 'Location: formulario_manutencao.php?message=' . urlencode('Erro: Acesso negado. O extintor não está liberado para manutenção ou não existe.');
        }

        // Capturar o nome do usuário logado a partir da sessão
        $username = $session['user_name'] ?? null;

        // Verificar se o username foi recuperado corretamente
        if (empty($username)) {
            return 'Location: formulario_manutencao.php?message=' . urlencode('Erro ao capturar o usuário logado.');
        }

        // Variável para armazenar mensagens de sucesso ou erro
        $message = '';

        // Se o checkbox de manutenção de nível 2 foi marcado
        if ($manutencao_n2) {
            // Validação rigorosa para Auditoria: Impedir fraude sem selo
            if (empty(trim($novo_selo_inmetro))) {
                return 'Location: formulario_manutencao.php?message=' . urlencode('Erro: O código do novo selo INMETRO é obrigatório para manutenções.');
            }

            // Definir data de manutenção atual e próxima manutenção para um ano depois
            $data_manutencao_n2 = date('Y-m-d');
            $data_proxima_manutencao_n2 = date('Y-m-d', strtotime('+1 year'));

            // Atualizar o extintor específico com as informações de manutenção, cobertura e NOVO SELO
            $sql_update_manutencao = "UPDATE bd_extintores
                           SET manutencao_n2 = ?, proxima_manutencao_n2 = ?, usuario_n2 = ?, cobertura = ?, selo_do_Inmetro = ?, updated_at = NOW()
                           WHERE codigo = ? LIMIT 1";
            $stmt_manutencao = $conn->prepare($sql_update_manutencao);

            // Verificar se a preparação da consulta foi bem-sucedida
            if ($stmt_manutencao === false) {
                error_log("Erro ao preparar consulta para atualizar manutenção: " . $conn->error);
                $message .= " Erro interno ao atualizar a manutenção.";
            } else {
                $stmt_manutencao->bind_param("sssiss", $data_manutencao_n2, $data_proxima_manutencao_n2, $username, $cobertura, $novo_selo_inmetro, $codigo);

                // Executar e verificar se a consulta foi bem-sucedida
                if ($stmt_manutencao->execute()) {
                    // Sucesso ao salvar
                    $message .= " Manutenção, selo INMETRO e datas registadas com sucesso!";
                    
                    // Log de auditoria explícito sobre a troca do selo
                    $log_acao = "Manutenção N2 Confirmada (Fornecedor)";
                    $log_detalhe = "Manutenção concluída pelo fornecedor $username. Novo selo INMETRO aplicado: $novo_selo_inmetro.";
                    $sql_log = "INSERT INTO auditoria_logs (user_id, user_level, action, detalhes) VALUES (?, 'fornecedor', ?, ?)";
                    if ($stmt_log = $conn->prepare($sql_log)) {
                        $stmt_log->bind_param("iss", $session['user_id'], $log_acao, $log_detalhe);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                } else {
                    // Erro ao salvar
                    error_log("Erro ao atualizar a manutenção: " . $stmt_manutencao->error);
                    $message .= " Erro interno ao atualizar a manutenção.";
                }
                $stmt_manutencao->close();
            }
        }

        // Se o checkbox de cobertura foi marcado, atualizar apenas a cobertura
        if (!$manutencao_n2 && $cobertura) {
            // Atualizar apenas a cobertura do extintor específico
            $sql_update_cobertura = "UPDATE bd_extintores
                           SET cobertura = ?, usuario_n2 = ?, updated_at = NOW()
                           WHERE codigo = ? LIMIT 1";
            $stmt_cobertura = $conn->prepare($sql_update_cobertura);

            // Verificar se a preparação da consulta foi bem-sucedida
            if ($stmt_cobertura === false) {
                error_log("Erro ao preparar consulta para atualizar cobertura: " . $conn->error);
                $message .= " Erro interno ao atualizar a cobertura.";
            } else {
                $stmt_cobertura->bind_param("iss", $cobertura, $username, $codigo);

                // Executar e verificar se a consulta foi bem-sucedida
                if ($stmt_cobertura->execute()) {
                    // Sucesso ao salvar
                    $message .= " Cobertura atualizada com sucesso!";
                } else {
                    // Erro ao salvar
                    error_log("Erro ao atualizar a cobertura: " . $stmt_cobertura->error);
                    $message .= " Erro interno ao atualizar a cobertura.";
                }
                $stmt_cobertura->close();
            }
        }

        // Atualizar automaticamente os dias para expirar apenas do extintor específico
        $sql_update_dias = "UPDATE bd_extintores
                            SET dias_para_expirar_n2 = DATEDIFF(proxima_manutencao_n2, CURDATE())
                            WHERE codigo = ? LIMIT 1";
        $stmt_dias = $conn->prepare($sql_update_dias);

        if ($stmt_dias === false) {
            error_log("Erro ao preparar consulta para atualizar dias para expirar: " . $conn->error);
            $message .= " Erro interno ao atualizar os dias para expirar.";
        } else {
            $stmt_dias->bind_param("s", $codigo);

            if ($stmt_dias->execute()) {
                // Sucesso ao atualizar os dias
                $message .= " Dias para expirar atualizados com sucesso!";
            } else {
                // Erro ao atualizar os dias
                error_log("Erro ao atualizar os dias para expirar: " . $stmt_dias->error);
                $message .= " Erro interno ao atualizar os dias para expirar.";
            }
            $stmt_dias->close();
        }

        return "Location: formulario_manutencao.php?message=" . urlencode(trim($message));
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    session_start();
    require_once __DIR__ . '/config/db_conexao.php';
    include 'auditoria.php';

    $redirect = salvar_manutencao_logic($conn, $_SESSION, $_POST, $_SERVER);

    // Fechar a conexão
    if ($conn) {
        $conn->close();
    }

    // Redirecionar para a página anterior com uma mensagem
    if ($redirect) {
        header($redirect);
        exit();
    }
}
?>