<?php
session_start();
require_once __DIR__ . "/config/db_conexao.php";

// Verificação de usuário logado
if (!isset($_SESSION['user_id'])){
  header("Location: login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Controle de Pesagem Extintores CO₂</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
</head>
<body>

<?php include 'templates/header_controller.php'; ?>

<div class="container mt-4">
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
      $pesagens = $conn->query("SELECT p.*, e.codigo FROM pesagens_extintores p INNER JOIN bd_extintores e ON p.id_extintor = e.id ORDER BY p.data_pesagem DESC LIMIT 100");
      while($pesagem = $pesagens->fetch_assoc()){
        $pesagem = array_map('htmlspecialchars', $pesagem);
        $situacao = $pesagem['situacao'] == 'Aprovado' ? '✅ OK' : '❌ NOK';
        $codigo = htmlspecialchars($pesagem['codigo'] ?? '', ENT_QUOTES, 'UTF-8');
        $peso_aferido = htmlspecialchars((string)($pesagem['peso_aferido'] ?? ''), ENT_QUOTES, 'UTF-8');
        $percentual_perda = htmlspecialchars((string)($pesagem['percentual_perda'] ?? ''), ENT_QUOTES, 'UTF-8');
        $usuario = htmlspecialchars($pesagem['usuario'] ?? '', ENT_QUOTES, 'UTF-8');

        echo "<tr>
          <td>{$codigo}</td>
          <td>{$peso_aferido} kg</td>
          <td>{$percentual_perda}%</td>
          <td>{$situacao}</td>
          <td>".date('d/m/Y', strtotime($pesagem['data_pesagem']))."</td>
          <td>".date('d/m/Y', strtotime($pesagem['proxima_pesagem']))."</td>
          <td>{$usuario}</td>
        </tr>";
      }
      ?>
    </tbody>
  </table>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>
