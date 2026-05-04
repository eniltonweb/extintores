<?php

if (!function_exists('apagar_logs_selecionados')) {
    function apagar_logs_selecionados($conn, $logs_post) {
        if (empty($logs_post) || !is_array($logs_post)) {
            return "Nenhum log foi selecionado para exclusão.";
        }

        // Evitar excluir o log que registra a ação de apagar todos os logs
        $sql_exclusao = "SELECT id FROM auditoria_logs WHERE detalhes = 'Todos os logs de auditoria foram apagados'";
        $result_exclusao = $conn->query($sql_exclusao);

        $id_exclusao = null;
        if ($result_exclusao && $log_exclusao = $result_exclusao->fetch_assoc()) {
            $id_exclusao = $log_exclusao['id'];
        }

        // Remover o ID do log de exclusão da lista de logs a serem excluídos
        $logs_to_delete = $id_exclusao ? array_diff($logs_post, [$id_exclusao]) : $logs_post;

        if (empty($logs_to_delete)) {
            return "Nenhum log foi selecionado para exclusão.";
        }

        $logs_to_delete = array_map('intval', array_values($logs_to_delete));
        $placeholders = implode(",", array_fill(0, count($logs_to_delete), "?"));
        $sql = "DELETE FROM auditoria_logs WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return "Erro ao preparar a exclusão.";
        }

        $types = str_repeat('i', count($logs_to_delete));
        $bind_params = [];
        foreach ($logs_to_delete as $key => $value) {
            $bind_params[$key] = &$logs_to_delete[$key];
        }
        $stmt->bind_param($types, ...$bind_params);
        $stmt->execute();
        $stmt->close();

        return "Logs selecionados foram apagados.";
    }
}
session_start();
require_once __DIR__ . '/config/db_conexao.php';
// Gerar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("Tentativa de CSRF detectada em auditoria_logs.php. User ID: " . ($_SESSION['user_id'] ?? 'desconhecido'));
        $message = "Erro de validação de segurança CSRF.";
    } elseif (isset($_POST['delete_selected'])) {
        $message = apagar_logs_selecionados($conn, $_POST['logs'] ?? []);
    } elseif (isset($_POST['delete_all'])) {
        $sql = "DELETE FROM auditoria_logs";
        if ($conn->query($sql) === TRUE) {
            $message = "Todos os logs foram apagados.";
            // Registrar a ação no log de auditoria
            auditoria('Apagar Todos os Logs', null, $_SESSION['user_id'], $_SESSION['user_level'], 'Todos os logs de auditoria foram apagados');
        } else {
            $message = "Erro ao apagar todos os logs: " . $conn->error;
        }
    }
}

// Configuração da paginação
$itens_por_pagina = 20;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contar total de registros para a paginação
$sql_count = "SELECT COUNT(*) AS total FROM auditoria_logs";
$result_count = $conn->query($sql_count);
$total_registros = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

$sql = "
    SELECT al.*, u.username, e.codigo AS extintor_codigo
    FROM auditoria_logs al
    LEFT JOIN usuarios u ON al.user_id = u.id
    LEFT JOIN bd_extintores e ON al.extintor_id = e.id
    ORDER BY al.data_hora DESC
    LIMIT $itens_por_pagina OFFSET $offset
";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sistema de Controle e Manutenção de Extintores</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="index.php">Logs para Auditoria</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="liberar_manutencao.php">Liberar Extintores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="historico_inspecao.php">Historico Inspeções</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="historico_manutencao.php">Histórico Manutenções</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="filtro_vencimento.php">Vencimento Extintores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="registrar_usuario.php">Gerenciar Usuários</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="auditoria_logs.php">Log Auditoria</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="exportar_dados.php">Exportar</a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="sair.php">Sair</a>
                </li>
            </ul>
        </div>
    </nav>

<div class="container mt-4">
    <h2 class="text-center">Logs de Auditoria</h2>

    <?php if (isset($message)) : ?>
        <div class="alert alert-info" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="auditoria_logs.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th></th>
                    <th>Data e Hora</th>
                    <th>Usuário</th>
                    <th>Nível do Usuário</th>
                    <th>Ação</th>
                    <th>Extintor</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) : ?>
                    <tr>
                        <td>
                            <?php if ($row['detalhes'] != 'Todos os logs de auditoria foram apagados') : ?>
                                <input type="checkbox" name="logs[]" value="<?php echo htmlspecialchars($row['id']); ?>">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['data_hora']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['user_level']); ?></td>
                        <td><?php echo htmlspecialchars($row['action']); ?></td>
                        <td><?php echo htmlspecialchars($row['extintor_codigo']); ?></td>
                        <td><?php echo htmlspecialchars($row['detalhes']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="text-center">
            <button type="submit" name="delete_selected" class="btn btn-danger">Apagar Selecionados</button>
            <button type="submit" name="delete_all" class="btn btn-danger">Apagar Todos</button>
        </div>
    </form>

    <nav aria-label="Navegação de página" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($pagina_atual > 1) : ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $pagina_atual - 1; ?>">Anterior</a>
                </li>
            <?php endif; ?>

            <?php
            $range = 2;
            for ($i = 1; $i <= $total_paginas; $i++) :
                if ($i == 1 || $i == $total_paginas || ($i >= $pagina_atual - $range && $i <= $pagina_atual + $range)) :
            ?>
                    <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
            <?php
                elseif ($i == $pagina_atual - $range - 1 || $i == $pagina_atual + $range + 1) :
            ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php
                endif;
            endfor;
            ?>

            <?php if ($pagina_atual < $total_paginas) : ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $pagina_atual + 1; ?>">Próximo</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>