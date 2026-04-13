<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

$error_message = null;

if (!isset($_GET['codigo'])) {
    $error_message = 'Código de barras não fornecido.';
    error_log('codigobarras.php erro: ' . $error_message);
} else {
    $codigo = htmlspecialchars($_GET['codigo']);
    if (!preg_match('/^[a-zA-Z0-9\-]+$/', $codigo)) {
        $error_message = 'Código inválido.';
        error_log('codigobarras.php erro: ' . $error_message . ' | Input: ' . $_GET['codigo']);
    }
}

$user_level = isset($_SESSION['user_level']) ? $_SESSION['user_level'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Cabeçalhos de segurança
header("Content-Security-Policy: default-src 'self'; img-src 'self' http://www.enilton.com.br; script-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://maxcdn.bootstrapcdn.com; style-src 'self' https://maxcdn.bootstrapcdn.com 'unsafe-inline';");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Consulta para obter as informações do extintor e o nome do usuário que fez a última inspeção de nível 1
$result = null;
if (!isset($error_message)) {
    $sql = "
        SELECT e.*,
               e.usuario AS usuario_inspecao_nivel1,
               e.usuario_n2 AS usuario_manutencao_nivel2
        FROM bd_extintores e
        WHERE e.codigo = ?
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $codigo);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

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
    <link rel="manifest" href="../public/js/manifest.json">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('../public/js/service-worker.js')
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
<?php
if (isset($error_message)) {
    echo "<div class='alert alert-danger'>" . htmlspecialchars($error_message) . "</div>";
} elseif ($result) {
    if ($result->num_rows > 0) {
        $extintor = $result->fetch_assoc();
        ?>
        <h2 class="detalhes-extintor">Detalhes do Extintor</h2>
        <p><strong>Código:</strong> <?php echo htmlspecialchars($extintor['codigo']); ?></p>
        <p><strong>Prédio:</strong> <?php echo htmlspecialchars($extintor['Predio']); ?></p>
        <p><strong>Atividade:</strong> <?php echo htmlspecialchars($extintor['Atividade']); ?></p>
        <p><strong>Local Exato:</strong> <?php echo htmlspecialchars($extintor['Local_Exato']); ?></p>
        <p><strong>Tipo de Extintor:</strong> <?php echo htmlspecialchars($extintor['tip_extintor']); ?></p>
        <p><strong>Carga:</strong> <?php echo htmlspecialchars($extintor['carga']); ?></p>
        <p><strong>Última Manutenção Nível 1:</strong> 
            <?php
            if (!empty($extintor['inspecao_trimestral_nivel1'])) {
                echo htmlspecialchars(date_format(date_create($extintor['inspecao_trimestral_nivel1']), 'd-m-Y'));
            } else {
                echo 'Não disponível';
            }
            ?>
        </p>
        <p><strong>Usuário Última Inspeção Nível 1:</strong> 
		<?php 
		if (!empty($extintor['usuario_inspecao_nivel1']))	{
			echo htmlspecialchars($extintor['usuario_inspecao_nivel1']); 
		} else {
			echo 'Não Disponível';
		}
		?>
		</p>

        <?php if ($user_id): ?>
        <p><strong>Última Manutenção Nível 2:</strong> 
            <?php
            if (!empty($extintor['manutencao_n2'])) {
                echo htmlspecialchars(date_format(date_create($extintor['manutencao_n2']), 'd-m-Y'));
            } else {
                echo 'Não disponível';
            }
            ?>
        </p>
        <p><strong>Próxima Manutenção Nível 2:</strong> 
            <?php
            if (!empty($extintor['proxima_manutencao_n2'])) {
                echo htmlspecialchars(date_format(date_create($extintor['proxima_manutencao_n2']), 'd-m-Y'));
            } else {
                echo 'Não disponível';
            }
            ?>
        </p>
        <p><strong>Usuário Última Manutenção Nível 2:</strong> <?php echo htmlspecialchars($extintor['usuario_manutencao_nivel2']); ?></p>
        <p><strong>Comentários:</strong> <?php echo !empty($extintor['comentarios']) ? htmlspecialchars($extintor['comentarios']) : 'Nenhum'; ?></p>
        <p><strong>Foto:</strong>
            <?php
            if (!empty($extintor['foto'])) {
                echo '<img src="../uploads/' . htmlspecialchars($extintor['foto']) . '" alt="Foto do Extintor" class="extintor-img">';
            } else {
                echo 'Nenhuma foto disponível';
            }
            ?>
        </p>
        <?php endif; ?>

        <?php if ($user_level == 'bombeiro' && $liberado_inspecao): ?>
            <p><a href="formulario_inspecao.php?predio=<?php echo htmlspecialchars($extintor['Predio']); ?>&codigo=<?php echo $codigo; ?>" class="btn btn-primary">Realizar Inspeção de Nível 1</a></p>
        <?php endif; ?>

        <?php if ($user_level == 'fornecedor' && $liberado_manutencao): ?>
            <p><a href="formulario_manutencao.php?codigo=<?php echo $codigo; ?>" class="btn btn-primary">Realizar Manutenção de Nível 2</a></p>
        <?php endif; ?>
        <?php
    } else {
        echo "<div class='alert alert-warning'>Nenhum extintor encontrado com o código fornecido.</div>";
    }
} elseif (!isset($error_message) && isset($stmt) && $stmt) {
    echo "<div class='alert alert-danger'>Erro ao executar a consulta: " . $stmt->error . "</div>";
}

$conn->close();
?>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>
