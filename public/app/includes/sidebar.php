<?php
$pagina_atual = $_SERVER['PHP_SELF'];
require_once dirname(__DIR__) . '/core/access_control.php';
$perfilAtual = (string)($_SESSION['usuario_perfil'] ?? '');
$acessos = modulosPorPerfil($perfilAtual);
$tabAtual = trim((string)($_GET['tab'] ?? ''));
$viewAtual = trim((string)($_GET['view'] ?? ''));
$appActionFeedback = null;
if (isset($_SESSION['app_action_feedback']) && is_array($_SESSION['app_action_feedback'])) {
    $appActionFeedback = $_SESSION['app_action_feedback'];
    unset($_SESSION['app_action_feedback']);
}
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
                <a href="/vilcon-systemon/public/app/modules/transporte/index.php?tab=aluguer&view=modulo&mode=list"
                   class="nav-link sub <?= (strpos($pagina_atual, '/transporte/') !== false && ($tabAtual === 'aluguer_equipamentos' || $tabAtual === 'aluguer')) ? 'active' : '' ?>">
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
<div class="app-action-feedback-overlay" id="appActionFeedbackOverlay" aria-hidden="true">
    <div class="app-action-feedback-card">
        <div class="app-action-feedback-icon" id="appActionFeedbackIcon"></div>
        <h4 class="app-action-feedback-title" id="appActionFeedbackTitle">Sucesso</h4>
        <p class="app-action-feedback-text" id="appActionFeedbackText"></p>
        <div class="app-action-feedback-actions" id="appActionFeedbackActions">
            <button type="button" class="app-action-feedback-btn" id="appActionFeedbackClose">Fechar</button>
        </div>
    </div>
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

/* ===== GLOBAL ACTION FEEDBACK ===== */
.app-action-feedback-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.48);
    z-index: 2500;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.app-action-feedback-overlay.open { display: flex; }
.app-action-feedback-card {
    width: min(420px, 95vw);
    border-radius: 14px;
    border: 1px solid #dbe2ea;
    background: #fff;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28);
    padding: 20px 18px;
    text-align: center;
}
.app-action-feedback-icon {
    width: 126px;
    height: 126px;
    margin: 0 auto 14px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 58px;
    font-weight: 800;
    color: #fff;
    transform: scale(0.82);
    opacity: 0;
    animation: appFeedbackPop .36s ease forwards;
}
.app-action-feedback-icon.success {
    background: #22c55e;
    box-shadow: 0 0 0 10px rgba(34, 197, 94, 0.12);
}
.app-action-feedback-icon.error {
    background: #ef4444;
    box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.12);
}
.app-action-feedback-title {
    margin: 0 0 6px 0;
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
}
.app-action-feedback-text {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}
.app-action-feedback-actions {
    margin-top: 14px;
    display: flex;
    justify-content: center;
}
.app-action-feedback-btn {
    border: none;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    background: #0f172a;
    cursor: pointer;
}
@keyframes appFeedbackPop {
    from { transform: scale(0.82); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>
<script>
;(function() {
    var overlay = document.getElementById('appActionFeedbackOverlay');
    var icon = document.getElementById('appActionFeedbackIcon');
    var title = document.getElementById('appActionFeedbackTitle');
    var text = document.getElementById('appActionFeedbackText');
    var actions = document.getElementById('appActionFeedbackActions');
    var closeBtn = document.getElementById('appActionFeedbackClose');
    if (!overlay || !icon || !title || !text || !actions || !closeBtn) return;

    function closeOverlay() {
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    function showFeedback(opts) {
        opts = opts || {};
        var type = String(opts.type || 'success').toLowerCase() === 'error' ? 'error' : 'success';
        var ttl = String(opts.title || (type === 'success' ? 'Operacao concluida' : 'Falha na operacao'));
        var msg = String(opts.message || '');
        var autoCloseMs = Number(opts.autoCloseMs || 0);
        var redirectUrl = String(opts.redirectUrl || '');

        icon.className = 'app-action-feedback-icon ' + type;
        icon.innerHTML = type === 'success' ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>';
        title.textContent = ttl;
        text.textContent = msg;
        actions.style.display = autoCloseMs > 0 ? 'none' : 'flex';

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');

        if (autoCloseMs > 0) {
            window.setTimeout(function() {
                closeOverlay();
                if (redirectUrl) window.location.href = redirectUrl;
            }, autoCloseMs);
        }
    }

    closeBtn.addEventListener('click', closeOverlay);
    overlay.addEventListener('click', function(ev) {
        if (ev.target === overlay) closeOverlay();
    });

    window.vilconActionFeedback = { show: showFeedback, close: closeOverlay };

    var initial = <?= json_encode($appActionFeedback ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (initial && typeof initial === 'object') {
        showFeedback({
            type: initial.type || 'success',
            title: initial.title || '',
            message: initial.message || '',
            autoCloseMs: initial.auto_close_ms || 0,
            redirectUrl: initial.redirect || ''
        });
    }
})();
</script>
