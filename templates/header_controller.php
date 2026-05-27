<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_level = $_SESSION['user_level'] ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <a class="navbar-brand" href="index.php">
        <img src="img/michelin_logo.png" height="30" class="d-inline-block align-top mr-2" style="filter: brightness(0) invert(1);" alt="Logo">
        Controle de Extintores
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="index.php">Inicio</a>
            </li>
            
            <?php if ($user_level === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarGestao" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Gestão</a>
                    <div class="dropdown-menu shadow-sm" aria-labelledby="navbarGestao">
                        <a class="dropdown-item" href="extintores.php">Lista de Extintores</a>
                        <a class="dropdown-item" href="liberar_manutencao.php">Liberar Extintores</a>
                        <a class="dropdown-item" href="aprovar_extintores.php">Aprovar Extintores</a>
                        <a class="dropdown-item" href="controle_movimentacao.php">Movimentações</a>
                        <a class="dropdown-item" href="controle_pesagem.php">Pesagem de CO₂</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarRelatorios" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Relatórios</a>
                    <div class="dropdown-menu shadow-sm" aria-labelledby="navbarRelatorios">
                        <a class="dropdown-item" href="historico_inspecao.php">Histórico Inspeções</a>
                        <a class="dropdown-item" href="historico_manutencao.php">Histórico Manutenções</a>
                        <a class="dropdown-item" href="filtro_vencimento.php">Vencimento Extintores</a>
                        <a class="dropdown-item" href="auditoria_logs.php">Log de Auditoria</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarExportacao" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Exportação</a>
                    <div class="dropdown-menu shadow-sm" aria-labelledby="navbarExportacao">
                        <a class="dropdown-item" href="exportar_dados.php">Exportar Dados (Excel)</a>
                        <a class="dropdown-item" href="exportar_relatorio_pdf.php">Gerar PDF</a>
                        <a class="dropdown-item" href="gerar_etiquetas.php">Gerar Etiquetas QR Code</a>
                        <a class="dropdown-item" href="codigobarras.php">Leitor de QR Code</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarSistema" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Sistema</a>
                    <div class="dropdown-menu shadow-sm" aria-labelledby="navbarSistema">
                        <a class="dropdown-item" href="dashboard.php">Dashboard</a>
                        <a class="dropdown-item" href="registrar_usuario.php">Gerenciar Usuários</a>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($user_level === 'bombeiro'): ?>
                <li class="nav-item"><a class="nav-link" href="formulario_inspecao.php">Inspeção Nível 1</a></li>
                <li class="nav-item"><a class="nav-link" href="novo_extintor.php">Novo Extintor</a></li>
                <li class="nav-item"><a class="nav-link" href="controle_pesagem.php">Pesagem de CO₂</a></li>
                <li class="nav-item"><a class="nav-link" href="codigobarras.php">Código de Barras</a></li>
            <?php endif; ?>

            <?php if ($user_level === 'fornecedor'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarFornecedor" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Gestão Fornecedor</a>
                    <div class="dropdown-menu shadow-sm" aria-labelledby="navbarFornecedor">
                        <a class="dropdown-item" href="extintores.php">Lista de Extintores</a>
                        <a class="dropdown-item" href="formulario_manutencao.php">Manutenção Nível 2</a>
                        <a class="dropdown-item" href="controle_pesagem.php">Pesagem de CO₂</a>
                        <a class="dropdown-item" href="controle_movimentacao.php">Movimentações</a>
                        <a class="dropdown-item" href="filtro_vencimento.php">Vencimento Extintores</a>
                        <a class="dropdown-item" href="codigobarras.php">Código de Barras</a>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="sair.php">Sair</a>
            </li>
        </ul>
    </div>
</nav>
