<?php
$pagina_atual = $_SERVER['PHP_SELF'];
require_once dirname(__DIR__) . '/core/access_control.php';
$perfilAtual = (string)($_SESSION['usuario_perfil'] ?? '');
$acessos = modulosPorPerfil($perfilAtual);
$tabAtual = trim((string)($_GET['tab'] ?? ''));
$viewAtual = trim((string)($_GET['view'] ?? ''));
?>

<div class="sidebar">

    <!-- HEADER -->
    <div class="sidebar-header">
        <img src="/vilcon-systemon/public/assets/img/logo-vilcon.png" alt="Vilcon Logo" style="width:200px;">
        <h2>Sistema Integrado</h2>
    </div>

    <!-- MENU -->
    <div class="nav-menu">

        <!-- DASHBOARD ROOT -->
        <div class="menu-section">
            <?php if (in_array('transporte', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/transporte/index.php?tab=hse&view=checklist&mode=list"
                   class="nav-link sub <?= (strpos($pagina_atual, '/transporte/') !== false && $tabAtual === 'hse' && $viewAtual === 'checklist') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-check"></i> HSE
                </a>
            <?php endif; ?>

            <?php if (in_array('dashboard', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/dashboard/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/dashboard/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard & BI
                </a>
            <?php endif; ?>

            <?php if (in_array('documental', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/documental/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/documental/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-folder-tree"></i> Gestão Documental
                </a>
            <?php endif; ?>

            <?php if (in_array('oficina', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/oficina/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/oficina/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Oficina
                </a>
            <?php endif; ?>

            <?php if (in_array('transporte', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/transporte/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/transporte/') !== false && $tabAtual === '') ? 'active' : '' ?>">
                    <i class="fa-solid fa-truck-ramp-box"></i> Transporte
                </a>
                <a href="/vilcon-systemon/public/app/modules/transporte/index.php?tab=gestao_frota"
                   class="nav-link sub <?= (strpos($pagina_atual, '/transporte/') !== false && $tabAtual === 'gestao_frota') ? 'active' : '' ?>">
                    <i class="fa-solid fa-truck"></i> Gestão de Frota
                </a>
                <a href="/vilcon-systemon/public/app/modules/transporte/index.php?tab=aluguer_equipamentos"
                   class="nav-link sub <?= (strpos($pagina_atual, '/transporte/') !== false && $tabAtual === 'aluguer_equipamentos') ? 'active' : '' ?>">
                    <i class="fa-solid fa-warehouse"></i> Aluguer de Equipamentos
                </a>
                <a href="/vilcon-systemon/public/app/modules/transporte/index.php?tab=frentista"
                   class="nav-link sub <?= (strpos($pagina_atual, '/transporte/') !== false && $tabAtual === 'frentista') ? 'active' : '' ?>">
                    <i class="fa-solid fa-gas-pump"></i> Frentista
                </a>
            <?php endif; ?>

            <?php if (in_array('rh', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/rh/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/rh/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i> RH
                </a>
            <?php endif; ?>

            <?php if (in_array('seguranca', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/seguranca/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/seguranca/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-shield-halved"></i> Seguranca
                </a>
            <?php endif; ?>

            <?php if (in_array('logistica', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/logistica/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/logistica/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-boxes-packing"></i> Logística
                </a>
            <?php endif; ?>

            <?php if (in_array('aprovacoes', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/aprovacoes/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/aprovacoes/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-circle-check"></i> Aprovações
                </a>
            <?php endif; ?>

            <?php if (in_array('relatorios', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/relatorios/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/relatorios/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i> Relatórios & BI
                </a>
            <?php endif; ?>

            <?php if (in_array('utilizadores', $acessos, true)): ?>
                <a href="/vilcon-systemon/public/app/modules/utilizadores/index.php"
                   class="nav-link sub <?= (strpos($pagina_atual, '/utilizadores/') !== false) ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-shield"></i> Utilizadores
                </a>
            <?php endif; ?>

        </div>
    </div>

   <a href="/vilcon-systemon/public/logout.php" class="btn-sair">
    <i class="fas fa-power-off"></i> SAIR
</a>


</div>
<style>
/* ===== SIDEBAR BASE ===== */
.sidebar {
    width: 280px;
    background: #1a1a1a;
    height: 100vh;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #2c2c2c;
    font-family: 'Inter', 'Segoe UI', sans-serif;
}

/* ===== HEADER ===== */
.sidebar-header {
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid #2c2c2c;
}

.sidebar-header h2 {
    font-size: 11px;
    color: #f39c12;
    letter-spacing: 2px;
    font-weight: 700;
    text-transform: uppercase;
    margin-top: 10px;
}

/* ===== MENU ===== */
.nav-menu {
    flex: 1;
    padding-top: 15px;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.nav-menu::-webkit-scrollbar {
    width: 0;
    height: 0;
}

/* ===== DASHBOARD ROOT ===== */
.menu-section {
    margin-bottom: 10px;
}

.menu-root {
    padding: 14px 25px;
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: #111;
    border-left: 4px solid #f39c12;
}

.menu-root i {
    color: #f39c12;
    font-size: 16px;
}

/* ===== SUB LINKS ===== */
.nav-link {
    padding: 12px 25px;
    color: #b3b3b3;
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 13px;
    border-left: 4px solid transparent;
    transition: all 0.25s ease;
}

/* indentação estilo árvore */
.nav-link.sub {
    padding-left: 45px;
    font-size: 13px;
}

.nav-link i {
    margin-right: 15px;
    color: #f39c12;
    width: 20px;
    text-align: center;
    font-size: 15px;
}

.nav-link:hover {
    background: #252525;
    color: #ffffff;
    padding-left: 55px;
}

.nav-link.active {
    background: rgba(243, 156, 18, 0.12);
    color: #ffffff;
    border-left-color: #f39c12;
}

/* ===== FOOTER ===== */
.sidebar-footer {
    border-top: 1px solid #2c2c2c;
    padding: 15px;
}

.btn-sair {
    background: #c0392b;
    color: white;
    padding: 12px;
    border-radius: 6px;
    text-decoration: none;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    font-weight: bold;
    transition: 0.3s;
}

.btn-sair:hover {
    background: #e74c3c;
    transform: scale(1.02);
}
</style>
