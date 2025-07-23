<?php
session_start();
include '../config/db_conexao.php';
include 'auditoria.php'; // Certifique-se de que este arquivo existe e tem a função auditoria

// Verificar se o usuário está logado e se tem permissão para acessar esta página
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_level = filter_input(INPUT_POST, 'user_level', FILTER_SANITIZE_SPECIAL_CHARS);

    // Verificar se o nome de usuário já existe
    $sql_check = "SELECT id FROM usuarios WHERE username = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $message = "Nome de usuário já existe.";
    } else {
        // Prevenir SQL Injection usando prepared statements
        $sql = "INSERT INTO usuarios (username, password, nivel_acesso) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $message = "Erro ao preparar a consulta: " . $conn->error;
        } else {
            $stmt->bind_param("sss", $username, $password, $user_level);

            if ($stmt->execute()) {
                auditoria('Registro de usuário', null, $_SESSION['user_id'], $_SESSION['user_level'], 'Usuário registrado com sucesso: ' . $username);
                $message = "Usuário registrado com sucesso.";
            } else {
                $message = "Erro ao registrar usuário: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    $stmt_check->close();
}

// Consultar todos os usuários registrados
if (isset($_GET['action']) && $_GET['action'] == 'fetch_users') {
    $sql = "SELECT * FROM usuarios";
    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['data' => $data]);
    $conn->close();
    exit();
}

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
        th.sortable:hover {
            cursor: pointer;
            color: #007bff;
        }
    </style>
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
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="sair.php">Sair</a>
                </li>
            </ul>
        </div>
    </nav>
<div class="container mt-4">
    <h2 class="text-center">Gerenciar Usuários</h2>

    <?php if (!empty($message)) : ?>
        <div class="alert alert-info" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['message'])) : ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="registrar_usuario.php">
        <div class="form-group">
            <label for="username">Usuário:</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Senha:</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="user_level">Nível de Acesso:</label>
            <select class="form-control" id="user_level" name="user_level">
                <option value="admin">Administrador</option>
                <option value="bombeiro">Bombeiro</option>
                <option value="fornecedor">Fornecedor</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Registrar</button>
    </form>

    <h3 class="mt-4">Usuários Registrados</h3>
    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th class="sortable" data-sort="username">Usuário</th>
                <th class="sortable" data-sort="nivel_acesso">Nível de Acesso</th>
                <th>Ações</th>
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
        loadUsuarios();
    });

    function loadUsuarios() {
        const url = 'registrar_usuario.php?action=fetch_users';

        fetch(url)
            .then(response => response.json())
            .then(data => {
                const usuarios = data.data;

                const tableBody = document.querySelector('table tbody');
                tableBody.innerHTML = '';

                if (usuarios.length > 0) {
                    usuarios.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${row.username}</td>
                            <td>${row.nivel_acesso}</td>
                            <td>
                                <a href="deletar_usuario.php?id=${row.id}" class="btn btn-danger btn-sm">Remover</a>
                                <a href="resetar_senha.php?id=${row.id}" class="btn btn-warning btn-sm">Resetar Senha</a>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="3" class="text-center">Nenhum usuário encontrado.</td>`;
                    tableBody.appendChild(tr);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    document.querySelectorAll('th.sortable').forEach(header => {
        header.addEventListener('click', function() {
            const sortKey = this.getAttribute('data-sort');
            sortTable(sortKey);
        });
    });

    function sortTable(sortKey) {
        const tableBody = document.querySelector('table tbody');
        const rows = Array.from(tableBody.querySelectorAll('tr'));

        const sortedRows = rows.sort((a, b) => {
            let aData, bData;
            if (sortKey === 'username') {
                aData = a.querySelector('td:nth-child(1)').textContent;
                bData = b.querySelector('td:nth-child(1)').textContent;
            } else if (sortKey === 'nivel_acesso') {
                aData = a.querySelector('td:nth-child(2)').textContent;
                bData = b.querySelector('td:nth-child(2)').textContent;
            }
            return aData > bData ? 1 : aData < bData ? -1 : 0;
        });

        tableBody.innerHTML = '';
        sortedRows.forEach(row => tableBody.appendChild(row));
    }
</script>
</body>
</html>
