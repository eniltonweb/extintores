<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php';

if (!isset($_GET['codigo'])) {
    die('Código de barras não fornecido.');
}

$codigo = htmlspecialchars($_GET['codigo']);
if (!preg_match('/^[a-zA-Z0-9\-]+$/', $codigo)) {
    die('Código inválido.');
}

$user_level = isset($_SESSION['user_level']) ? $_SESSION['user_level'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Cabeçalhos de segurança
header("Content-Security-Policy: default-src 'self'; img-src 'self' http://www.enilton.com.br; script-src 'self' https://code.jquery.com https://cdn.jsdelivr.net https://maxcdn.bootstrapcdn.com; style-src 'self' https://maxcdn.bootstrapcdn.com 'unsafe-inline';");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Consulta para obter as informações do extintor e o nome do usuário que fez a última inspeção de nível 1
$sql = "
    SELECT e.*, 
           e.usuario AS usuario_inspecao_nivel1,
           e.usuario_n2 AS usuario_manutencao_nivel2
    FROM bd_extintores e
    WHERE e.codigo = ?
    LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $codigo);
$stmt->execute();
$result = $stmt->get_result();

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
    <link rel="manifest" href="../public/js/manifest.json">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #ffffff;
            color: #1b1e21;
        }
        .navbar-brand {
            font-size: 1.5rem;
            color: #fce500 !important;
            display: flex;
            align-items: center;
        }
        .img-logo {
            max-height: 50px;
            margin-right: 10px;
        }
        .navbar {
            background: linear-gradient(45deg, #001f3f, #27509b);
            border-bottom: 3px solid #fce500;
            justify-content: center;
        }
        .detalhes-extintor {
            text-align: center;
            margin-bottom: 20px;
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
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba%255, 229, 0, 0.7%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
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
        .extintor-img {
            max-width: 200px;
            max-height: 200px;
            border: 2px solid #001f3f;
            border-radius: 10px;
            margin-top: 10px;
        }
        .footer {
            background-color: #003580;
            color: #ffffff;
            padding: 10px 0;
            border-top: 3px solid #fce500;
            border-bottom: 3px solid #fce500;
        }
    </style>
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
if ($result) {
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
} else {
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
