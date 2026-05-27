<?php
session_start();
require_once __DIR__ . '/config/db_conexao.php';

// Apenas Admin pode acessar
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 'admin') {
    header('Location: index.php');
    exit();
}

$predios = [];
$res_predios = $conn->query("SELECT DISTINCT Predio FROM bd_extintores WHERE Predio IS NOT NULL AND Predio != '' ORDER BY Predio");
if ($res_predios) {
    while ($row = $res_predios->fetch_assoc()) {
        $predios[] = $row['Predio'];
    }
}

// Filtro
$filtro_predio = $_GET['predio'] ?? '';

$sql = "SELECT codigo, Predio, Local_Exato, tip_extintor FROM bd_extintores WHERE 1=1";
$params = [];
$types = "";

if ($filtro_predio !== '') {
    $sql .= " AND Predio = ?";
    $params[] = $filtro_predio;
    $types .= "s";
}

$sql .= " ORDER BY Predio, codigo";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($types)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$extintores = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $extintores[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Gerador de Etiquetas QR Code</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    
    <!-- Biblioteca geradora de QR Code -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        /* Estilos do Modo Tela */
        .tela-painel { display: block; }
        .print-painel { display: none; }
        
        .list-group-item.selected {
            background-color: #e9f0fc;
            border-color: #27509b;
        }

        /* Estilos do Modo Impressão */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm; /* Margem da folha */
            }
            body { background: #fff; margin: 0; padding: 0; }
            .tela-painel, nav, footer, .navbar { display: none !important; }
            .print-painel { 
                display: flex !important; 
                flex-wrap: wrap; 
                justify-content: flex-start;
                gap: 10px; /* Espaçamento entre os adesivos */
            }
            
            /* O tamanho da etiqueta adesiva (Aproximadamente 3 colunas por A4) */
            .etiqueta {
                width: 6cm;
                height: 4.5cm;
                border: 1px dashed #ccc;
                padding: 10px;
                box-sizing: border-box;
                display: flex;
                flex-direction: row;
                align-items: center;
                page-break-inside: avoid;
                background: #fff;
                overflow: hidden;
            }

            .etiqueta-esq {
                width: 55%;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .etiqueta-esq .qrcode-container img {
                width: 100%;
                height: auto;
                image-rendering: crisp-edges;
            }

            .etiqueta-dir {
                width: 45%;
                padding-left: 10px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .etiqueta-logo { max-width: 60px; margin-bottom: 5px; filter: grayscale(100%); }
            .etiqueta-codigo { font-size: 14px; font-weight: 900; color: #000; margin-bottom: 2px; }
            .etiqueta-tipo { font-size: 10px; font-weight: bold; color: #333; margin-bottom: 2px; }
            .etiqueta-local { font-size: 9px; color: #555; line-height: 1.1; }
        }
    </style>
</head>
<body>
<?php include 'templates/header_controller.php'; ?>

<!-- TELA NORMAL (UI de Seleção) -->
<div class="container mt-4 tela-painel">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h3 style="color: var(--michelin-blue-dark); font-weight: bold;">
                <i class="fas fa-qrcode mr-2"></i> Gerador de Etiquetas QR Code
            </h3>
            <p class="text-muted mb-0">Selecione os extintores que deseja gerar a etiqueta adesiva para colar nos equipamentos.</p>
        </div>
    </div>

    <div class="row">
        <!-- Filtros e Ações -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-light font-weight-bold">Filtros e Configurações</div>
                <div class="card-body">
                    <form method="GET" action="gerar_etiquetas.php">
                        <div class="form-group">
                            <label>Filtrar por Prédio:</label>
                            <select name="predio" class="form-control" onchange="this.form.submit()">
                                <option value="">Todos os Prédios</option>
                                <?php foreach ($predios as $p): ?>
                                    <option value="<?= htmlspecialchars($p) ?>" <?= $filtro_predio === $p ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <hr>
                    <button class="btn btn-outline-secondary w-100 mb-2" onclick="selecionarTodos(true)">Marcar Todos</button>
                    <button class="btn btn-outline-secondary w-100 mb-4" onclick="selecionarTodos(false)">Desmarcar Todos</button>
                    
                    <button class="btn btn-primary w-100 btn-lg font-weight-bold" onclick="prepararImpressao()">
                        <i class="fas fa-print mr-2"></i> Imprimir Selecionados
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de Extintores -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">Extintores Encontrados (<?= count($extintores) ?>)</span>
                    <span id="contadorSelecionados" class="badge badge-primary">0 selecionados</span>
                </div>
                <ul class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;" id="listaExtintores">
                    <?php if (empty($extintores)): ?>
                        <li class="list-group-item text-center text-muted">Nenhum extintor encontrado.</li>
                    <?php endif; ?>
                    
                    <?php foreach ($extintores as $ext): 
                        $codigo = htmlspecialchars($ext['codigo']);
                        $predio = htmlspecialchars($ext['Predio']);
                        $local = htmlspecialchars($ext['Local_Exato']);
                        $tipo = htmlspecialchars($ext['tip_extintor']);
                        $texto_busca = strtolower("$codigo $predio $local $tipo");
                    ?>
                    <li class="list-group-item list-group-item-action d-flex align-items-center extintor-item" data-codigo="<?= $codigo ?>">
                        <div class="custom-control custom-checkbox mr-3">
                            <input type="checkbox" class="custom-control-input cb-extintor" id="cb_<?= $codigo ?>" value="<?= $codigo ?>" 
                                   data-predio="<?= $predio ?>" data-local="<?= $local ?>" data-tipo="<?= $tipo ?>" onchange="atualizarContador()">
                            <label class="custom-control-label" for="cb_<?= $codigo ?>"></label>
                        </div>
                        <div style="flex-grow: 1; cursor: pointer;" onclick="toggleCheckbox('cb_<?= $codigo ?>')">
                            <strong><?= $codigo ?></strong> <span class="badge badge-secondary ml-2"><?= $tipo ?></span>
                            <div class="text-muted small">Prédio: <?= $predio ?> - <?= $local ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- TELA DE IMPRESSÃO (GRID DE ETIQUETAS) -->
<div class="print-painel" id="printArea">
    <!-- As etiquetas serão injetadas aqui via JS -->
</div>

<script>
    const baseUrl = 'https://enilton.com.br/extintores2/codigobarras.php?codigo=';

    function selecionarTodos(selecionar) {
        $('.cb-extintor').prop('checked', selecionar);
        atualizarContador();
    }

    function toggleCheckbox(id) {
        const cb = document.getElementById(id);
        cb.checked = !cb.checked;
        atualizarContador();
    }

    function atualizarContador() {
        const selecionados = $('.cb-extintor:checked').length;
        $('#contadorSelecionados').text(selecionados + ' selecionados');
        
        $('.extintor-item').removeClass('selected');
        $('.cb-extintor:checked').closest('.extintor-item').addClass('selected');
    }

    function prepararImpressao() {
        const selecionados = $('.cb-extintor:checked');
        if (selecionados.length === 0) {
            alert('Selecione pelo menos um extintor para imprimir a etiqueta.');
            return;
        }

        const printArea = document.getElementById('printArea');
        printArea.innerHTML = ''; // Limpa a grade de impressão

        let qrcodePromises = [];

        selecionados.each(function() {
            const codigo = $(this).val();
            const predio = $(this).data('predio');
            const local = $(this).data('local');
            const tipo = $(this).data('tipo');
            const linkAcesso = baseUrl + encodeURIComponent(codigo);

            // Criar o container da etiqueta
            const etiquetaDiv = document.createElement('div');
            etiquetaDiv.className = 'etiqueta';
            
            // Coluna Esquerda (QR)
            const esqDiv = document.createElement('div');
            esqDiv.className = 'etiqueta-esq';
            const qrContainer = document.createElement('div');
            qrContainer.className = 'qrcode-container';
            esqDiv.appendChild(qrContainer);

            // Coluna Direita (Texto)
            const dirDiv = document.createElement('div');
            dirDiv.className = 'etiqueta-dir';
            dirDiv.innerHTML = `
                <img src="img/michelin_logo.png" class="etiqueta-logo" alt="Michelin">
                <div class="etiqueta-codigo">${codigo}</div>
                <div class="etiqueta-tipo">${tipo}</div>
                <div class="etiqueta-local">P: ${predio}<br>${local}</div>
            `;

            etiquetaDiv.appendChild(esqDiv);
            etiquetaDiv.appendChild(dirDiv);
            printArea.appendChild(etiquetaDiv);

            // Gerar o QR Code e aguardar a renderização (QRCode.js é síncrono, mas DOM update pode levar ms)
            new QRCode(qrContainer, {
                text: linkAcesso,
                width: 150,
                height: 150,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });

        // Aguardar um pequeno tempo para garantir que todas as tags <canvas>/<img> foram desenhadas
        setTimeout(() => {
            window.print();
        }, 500);
    }
</script>

<footer class="footer mt-4 tela-painel">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Sistema de Controle de Extintores</p>
    </div>
</footer>
</body>
</html>
