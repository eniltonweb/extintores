<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $tipo_liberacao = $_POST['tipo_liberacao'];
    $liberar_para = $_POST['liberar_para'];

    if ($action == 'liberar') {
        if ($tipo_liberacao == 'extintor') {
            $codigo_extintor = $_POST['codigo_extintor'];
            if ($liberar_para == 'bombeiro') {
                $sql = "INSERT INTO liberacao_inspecao (codigo_extintor, liberado_para) VALUES (?, 'bombeiro')";
            } elseif ($liberar_para == 'fornecedor') {
                $sql = "INSERT INTO liberacao_manutencao (codigo_extintor, liberado_para) VALUES (?, 'fornecedor')";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $codigo_extintor);
        } elseif ($tipo_liberacao == 'predio') {
            $predio = $_POST['predio'];
            if ($liberar_para == 'bombeiro') {
                $sql = "INSERT INTO liberacao_inspecao (codigo_extintor, liberado_para) SELECT codigo, 'bombeiro' FROM bd_extintores WHERE Predio = ?";
            } elseif ($liberar_para == 'fornecedor') {
                $sql = "INSERT INTO liberacao_manutencao (codigo_extintor, liberado_para) SELECT codigo, 'fornecedor' FROM bd_extintores WHERE Predio = ?";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $predio);
        }
    } elseif ($action == 'remover') {
        if ($tipo_liberacao == 'extintor') {
            $codigo_extintor = $_POST['codigo_extintor'];
            if ($liberar_para == 'bombeiro') {
                $sql = "DELETE FROM liberacao_inspecao WHERE codigo_extintor = ? AND liberado_para = 'bombeiro'";
            } elseif ($liberar_para == 'fornecedor') {
                $sql = "DELETE FROM liberacao_manutencao WHERE codigo_extintor = ? AND liberado_para = 'fornecedor'";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $codigo_extintor);
        } elseif ($tipo_liberacao == 'predio') {
            $predio = $_POST['predio'];
            if ($liberar_para == 'bombeiro') {
                $sql = "DELETE FROM liberacao_inspecao WHERE codigo_extintor IN (SELECT codigo FROM bd_extintores WHERE Predio = ?) AND liberado_para = 'bombeiro'";
            } elseif ($liberar_para == 'fornecedor') {
                $sql = "DELETE FROM liberacao_manutencao WHERE codigo_extintor IN (SELECT codigo FROM bd_extintores WHERE Predio = ?) AND liberado_para = 'fornecedor'";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $predio);
        }
    }

    if ($stmt->execute()) {
        $message = "Ação realizada com sucesso.";
    } else {
        $message = "Erro ao realizar a ação: " . $stmt->error;
    }
    $stmt->close();
}

// Adicionar um endpoint para carregar os dados de liberação de forma assíncrona
if (isset($_GET['action']) && $_GET['action'] == 'fetch_data') {
    $tipo = $_GET['tipo'];
    $liberado_para = $_GET['liberado_para'];

    if ($tipo == 'inspecao') {
        $sql = "SELECT le.codigo_extintor, be.Predio, be.Atividade, be.Local_Exato 
                FROM liberacao_inspecao le
                JOIN bd_extintores be ON le.codigo_extintor = be.codigo
                WHERE le.liberado_para = ?";
    } elseif ($tipo == 'manutencao') {
        $sql = "SELECT lm.codigo_extintor, be.Predio, be.Atividade, be.Local_Exato 
                FROM liberacao_manutencao lm
                JOIN bd_extintores be ON lm.codigo_extintor = be.codigo
                WHERE lm.liberado_para = ?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $liberado_para);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    echo json_encode($data);
    exit();
}

$sql_extintores = "SELECT codigo, Predio, Atividade, Local_Exato FROM bd_extintores";
$result_extintores = $conn->query($sql_extintores);

$sql_predios = "SELECT DISTINCT Predio FROM bd_extintores";
$result_predios = $conn->query($sql_predios);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Liberação de Manutenções</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <link rel="manifest" href="../public/js/manifest.json">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <a class="navbar-brand" href="index.php">Controle de Extintores</a>
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
    <h2>Liberação de Manutenções</h2>

    <?php if (isset($message)) : ?>
        <div class="alert alert-info">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="liberar_manutencao.php">
        <div class="form-group">
            <label for="liberar_para">Liberar para:</label>
            <select id="liberar_para" name="liberar_para" class="form-control" required>
                <option value="bombeiro">Bombeiro (Inspeção Nível 1)</option>
                <option value="fornecedor">Fornecedor (Manutenção Nível 2)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="tipo_liberacao">Tipo de Liberação:</label>
            <select id="tipo_liberacao" name="tipo_liberacao" class="form-control" onchange="toggleTipoLiberacao(this.value)" required>
                <option value="extintor">Por Extintor</option>
                <option value="predio">Por Prédio</option>
            </select>
        </div>

        <div id="extintor_container" class="form-group">
            <label for="codigo_extintor">Código do Extintor:</label>
            <select id="codigo_extintor" name="codigo_extintor" class="form-control">
                <?php while ($row = $result_extintores->fetch_assoc()) : ?>
                    <option value="<?php echo htmlspecialchars($row['codigo']); ?>">
                        <?php echo htmlspecialchars($row['codigo'] . " - " . $row['Predio'] . " - " . $row['Atividade'] . " - " . $row['Local_Exato']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div id="predio_container" class="form-group" style="display: none;">
            <label for="predio">Prédio:</label>
            <select id="predio" name="predio" class="form-control">
                <?php while ($row = $result_predios->fetch_assoc()) : ?>
                    <option value="<?php echo htmlspecialchars($row['Predio']); ?>">
                        <?php echo htmlspecialchars($row['Predio']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" name="action" value="liberar" class="btn btn-primary">Liberar</button>
            <button type="submit" name="action" value="remover" class="btn btn-danger">Remover Liberação</button>
        </div>
    </form>

    <h3>Extintores Liberados para Inspeção Nível 1</h3>
    <table id="inspecao_bombeiro_table" class="table table-bordered">
        <thead>
            <tr>
                <th>Código</th>
                <th>Prédio</th>
                <th>Atividade</th>
                <th>Local Exato</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <h3>Extintores Liberados para Manutenção Nível 2</h3>
    <table id="manutencao_fornecedor_table" class="table table-bordered">
        <thead>
            <tr>
                <th>Código</th>
                <th>Prédio</th>
                <th>Atividade</th>
                <th>Local Exato</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; 2024 Sistema de Controle de Extintores</p>
    </div>
</footer>
	<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadLiberados('inspecao', 'bombeiro');
        loadLiberados('manutencao', 'fornecedor');
    });

    function loadLiberados(tipo, liberado_para) {
        fetch(`liberar_manutencao.php?action=fetch_data&tipo=${tipo}&liberado_para=${liberado_para}`)
            .then(response => response.json())
            .then(data => {
                let tableBody = document.querySelector(`#${tipo}_${liberado_para}_table tbody`);
                tableBody.innerHTML = '';
                data.forEach(row => {
                    let tr = document.createElement('tr');
                    tr.innerHTML = `<td>${row.codigo_extintor}</td>
                                    <td>${row.Predio}</td>
                                    <td>${row.Atividade}</td>
                                    <td>${row.Local_Exato}</td>`;
                    tableBody.appendChild(tr);
                });
            })
            .catch(error => console.error('Error:', error));
    }

    function toggleTipoLiberacao(tipo) {
        if (tipo === 'extintor') {
            document.getElementById('extintor_container').style.display = 'block';
            document.getElementById('predio_container').style.display = 'none';
        } else if (tipo === 'predio') {
            document.getElementById('extintor_container').style.display = 'none';
            document.getElementById('predio_container').style.display = 'block';
        }
    }
</script>
</body>
</html>
