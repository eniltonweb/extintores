<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';
include 'auditoria.php';

// Verificar se o usuário está logado e se é admin ou fornecedor
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_level'], ['admin', 'fornecedor'])) {
    header('Location: index.php');
    exit();
}

$user_level = $_SESSION['user_level'];

// Consultar todas as movimentações ATIVAS
$sqlAtivas = "
    SELECT 
        m.id, 
        m.data_movimentacao, 
        m.motivo, 
        m.local_original_substituto,
        m.novo_local_provsorio,
        m.extintor_substituto_id,
        es.codigo AS cod_substituto,
        ea.codigo AS cod_ausente,
        u.username AS usuario_nome
    FROM bd_historico_movimentacao m
    JOIN bd_extintores es ON m.extintor_substituto_id = es.id
    JOIN bd_extintores ea ON m.extintor_ausente_id = ea.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.status_movimentacao = 'Ativa'
    ORDER BY m.data_movimentacao DESC
";
$resultAtivas = $conn->query($sqlAtivas);

// Consultar todos os extintores para o formulário
// Reservas disponíveis (cobertura = 0)
$sqlReservas = "SELECT id, codigo, Local_Exato, Predio FROM bd_extintores WHERE cobertura = 0 OR cobertura IS NULL ORDER BY codigo ASC";
$resReservas = $conn->query($sqlReservas);
$reservas = [];
while ($row = $resReservas->fetch_assoc()) {
    $reservas[] = $row;
}

// Todos os extintores que podem ser cobertos
$sqlTodos = "SELECT id, codigo, Local_Exato, Predio FROM bd_extintores ORDER BY codigo ASC";
$resTodos = $conn->query($sqlTodos);
$todos = [];
while ($row = $resTodos->fetch_assoc()) {
    $todos[] = $row;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Movimentação - Michelin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">

    <?php include 'templates/header_controller.php'; ?>

    <div class="container mt-4 mb-5 fade-in">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Controle de Movimentação Física</h1>
            <button class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#modalNovaMovimentacao">
                + Nova Cobertura
            </button>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="text-michelin-blue font-weight-bold mb-0">Coberturas Ativas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="thead-michelin">
                            <tr>
                                <th>Data</th>
                                <th>Extintor Reserva (Cobrando)</th>
                                <th>Extintor Ausente (Sendo Coberto)</th>
                                <th>Local Provisório (Atual)</th>
                                <th>Local Original (Retorno)</th>
                                <th>Motivo</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultAtivas && $resultAtivas->num_rows > 0): ?>
                                <?php while ($mov = $resultAtivas->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($mov['data_movimentacao'])) ?></td>
                                        <td><strong><?= htmlspecialchars($mov['cod_substituto']) ?></strong></td>
                                        <td><span class="text-danger"><?= htmlspecialchars($mov['cod_ausente']) ?></span></td>
                                        <td><?= htmlspecialchars($mov['novo_local_provsorio']) ?></td>
                                        <td><span class="text-muted"><?= htmlspecialchars($mov['local_original_substituto']) ?></span></td>
                                        <td><?= htmlspecialchars($mov['motivo']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success encerrar-btn" data-substituto="<?= $mov['extintor_substituto_id'] ?>">
                                                Devolver Extintor
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Nenhuma movimentação/cobertura ativa no momento.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Movimentação -->
    <div class="modal fade" id="modalNovaMovimentacao" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-michelin-blue text-white">
            <h5 class="modal-title font-weight-bold">Registrar Nova Movimentação</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form id="formNovaMovimentacao">
              <div class="modal-body p-4">
                  <div class="form-group">
                      <label class="font-weight-bold">Extintor Ausente (Que precisa ser coberto)</label>
                      <select name="extintor_ausente_id" class="form-control" required>
                          <option value="">Selecione o extintor ausente...</option>
                          <?php foreach ($todos as $ext): ?>
                              <option value="<?= $ext['id'] ?>"><?= htmlspecialchars($ext['codigo']) ?> - <?= htmlspecialchars($ext['Predio']) ?> (<?= htmlspecialchars($ext['Local_Exato']) ?>)</option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="form-group mt-3">
                      <label class="font-weight-bold">Extintor Reserva (Que irá cobrir o local)</label>
                      <select name="extintor_substituto_id" class="form-control" required>
                          <option value="">Selecione um extintor reserva...</option>
                          <?php foreach ($reservas as $ext): ?>
                              <option value="<?= $ext['id'] ?>"><?= htmlspecialchars($ext['codigo']) ?> - <?= htmlspecialchars($ext['Predio']) ?> (<?= htmlspecialchars($ext['Local_Exato']) ?>)</option>
                          <?php endforeach; ?>
                      </select>
                      <small class="text-muted">Apenas extintores que não estão prestando cobertura aparecem aqui.</small>
                  </div>
                  <div class="form-group mt-3">
                      <label class="font-weight-bold">Motivo da Movimentação</label>
                      <input type="text" name="motivo" class="form-control" placeholder="Ex: Manutenção Semestral, Recarga..." required>
                  </div>
              </div>
              <div class="modal-footer border-0 pb-4 pr-4">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnSalvar">Confirmar Cobertura</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Salvar nova movimentação
            $('#formNovaMovimentacao').on('submit', function(e) {
                e.preventDefault();
                $('#btnSalvar').prop('disabled', true).text('Processando...');

                $.ajax({
                    url: 'salvar_movimentacao.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Erro: ' + response.message);
                            $('#btnSalvar').prop('disabled', false).text('Confirmar Cobertura');
                        }
                    },
                    error: function() {
                        alert('Erro de conexão com o servidor.');
                        $('#btnSalvar').prop('disabled', false).text('Confirmar Cobertura');
                    }
                });
            });

            // Encerrar movimentação
            $('.encerrar-btn').on('click', function() {
                if (!confirm('Deseja devolver este extintor ao seu local original?')) return;
                
                let btn = $(this);
                let substituto_id = btn.data('substituto');
                btn.prop('disabled', true).text('Devolvendo...');

                $.ajax({
                    url: 'encerrar_movimentacao.php',
                    type: 'POST',
                    data: { extintor_substituto_id: substituto_id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Erro: ' + response.message);
                            btn.prop('disabled', false).text('Devolver Extintor');
                        }
                    },
                    error: function() {
                        alert('Erro de conexão ao encerrar movimentação.');
                        btn.prop('disabled', false).text('Devolver Extintor');
                    }
                });
            });
        });
    </script>
</body>
</html>
