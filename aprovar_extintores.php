<?php
session_start();
include '../config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}
// Definir o nível do usuário logado
$user_level = $_SESSION['user_level'];

// Buscar extintores com status "Em espera"
$sql_extintores = "SELECT * FROM bd_extintores WHERE status_aprovacao = 'Em espera'";
$result_extintores = $conn->query($sql_extintores);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Aprovar Extintores</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js')
                .then(function(registration) {
                    console.log('Service Worker registrado com sucesso:', registration);
                })
                .catch(function(error) {
                    console.log('Falha ao registrar o Service Worker:', error);
                });
        }
    </script>
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
    <?php
    // Incluir o cabeçalho correto com base no nível de usuário
    if ($user_level == 'admin') {
        include '../templates/header1.php';
    } elseif ($user_level == 'bombeiro') {
        include '../templates/header2.php';
    } elseif ($user_level == 'fornecedor') {
        include '../templates/header3.php';
    } else {
        include '../templates/header.php';
    }
    ?>
<div class="container mt-4">
    <h2>Extintores Aguardando Aprovação</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Código</th>
                <th>Prédio</th>
                <th>Local Exato</th>
                <th>Tipo</th>
                <th>Carga</th>
                <th>Aprovar</th>
                <th>Rejeitar</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result_extintores->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($row['Predio']); ?></td>
                    <td><?php echo htmlspecialchars($row['Local_Exato']); ?></td>
                    <td><?php echo htmlspecialchars($row['tip_extintor']); ?></td>
                    <td><?php echo htmlspecialchars($row['carga']); ?></td>
                    <td><a href="aprovar_extintor.php?codigo=<?php echo urlencode($row['codigo']); ?>" class="btn btn-success">Aprovar</a></td>
                    <td><a href="rejeitar_extintor.php?codigo=<?php echo urlencode($row['codigo']); ?>" class="btn btn-danger">Rejeitar</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
