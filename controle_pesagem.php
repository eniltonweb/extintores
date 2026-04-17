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
          $cacheDir = __DIR__ . '/cache';
          $cacheFile = $cacheDir . '/co2_extintores_options.html';
          $cacheTime = 3600; // 1 hour cache

          if (!is_dir($cacheDir)) {
              mkdir($cacheDir, 0755, true);
          }

          if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
            echo file_get_contents($cacheFile);
          } else {
            $resultado = $conn->query("SELECT id, codigo FROM bd_extintores WHERE tip_extintor='CO2'");

            // Generate a temporary file to avoid partial reads/writes
            $tempFile = dirname($cacheFile) . '/.tmp_co2_extintores_' . uniqid() . '.html';
            $fp = @fopen($tempFile, 'w');
            $options = [];

            if ($resultado) {
              while($linha = $resultado->fetch_assoc()){
                $id_safe = htmlspecialchars((string)($linha['id'] ?? ''), ENT_QUOTES, 'UTF-8');
                $codigo_safe = htmlspecialchars((string)($linha['codigo'] ?? ''), ENT_QUOTES, 'UTF-8');
                $options[] = "<option value='{$id_safe}'>{$codigo_safe}</option>";
              }

              if ($fp) {
                foreach ($options as $opt) {
                  fwrite($fp, $opt . "\n");
                }
                fclose($fp);
                // Atomic write for cache file
                rename($tempFile, dirname($cacheFile) . '/' . basename($cacheFile));
              }

              echo implode("\n", $options);
            } elseif ($fp) {
                fclose($fp);
                unlink(dirname($cacheFile) . '/' . basename($tempFile));
            }
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

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
