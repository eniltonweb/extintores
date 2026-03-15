<?php
session_start();
require_once __DIR__ . "/config/db_conexao.php";

// Verificação de usuário logado
if (!isset($_SESSION['nome_usuario'])){
  header("Location: login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Controle de Pesagem Extintores CO₂</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #003b80;">
  <a class="navbar-brand" href="#">Michelin</a>
  <div class="collapse navbar-collapse">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
      <li class="nav-item"><a class="nav-link" href="extintores.php">Extintores</a></li>
      <li class="nav-item"><a class="nav-link" href="inspecoes.php">Inspeções</a></li>
      <li class="nav-item active"><a class="nav-link" href="controle_pesagem.php">Pesagem CO₂</a></li>
      <li class="nav-item"><a class="nav-link" href="usuarios.php">Usuários</a></li>
      <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
    </ul>
  </div>
</nav>

<div class="container mt-4">
  <h3>Registrar Pesagem de Extintores CO₂</h3>

  <form action="salvar_pesagem.php" method="post">
    <div class="form-group">
      <label>Selecione o Extintor</label>
      <select name="id_extintor" class="form-control" required>
        <?php
          $resultado = $conn->query("SELECT id, codigo FROM bd_extintores WHERE tip_extintor='CO2'");
          while($linha = $resultado->fetch_assoc()){
            echo "<option value='{$linha['id']}'>{$linha['codigo']}</option>";
          }
        ?>
      </select>
    </div>

    <div class="form-group">
      <label>Peso Aferido (kg)</label>
      <input type="number" step="0.01" name="peso_aferido" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">Registrar Pesagem</button>
  </form>

  <hr>

  <h4>Histórico de Pesagens</h4>
  <table class="table table-striped">
    <thead class="thead-dark">
      <tr>
        <th>Código Extintor</th>
        <th>Peso Aferido</th>
        <th>% Perda</th>
        <th>Situação</th>
        <th>Última Pesagem</th>
        <th>Próxima Pesagem</th>
        <th>Técnico</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $pesagens = $conn->query("SELECT p.*, e.codigo FROM pesagens_extintores p INNER JOIN bd_extintores e ON p.id_extintor = e.id ORDER BY p.data_pesagem DESC");
      while($pesagem = $pesagens->fetch_assoc()){
        $situacao = $pesagem['situacao'] == 'Aprovado' ? '✅ OK' : '❌ NOK';
        echo "<tr>
          <td>{$pesagem['codigo']}</td>
          <td>{$pesagem['peso_aferido']} kg</td>
          <td>{$pesagem['percentual_perda']}%</td>
          <td>{$situacao}</td>
          <td>".date('d/m/Y', strtotime($pesagem['data_pesagem']))."</td>
          <td>".date('d/m/Y', strtotime($pesagem['proxima_pesagem']))."</td>
          <td>{$pesagem['usuario']}</td>
        </tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
