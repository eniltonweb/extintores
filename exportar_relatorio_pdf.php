<?php
/**
 * exportar_relatorio_pdf.php
 * ============================================================
 * Relatório PDF de Inspeções de Nível 1 com evidências
 * fotográficas embutidas — padrão Michelin.
 *
 * DEPENDÊNCIA: composer require mpdf/mpdf
 * Após instalar, o vendor/autoload.php já carrega o mPDF.
 *
 * ACESSO: apenas administradores
 *
 * PARÂMETROS GET (opcionais):
 *   ?predio=100         → filtrar por prédio
 *   ?de=2026-01-01      → data início
 *   ?ate=2026-05-21     → data fim
 *   ?apenas_nok=1       → apenas extintores com algum item NOK
 * ============================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M'); // Já aproveita para aumentar a memória
set_time_limit(300); // Dá mais tempo para o servidor processar as fotos


session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] !== 'admin') {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/config/db_conexao.php';
require_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/auditoria.php';

// -------------------------------------------------------
// Controle de acesso
// -------------------------------------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// -------------------------------------------------------
// Filtros
// -------------------------------------------------------
$filtro_predio   = filter_input(INPUT_GET, 'predio',    FILTER_SANITIZE_SPECIAL_CHARS);
$filtro_de       = filter_input(INPUT_GET, 'de',        FILTER_SANITIZE_SPECIAL_CHARS);
$filtro_ate      = filter_input(INPUT_GET, 'ate',       FILTER_SANITIZE_SPECIAL_CHARS);
$apenas_nok      = filter_input(INPUT_GET, 'apenas_nok',FILTER_VALIDATE_BOOLEAN);

// Defaults de data
if (empty($filtro_de))  $filtro_de  = date('Y-m-01');          // início do mês atual
if (empty($filtro_ate)) $filtro_ate = date('Y-m-d');            // hoje

$uploads_dir = __DIR__ . '/uploads/';

// -------------------------------------------------------
// Busca dos dados de inspeção
// -------------------------------------------------------
$where   = ["e.inspecao_trimestral_nivel1 IS NOT NULL"];
$params  = [];
$types   = '';

$where[] = "e.inspecao_trimestral_nivel1 BETWEEN ? AND ?";
$params[] = $filtro_de;
$params[] = $filtro_ate;
$types   .= 'ss';

if (!empty($filtro_predio)) {
    $where[] = "e.Predio = ?";
    $params[] = $filtro_predio;
    $types   .= 's';
}

$nok_fields = "e.sinalizacao_vertical, e.sinalizacao_piso, e.ficha_inspecao_trimestral,
               e.lacre, e.pressao_manometro, e.anel_identificacao";

if ($apenas_nok) {
    $where[] = "($nok_fields) LIKE '%NÃO OK%'";
    // LIKE em múltiplas colunas — simplificado com OR
    $where[count($where)-1] = "(
        e.sinalizacao_vertical     = 'NÃO OK' OR
        e.sinalizacao_piso         = 'NÃO OK' OR
        e.ficha_inspecao_trimestral= 'NÃO OK' OR
        e.lacre                    = 'NÃO OK' OR
        e.pressao_manometro        = 'NÃO OK' OR
        e.anel_identificacao       = 'NÃO OK'
    )";
}

$sql = "
    SELECT
        e.id,
        e.codigo,
        e.Predio,
        e.Local_Exato,
        e.tip_extintor,
        e.carga,
        e.inspecao_trimestral_nivel1 AS data_inspecao,
        e.usuario,
        e.selo_do_Inmetro,
        e.sinalizacao_vertical,
        e.sinalizacao_piso,
        e.ficha_inspecao_trimestral,
        e.lacre,
        e.pressao_manometro,
        e.anel_identificacao,
        e.pesagem_co2_semestral,
        e.comentarios
    FROM bd_extintores e
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.Predio, e.inspecao_trimestral_nivel1 DESC, e.codigo
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$extintores = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -------------------------------------------------------
// Para cada extintor, busca as fotos na nova tabela
// -------------------------------------------------------
function buscar_fotos(mysqli $conn, string $codigo, string $data): array
{
    $sql = "
        SELECT foto_nome, legenda, created_at
        FROM inspecao_fotos
        WHERE extintor_codigo = ?
          AND data_inspecao   = ?
        ORDER BY id ASC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $codigo, $data);
    $stmt->execute();
    $r = $stmt->get_result();
    $fotos = $r->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $fotos;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function badge_status(string $valor): string
{
    if (strtoupper($valor) === 'OK') {
        return '<span style="background:#28a745;color:#fff;padding:2px 7px;border-radius:3px;font-size:10px;font-weight:bold;">OK</span>';
    }
    if (stripos($valor, 'NÃO') !== false || strtoupper($valor) === 'NOK') {
        return '<span style="background:#dc3545;color:#fff;padding:2px 7px;border-radius:3px;font-size:10px;font-weight:bold;">NOK</span>';
    }
    return '<span style="background:#6c757d;color:#fff;padding:2px 7px;border-radius:3px;font-size:10px;font-size:10px;">—</span>';
}

function data_br(string $data): string
{
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d ? $d->format('d/m/Y') : $data;
}

// -------------------------------------------------------
// Montar HTML do relatório
// -------------------------------------------------------
$periodo_label = data_br($filtro_de) . ' a ' . data_br($filtro_ate);
$predio_label  = $filtro_predio ?: 'Todos';
$nok_label     = $apenas_nok ? ' — Apenas Não Conformes' : '';

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  body         { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
  h1           { font-size: 16px; color: #003087; margin:0; }
  h2           { font-size: 12px; color: #555; margin:0 0 4px 0; }
  .header-box  { background:#003087; color:#fff; padding:10px 14px; margin-bottom:14px; }
  .header-box p{ margin:2px 0; font-size:9px; color:#cce0ff; }
  .extintor-card {
    border: 1px solid #ccc;
    border-radius: 4px;
    margin-bottom: 18px;
    page-break-inside: avoid;
  }
  .extintor-header {
    background: #003087;
    color: #fff;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: bold;
  }
  .extintor-header .badge-tipo {
    background:#ffc107;color:#000;padding:2px 6px;border-radius:3px;
    font-size:9px;font-weight:bold;margin-left:8px;
  }
  .extintor-body { padding: 8px 10px; }
  table.checklist {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    margin-bottom: 8px;
  }
  table.checklist th {
    background: #e8edf4;
    color: #003087;
    padding: 4px 6px;
    text-align: left;
    border: 1px solid #c8d0dc;
  }
  table.checklist td {
    padding: 4px 6px;
    border: 1px solid #ddd;
    vertical-align: middle;
  }
  table.checklist tr:nth-child(even) td { background: #f9f9f9; }
  .comentario-box {
    background: #fffbe6;
    border-left: 3px solid #ffc107;
    padding: 5px 8px;
    font-size: 9px;
    margin-bottom: 8px;
    border-radius: 2px;
  }
  .foto-grid {
    display: table;
    width: 100%;
  }
  .foto-cell {
    display: table-cell;
    width: 25%;
    padding: 3px;
    text-align: center;
    vertical-align: top;
  }
  .foto-cell img {
    max-width: 130px;
    max-height: 100px;
    border: 1px solid #ccc;
    border-radius: 3px;
  }
  .foto-cell .legenda {
    font-size: 7.5px;
    color: #666;
    margin-top: 2px;
    word-wrap: break-word;
  }
  .sem-foto {
    color: #999;
    font-size: 9px;
    font-style: italic;
  }
  .page-footer {
    font-size: 8px;
    color: #999;
    text-align: right;
    border-top: 1px solid #eee;
    padding-top: 4px;
    margin-top: 10px;
  }
  .alerta { color: #dc3545; font-weight: bold; }
  .resumo-bar {
    background:#f4f6fa;
    border:1px solid #d0d7e5;
    border-radius:4px;
    padding:8px 12px;
    margin-bottom:14px;
    font-size:9px;
  }
  .resumo-bar span { margin-right: 16px; }
</style>
</head>
<body>

<!-- CABEÇALHO -->
<div class="header-box">
  <table style="width: 100%; border: none; margin: 0; padding: 0;">
    <tr>
      <td style="width: 15%; vertical-align: middle; text-align: center; border: none; padding-right: 15px;">
        <img src="https://enilton.com.br/extintores2/img/michelin_logo.png" alt="Michelin" style="max-height: 45px; background: #ffffff; padding: 6px; border-radius: 4px;">
      </td>
      
      <td style="width: 85%; vertical-align: middle; text-align: left; border: none;">
        <h1> Relatório de Inspeção de Nível 1 — Extintores</h1>
        <p>Período: <strong><?= htmlspecialchars($periodo_label) ?></strong>
           &nbsp;|&nbsp; Prédio: <strong><?= htmlspecialchars($predio_label) ?></strong>
           <?= $nok_label ? '&nbsp;|&nbsp; <strong>' . htmlspecialchars($nok_label) . '</strong>' : '' ?>
        </p>
        <p>Gerado em: <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp; Total de registros: <strong><?= count($extintores) ?></strong></p>
      </td>
    </tr>
  </table>
</div>

<?php if (empty($extintores)): ?>
  <p style="color:#888;text-align:center;padding:30px 0;">
    Nenhuma inspeção encontrada para os filtros selecionados.
  </p>
<?php else: ?>

  <?php
  // Calcular resumo
  $total_ok  = 0;
  $total_nok = 0;
  $total_fotos_geral = 0;
  foreach ($extintores as $e) {
      $campos_check = [
          $e['sinalizacao_vertical'], $e['sinalizacao_piso'],
          $e['ficha_inspecao_trimestral'], $e['lacre'],
          $e['pressao_manometro'], $e['anel_identificacao'],
      ];
      $tem_nok = false;
      foreach ($campos_check as $v) {
          if (stripos((string)$v, 'NÃO') !== false) $tem_nok = true;
      }
      if ($tem_nok) $total_nok++; else $total_ok++;
  }
  ?>

  <!-- BARRA DE RESUMO -->
  <div class="resumo-bar">
  <span>&#10004; Conformes: <strong><?= $total_ok ?></strong></span>
  <span>&#10006; Não conformes: <strong style="color:#dc3545;"><?= $total_nok ?></strong></span>
  <span>Total inspecionado: <strong><?= count($extintores) ?></strong></span>
</div>

  <?php foreach ($extintores as $ext):
    $fotos = buscar_fotos($conn, $ext['codigo'], $ext['data_inspecao']);

    // Verificar se há algum NOK
    $campos_nok = [
        $ext['sinalizacao_vertical'], $ext['sinalizacao_piso'],
        $ext['ficha_inspecao_trimestral'], $ext['lacre'],
        $ext['pressao_manometro'], $ext['anel_identificacao'],
    ];
    $tem_nok = false;
    foreach ($campos_nok as $v) {
        if (stripos((string)$v, 'NÃO') !== false) $tem_nok = true;
    }
  ?>

  <div class="extintor-card">

    <!-- Cabeçalho do extintor -->
    <div class="extintor-header" <?= $tem_nok ? 'style="background:#c0392b;"' : '' ?>>
  <?= $tem_nok ? '&#9888; ' : '&#10004; ' ?>
      Extintor: <strong><?= htmlspecialchars($ext['codigo']) ?></strong>
      &nbsp;—&nbsp;
      Prédio <?= htmlspecialchars($ext['Predio']) ?>
      &nbsp;—&nbsp;
      <?= htmlspecialchars($ext['Local_Exato']) ?>
      <span class="badge-tipo"><?= htmlspecialchars($ext['tip_extintor']) ?> / <?= htmlspecialchars($ext['carga']) ?></span>
      <span style="float:right;font-size:9px;font-weight:normal;">
        Inspeção: <?= data_br($ext['data_inspecao']) ?>
        &nbsp;|&nbsp; Bombeiro: <?= htmlspecialchars($ext['usuario'] ?: '—') ?>
      </span>
    </div>

    <div class="extintor-body">

      <!-- Checklist -->
      <table class="checklist">
        <tr>
          <th>Item verificado</th>
          <th style="width:80px;">Resultado</th>
          <th>Item verificado</th>
          <th style="width:80px;">Resultado</th>
        </tr>
        <tr>
          <td>Sinalização Vertical</td>
          <td><?= badge_status((string)$ext['sinalizacao_vertical']) ?></td>
          <td>Sinalização no Piso</td>
          <td><?= badge_status((string)$ext['sinalizacao_piso']) ?></td>
        </tr>
        <tr>
          <td>Ficha de Inspeção Trimestral</td>
          <td><?= badge_status((string)$ext['ficha_inspecao_trimestral']) ?></td>
          <td>Lacre</td>
          <td><?= badge_status((string)$ext['lacre']) ?></td>
        </tr>
        <tr>
          <td>Pressão do Manômetro</td>
          <td><?= badge_status((string)$ext['pressao_manometro']) ?></td>
          <td>Anel de Identificação</td>
          <td><?= badge_status((string)$ext['anel_identificacao']) ?></td>
        </tr>
        <?php if ($ext['tip_extintor'] === 'CO2'): ?>
        <tr>
          <td>Pesagem CO₂ Semestral</td>
          <td colspan="3"><?= badge_status((string)$ext['pesagem_co2_semestral']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td>Selo INMETRO</td>
          <td colspan="3"><?= htmlspecialchars((string)$ext['selo_do_Inmetro'] ?: '—') ?></td>
        </tr>
      </table>

      <!-- Comentários -->
      <?php if (!empty($ext['comentarios'])): ?>
        <div class="comentario-box">
          <?php if (strpos($ext['comentarios'], 'ALERTA GRAVE') !== false): ?>
  <span class="alerta">&#9888; </span>
<?php endif; ?>
          <strong>Observações:</strong> <?= nl2br(htmlspecialchars($ext['comentarios'])) ?>
        </div>
      <?php endif; ?>

      <!-- Fotos / Evidências -->
      <div style="font-size:9px;font-weight:bold;color:#003087;margin-bottom:4px;">
  Evidências Fotográficas
        <?= !empty($fotos) ? '(' . count($fotos) . ' foto' . (count($fotos) > 1 ? 's' : '') . ')' : '' ?>
      </div>

      <?php if (empty($fotos)): ?>
        <p class="sem-foto">Nenhuma foto registrada nesta inspeção.</p>
      <?php else: ?>
        <div class="foto-grid">
          <?php foreach ($fotos as $foto):
            $path_foto = $uploads_dir . basename($foto['foto_nome']);
            $img_src   = null;
            if (file_exists($path_foto)) {
                $img_ext = pathinfo($path_foto, PATHINFO_EXTENSION);
                $img_data = base64_encode(file_get_contents($path_foto));
                $img_src = 'data:image/' . $img_ext . ';base64,' . $img_data;
            }
          ?>
          <div class="foto-cell">
            <?php if ($img_src): ?>
              <img src="<?= htmlspecialchars($img_src) ?>"
                   alt="Evidência fotográfica"
                   onerror="this.style.display='none'">
            <?php else: ?>
              <div style="width:120px;height:90px;background:#eee;display:inline-block;
                           line-height:90px;text-align:center;color:#aaa;font-size:8px;">
                Arquivo não encontrado
              </div>
            <?php endif; ?>
            <div class="legenda">
              <?= htmlspecialchars($foto['legenda'] ?: basename($foto['foto_nome'])) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div><!-- /extintor-body -->
  </div><!-- /extintor-card -->

  <?php endforeach; ?>
<?php endif; ?>

<div class="page-footer">
  Sistema de Controle de Extintores — Michelin &nbsp;|&nbsp;
  Relatório gerado por <?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?>
  em <?= date('d/m/Y H:i:s') ?>
</div>
<footer class="footer mt-4">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>
<?php
$html_content = ob_get_clean();

// -------------------------------------------------------
// Gerar PDF com mPDF
// -------------------------------------------------------
$mpdf = new \Mpdf\Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'margin_top'        => 12,
    'margin_right'      => 10,
    'margin_bottom'     => 15,
    'margin_left'       => 10,
    'margin_header'     => 8,
    'margin_footer'     => 8,
    'tempDir'           => __DIR__ . '/cache',
]);

// Permitir imagens do filesystem local (essencial para as fotos)
$mpdf->setBasePath(__DIR__ . '/');
$mpdf->allow_output_buffering = true;

// Rodapé com número de página
$mpdf->SetFooter(
    '<table width="100%"><tr>' .
    '<td style="font-size:8px;color:#999;">Michelin — Inspeção de Extintores</td>' .
    '<td style="font-size:8px;color:#999;text-align:right;">Página {PAGENO} de {nbpg}</td>' .
    '</tr></table>'
);

$mpdf->WriteHTML($html_content);

// Nome do arquivo para download
$predio_slug = $filtro_predio ? '_P' . $filtro_predio : '';
$filename = 'relatorio_inspecao' . $predio_slug . '_' . date('Y-m-d') . '.pdf';

// Auditoria
registrar_auditoria(
    $conn,
    $_SESSION['user_id'],
    'Exportação PDF de inspeções',
    "PDF gerado: período $filtro_de a $filtro_ate, prédio: " . ($filtro_predio ?: 'todos')
);

$conn->close();

// Enviar PDF para o browser
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit();
