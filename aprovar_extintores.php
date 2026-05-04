<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}
// Definir o nível do usuário logado
$user_level = $_SESSION['user_level'];

// Buscar extintores com status "Em espera"
$sql_extintores = "SELECT codigo, Predio, Local_Exato, tip_extintor, carga FROM bd_extintores WHERE status_aprovacao = 'Em espera'";
$result_extintores = $conn->query($sql_extintores);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Aprovar Extintores</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
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
                    <td>
                        <form action="aprovar_extintor.php" method="POST" style="display:inline;">
                            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($row['codigo']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                            <button type="submit" class="btn btn-success">Aprovar</button>
                        </form>
                    </td>
                    <td>
                        <form action="rejeitar_extintor.php" method="POST" style="display:inline;">
                            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($row['codigo']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                            <button type="submit" class="btn btn-danger">Rejeitar</button>
                        </form>
                    </td>
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
