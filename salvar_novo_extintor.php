<?php
// Habilitar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'bombeiro') {
    header('Location: index.php');
    exit();
}

// Verificar se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $novo_predio = filter_input(INPUT_POST, 'novo_predio', FILTER_SANITIZE_SPECIAL_CHARS);
    $novo_codigo = filter_input(INPUT_POST, 'novo_codigo', FILTER_SANITIZE_SPECIAL_CHARS);
    $novo_local = filter_input(INPUT_POST, 'novo_local', FILTER_SANITIZE_SPECIAL_CHARS);
    $novo_tipo = filter_input(INPUT_POST, 'novo_tipo', FILTER_SANITIZE_SPECIAL_CHARS);
    $novo_carga = filter_input(INPUT_POST, 'novo_carga', FILTER_SANITIZE_SPECIAL_CHARS);

    // Verificar se todos os campos obrigatórios foram preenchidos
    if ($novo_predio && $novo_codigo && $novo_local && $novo_tipo && $novo_carga) {
        
        // Verificar se o código do extintor já existe
        $sql_verificar_codigo = "SELECT 1 FROM bd_extintores WHERE codigo = ?";
        $stmt_verificar_codigo = $conn->prepare($sql_verificar_codigo);
        $stmt_verificar_codigo->bind_param("s", $novo_codigo);
        $stmt_verificar_codigo->execute();
        $result_verificar_codigo = $stmt_verificar_codigo->get_result();

        if ($result_verificar_codigo->num_rows > 0) {
            // Caso o código já exista, redirecionar com mensagem de erro
            header('Location: formulario_inspecao.php?message=Erro:+O+código+do+extintor+já+existe.');
            exit();
        }

        // Buscar a atividade referente ao prédio escolhido
        $sql_atividade = "SELECT Atividade FROM bd_extintores WHERE Predio = ? LIMIT 1";
        $stmt_atividade = $conn->prepare($sql_atividade);
        $stmt_atividade->bind_param("s", $novo_predio);
        $stmt_atividade->execute();
        $result_atividade = $stmt_atividade->get_result();

        if ($result_atividade->num_rows > 0) {
            $row_atividade = $result_atividade->fetch_assoc();
            $atividade = $row_atividade['Atividade'];
        } else {
            // Caso não exista atividade registrada, usar um valor padrão (neste exemplo, estamos iniciando com '1')
            $atividade = '1';
        }

        // Inserir o novo extintor na base de dados
        $sql_inserir_extintor = "
            INSERT INTO bd_extintores (codigo, Predio, Local_Exato, tip_extintor, carga, Atividade, status_aprovacao) 
            VALUES (?, ?, ?, ?, ?, ?, 'Em espera')
        ";
        $stmt_inserir_extintor = $conn->prepare($sql_inserir_extintor);
        $stmt_inserir_extintor->bind_param("ssssss", $novo_codigo, $novo_predio, $novo_local, $novo_tipo, $novo_carga, $atividade);

        if ($stmt_inserir_extintor->execute()) {
            // Inserção bem-sucedida - registrar na auditoria e redirecionar com mensagem de sucesso
            auditoria('Novo extintor adicionado', $novo_codigo, $_SESSION['user_id'], $_SESSION['user_level'], "Prédio: $novo_predio, Local: $novo_local");
            header('Location: formulario_inspecao.php?message=Novo+extintor+adicionado+com+sucesso+e+aguardando+aprovação.');
            exit();
        } else {
            // Inserção falhou - redirecionar com mensagem de erro
            header('Location: formulario_inspecao.php?message=Erro:+Não+foi+possível+adicionar+o+novo+extintor.&erro=' . $stmt_inserir_extintor->error);
            exit();
        }
    } else {
        // Campos obrigatórios não preenchidos - redirecionar com mensagem de erro
        header('Location: formulario_inspecao.php?message=Erro:+Por+favor,+preencha+todos+os+campos+obrigatórios.');
        exit();
    }
}

// Caso o método de requisição não seja POST, redirecionar para a página inicial
header('Location: index.php');
exit();
?>