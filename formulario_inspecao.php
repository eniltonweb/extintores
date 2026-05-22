<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'bombeiro') {
    header('Location: index.php');
    exit();
}

$predio = filter_input(INPUT_GET, 'predio', FILTER_SANITIZE_SPECIAL_CHARS);
$codigo = filter_input(INPUT_GET, 'codigo', FILTER_SANITIZE_SPECIAL_CHARS);
$show_form = false;

if ($codigo) {
    // Verificar se a inspeção foi liberada pelo administrador
    $sql_liberacao = "SELECT 1 FROM liberacao_inspecao WHERE codigo_extintor = ? AND liberado_para = 'bombeiro' LIMIT 1";
    $stmt_liberacao = $conn->prepare($sql_liberacao);
    $stmt_liberacao->bind_param("s", $codigo);
    $stmt_liberacao->execute();
    $result_liberacao = $stmt_liberacao->get_result();

    if ($result_liberacao->num_rows == 0) {
        echo "<div class='warning'>A inspeção de nível 1 não foi liberada pelo administrador para este extintor.</div>";
        exit();
    }

    $sql = "SELECT * FROM bd_extintores WHERE codigo = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $show_form = true;
    } else {
        echo "<div class='warning'>Nenhum extintor encontrado com o código fornecido.</div>";
    }
}

// Obter prédios com extintores liberados
$sql_predios = "
    SELECT DISTINCT be.Predio 
    FROM bd_extintores be
    JOIN liberacao_inspecao li ON be.codigo = li.codigo_extintor
    WHERE li.liberado_para = 'bombeiro'
";
$result_predios = $conn->query($sql_predios);

// Obter extintores liberados para o prédio selecionado
if ($predio) {
    $sql_extintores = "
        SELECT be.codigo, be.Predio, be.Atividade, be.Local_Exato 
        FROM bd_extintores be
        JOIN liberacao_inspecao li ON be.codigo = li.codigo_extintor
        WHERE li.liberado_para = 'bombeiro' 
        AND be.Predio = ?
        AND (be.status_aprovacao = 'Aprovado' OR be.status_aprovacao IS NULL)
    ";
    $stmt_extintores = $conn->prepare($sql_extintores);
    $stmt_extintores->bind_param("s", $predio);
    $stmt_extintores->execute();
    $result_extintores = $stmt_extintores->get_result();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Formulário Inspeção de Nivel 1</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header>
    <?php include 'templates/header_controller.php'; ?>
</header>
<div class="container mt-4">
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
	
    <h2>Selecione um Prédio para Inspeção de Nível 1</h2>
    <form method="GET" action="formulario_inspecao.php">
        <label for="predio">Prédio:</label>
        <select id="predio" name="predio" onchange="this.form.submit()" required>
            <option value="">Selecione um Prédio</option>
            <?php while ($row_predios = $result_predios->fetch_assoc()) : ?>
                <option value="<?php echo htmlspecialchars($row_predios['Predio']); ?>" <?php echo ($predio == $row_predios['Predio']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($row_predios['Predio']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if ($predio && $result_extintores->num_rows > 0): ?>
        <h2>Selecione um Extintor</h2>
        <form method="GET" action="formulario_inspecao.php">
            <input type="hidden" name="predio" value="<?php echo htmlspecialchars($predio); ?>">
            <label for="codigo">Extintor:</label>
            <select id="codigo" name="codigo" required>
                <?php while ($row_extintores = $result_extintores->fetch_assoc()) : ?>
                    <option value="<?php echo htmlspecialchars($row_extintores['codigo']); ?>">
                        <?php echo htmlspecialchars($row_extintores['codigo'] . " - " . $row_extintores['Atividade'] . " - " . $row_extintores['Local_Exato']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button type="submit">Selecionar Extintor</button>
        </form>
    <?php endif; ?>

    <?php if ($show_form): ?>
        <form method="POST" action="salvar_inspecao.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>">

            <label for="Local_Exato">Local Exato:</label>
            <input type="text" id="Local_Exato" name="Local_Exato" value="<?php echo htmlspecialchars($row['Local_Exato']); ?>" required><br>

            <div class="card mb-3 border-info">
    <div class="card-header bg-info text-white font-weight-bold">
        Validação de Autenticidade (INMETRO)
    </div>
    <div class="card-body bg-light">
        <p class="mb-2">Selo registado no sistema pela Manutenção:</p>
        <h4 class="text-center font-weight-bold p-2 border border-secondary rounded bg-white text-primary">
            <?php echo !empty($row['selo_do_Inmetro']) ? htmlspecialchars($row['selo_do_Inmetro']) : '<span class="text-danger">Sem selo registado</span>'; ?>
        </h4>
        <p class="text-muted small mt-2">O código alfanumérico físico colado no extintor é EXATAMENTE igual a este número?</p>
        
        <label for="status_selo_inmetro" class="font-weight-bold">Resultado da Verificação:</label>
        <select id="status_selo_inmetro" name="status_selo_inmetro" class="form-control border-info" required>
            <option value="">Selecione...</option>
            <option value="OK">Sim, Confere Perfeitamente (OK)</option>
            <option value="NÃO OK">Divergente / Não Confere / Danificado (NOK)</option>
        </select>
    </div>
</div>

            <label for="sinalizacao_vertical">Sinalização Vertical:</label>
            <select id="sinalizacao_vertical" name="sinalizacao_vertical">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>

            <label for="sinalizacao_piso">Sinalização no Piso:</label>
            <select id="sinalizacao_piso" name="sinalizacao_piso">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>

            <label for="ficha_inspecao_trimestral">Ficha de Inspeção Trimestral:</label>
            <select id="ficha_inspecao_trimestral" name="ficha_inspecao_trimestral">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>

            <label for="lacre">Lacre:</label>
            <select id="lacre" name="lacre">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>

            <label for="pressao_manometro">Pressão do Manômetro:</label>
            <select id="pressao_manometro" name="pressao_manometro">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>

            <label for="anel_identificacao">Anel de Identificação:</label>
            <select id="anel_identificacao" name="anel_identificacao">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>
			
			<?php if($row["tip_extintor"] == "CO2"):?>
            <label for="pesagem_co2_semestral">Pesagem CO2 Semestral:</label>
            <select id="pesagem_co2_semestral" name="pesagem_co2_semestral">
                <option value="OK">OK</option>
                <option value="NÃO OK">NÃO OK</option>
            </select><br>
			<?php endif ?>

            <label for="comentarios">Comentários:</label>
            <textarea id="comentarios" name="comentarios"><?php echo htmlspecialchars($row['comentarios']); ?></textarea><br>

            <label for="foto">Enviar Foto:</label>
            <div class="card mb-3 border-warning mt-3">
  <div class="card-header bg-warning text-dark font-weight-bold">
    📷 Evidências Fotográficas
  </div>
  <div class="card-body">

    <p class="text-muted mb-2">
      Adicione até <strong>5 fotos</strong> como evidência.
      Capture: extintor completo, lacre, manômetro, sinalização, anomalias.
    </p>

    <!-- Preview das fotos antes do envio -->
    <div id="foto-preview-container" class="d-flex flex-wrap mb-2" style="gap:8px;"></div>

    <label for="fotos" class="font-weight-bold">Selecionar Fotos:</label>
    <input
      type="file"
      id="fotos"
      name="fotos[]"
      multiple
      accept="image/jpeg,image/png,image/webp,image/gif"
      class="form-control-file"
      onchange="previewFotos(this)"
    >
    <small class="form-text text-muted">
      Formatos aceitos: JPG, PNG, WEBP. Máximo 5MB por foto.
    </small>

    <!-- Legendas dinâmicas (geradas via JS conforme fotos são selecionadas) -->
    <div id="legendas-container" class="mt-2"></div>

  </div>
</div><br>

            <button type="submit">Salvar Inspeção</button>
        </form>
    <?php endif; ?>
</div>

<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
<script>
function previewFotos(input) {
  var previewContainer = document.getElementById('foto-preview-container');
  var legendasContainer = document.getElementById('legendas-container');
  previewContainer.innerHTML = '';
  legendasContainer.innerHTML = '';

  var files = input.files;
  var maxFotos = 5;

  if (files.length > maxFotos) {
    alert('Máximo de ' + maxFotos + ' fotos permitido. Somente as primeiras ' + maxFotos + ' serão enviadas.');
  }

  for (var i = 0; i < Math.min(files.length, maxFotos); i++) {
    (function(index, file) {
      var reader = new FileReader();
      reader.onload = function(e) {
        // Preview da imagem
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'position:relative;display:inline-block;';

        var img = document.createElement('img');
        img.src = e.target.result;
        img.style.cssText = 'width:90px;height:70px;object-fit:cover;border:2px solid #dee2e6;border-radius:4px;';
        img.title = file.name;

        var badge = document.createElement('span');
        badge.textContent = (index + 1);
        badge.style.cssText = 'position:absolute;top:2px;left:2px;background:#007bff;color:#fff;' +
          'font-size:11px;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;';

        wrapper.appendChild(img);
        wrapper.appendChild(badge);
        previewContainer.appendChild(wrapper);

        // Campo de legenda
        var legendaDiv = document.createElement('div');
        legendaDiv.className = 'input-group input-group-sm mb-1';
        legendaDiv.innerHTML =
          '<div class="input-group-prepend">' +
            '<span class="input-group-text">Foto ' + (index+1) + '</span>' +
          '</div>' +
          '<input type="text" class="form-control" ' +
            'name="legendas[]" ' +
            'placeholder="Ex: Lacre violado, manômetro abaixo do normal..." ' +
            'maxlength="255">';
        legendasContainer.appendChild(legendaDiv);
      };
      reader.readAsDataURL(file);
    })(i, files[i]);
  }
}
</script>
</body>
</html>
<?php
$conn->close();
?>
