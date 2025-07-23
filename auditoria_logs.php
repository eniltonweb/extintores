<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_selected'])) {
        if (!empty($_POST['logs'])) {
            // Evitar excluir o log que registra a ação de apagar todos os logs
            $sql_exclusao = "SELECT id FROM auditoria_logs WHERE detalhes = 'Todos os logs de auditoria foram apagados'";
            $result_exclusao = $conn->query($sql_exclusao);
            $log_exclusao = $result_exclusao->fetch_assoc();
            $id_exclusao = $log_exclusao['id'];
            
            // Remover o ID do log de exclusão da lista de logs a serem excluídos
            $logs_to_delete = array_diff($_POST['logs'], [$id_exclusao]);

            if (!empty($logs_to_delete)) {
                $logs_to_delete = implode(",", array_map('intval', $logs_to_delete));
                $sql = "DELETE FROM auditoria_logs WHERE id IN ($logs_to_delete)";
                $conn->query($sql);
                $message = "Logs selecionados foram apagados.";
            } else {
                $message = "Nenhum log foi selecionado para exclusão.";
            }
        } else {
            $message = "Nenhum log foi selecionado para exclusão.";
        }
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

$sql = "
    SELECT al.*, u.username, e.codigo AS extintor_codigo
    FROM auditoria_logs al
    LEFT JOIN usuarios u ON al.user_id = u.id
    LEFT JOIN bd_extintores e ON al.extintor_id = e.id
    ORDER BY al.data_hora DESC
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
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff;
            color: #1b1e21;
        }
        .navbar {
            background: linear-gradient(45deg, #001f3f, #27509b);
            border-bottom: 3px solid #fce500;
        }
        .navbar-brand {
            font-size: 1.5rem;
            color: #fce500 !important;
        }
        .nav-link {
            color: #ffffff !important;
            transition: color 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        .nav-link:hover {
            color: #fce500 !important;
            transform: scale(1.1);
        }
        .navbar-toggler {
            border-color: #fce500;
        }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba%25255, 229, 0, 0.7%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
        }
        .dropdown-menu {
            background-color: #27509b;
            border: none;
        }
        .dropdown-item {
            color: #ffffff;
            transition: background-color 0.3s ease-in-out;
        }
        .dropdown-item:hover {
            background-color: #fce500;
            color: #000000;
        }
        .nav-item {
            margin-right: 10px;
        }
        .navbar-nav.ml-auto {
            margin-left: auto;
        }
        .navbar-collapse {
            flex-grow: 1;
            justify-content: space-between;
        }
    </style>
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
</div>

<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>