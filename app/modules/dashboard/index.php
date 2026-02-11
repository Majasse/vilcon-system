<?php
$page_title = 'Dashboard | Vilcon System';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="top-bar">
        <h2>Dashboard EstratÃ©gico</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= $_SESSION['usuario_nome'] ?? 'Utilizador'; ?></strong>
        </div>
    </div>

    <div class="dashboard-container">
        <style>
            body { background: #f4f7f6; color: #111827; }
            .main-content { background: #f4f7f6; }
            .top-bar { background: #ffffff; border-bottom: 1px solid #e5e7eb; }
            .user-info { color: #6b7280; }
            .user-info strong { color: #111827; }
            .kpi-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; }
            .card {
                background:#ffffff;
                border:1px solid #e5e7eb;
                border-radius:12px;
                padding:16px;
                box-shadow:0 6px 16px rgba(17,24,39,0.08);
            }
            .kpi-title { font-size:11px; text-transform:uppercase; color:#6b7280; letter-spacing:.5px; }
            .kpi-value { font-size:22px; font-weight:800; margin-top:6px; }
            .kpi-sub { font-size:11px; color:#10b981; margin-top:4px; }
            .section { margin-top:18px; display:grid; grid-template-columns: 2fr 1fr; gap:16px; }
            .chart { display:flex; align-items:flex-end; gap:8px; height:140px; }
            .bar { width:14%; background:#f59e0b; border-radius:6px 6px 0 0; }
            .bar:nth-child(2) { background:#60a5fa; }
            .bar:nth-child(3) { background:#34d399; }
            .bar:nth-child(4) { background:#f87171; }
            .bar:nth-child(5) { background:#a78bfa; }
            .legend { display:flex; gap:10px; margin-top:8px; font-size:11px; color:#6b7280; flex-wrap:wrap; }
            .legend span { display:flex; align-items:center; gap:6px; }
            .dot { width:8px; height:8px; border-radius:999px; display:inline-block; }
            .list { display:flex; flex-direction:column; gap:10px; }
            .list-item { display:flex; justify-content:space-between; font-size:12px; color:#374151; }
            .progress { height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin-top:6px; }
            .progress > span { display:block; height:100%; background:#111827; }
            @media (max-width: 1100px) {
                .kpi-grid { grid-template-columns: repeat(2, 1fr); }
                .section { grid-template-columns: 1fr; }
            }
        </style>

        <div class="kpi-grid">
            <div class="card">
                <div class="kpi-title">Ordens de ServiÃ§o Abertas</div>
                <div class="kpi-value">38</div>
                <div class="kpi-sub">+6 esta semana</div>
            </div>
            <div class="card">
                <div class="kpi-title">Viaturas DisponÃ­veis</div>
                <div class="kpi-value">27</div>
                <div class="kpi-sub">92% de disponibilidade</div>
            </div>
            <div class="card">
                <div class="kpi-title">Custos Mensais (MZN)</div>
                <div class="kpi-value">2.48M</div>
                <div class="kpi-sub">-4% vs mÃªs anterior</div>
            </div>
            <div class="card">
                <div class="kpi-title">Itens em Stock CrÃ­tico</div>
                <div class="kpi-value">6</div>
                <div class="kpi-sub">Repor atÃ© 7 dias</div>
            </div>
        </div>

        <div class="section">
            <div class="card">
                <div class="kpi-title">ProduÃ§Ã£o Semanal (OS concluÃ­das)</div>
                <div class="chart" aria-label="ProduÃ§Ã£o semanal">
                    <div class="bar" style="height:55%"></div>
                    <div class="bar" style="height:80%"></div>
                    <div class="bar" style="height:65%"></div>
                    <div class="bar" style="height:90%"></div>
                    <div class="bar" style="height:70%"></div>
                </div>
                <div class="legend">
                    <span><i class="dot" style="background:#f59e0b"></i>Seg</span>
                    <span><i class="dot" style="background:#60a5fa"></i>Ter</span>
                    <span><i class="dot" style="background:#34d399"></i>Qua</span>
                    <span><i class="dot" style="background:#f87171"></i>Qui</span>
                    <span><i class="dot" style="background:#a78bfa"></i>Sex</span>
                </div>
            </div>

            <div class="card">
                <div class="kpi-title">EficiÃªncia por Equipa</div>
                <div class="list">
                    <div>
                        <div class="list-item"><span>Oficina A</span><span>84%</span></div>
                        <div class="progress"><span style="width:84%"></span></div>
                    </div>
                    <div>
                        <div class="list-item"><span>Transporte</span><span>76%</span></div>
                        <div class="progress"><span style="width:76%"></span></div>
                    </div>
                    <div>
                        <div class="list-item"><span>LogÃ­stica</span><span>68%</span></div>
                        <div class="progress"><span style="width:68%"></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
