<?php
$page_title = 'BI & Relatórios | Vilcon System';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$kpis = [
    ['titulo' => 'Custos Operacionais (Mês)', 'valor' => '3.12M MZN', 'sub' => '-2.1% vs mês anterior'],
    ['titulo' => 'Taxa de Cumprimento', 'valor' => '91%', 'sub' => '+3 pts em 30 dias'],
    ['titulo' => 'Tempo Médio de OS', 'valor' => '2.4 dias', 'sub' => 'Meta: 2.0 dias'],
    ['titulo' => 'Consumo de Combustível', 'valor' => '48,200 L', 'sub' => '+4% vs média'],
];

$custos = [
    ['categoria' => 'Manutenção', 'valor' => 42],
    ['categoria' => 'Combustível', 'valor' => 30],
    ['categoria' => 'Peças', 'valor' => 18],
    ['categoria' => 'Serviços', 'valor' => 10],
];
?>

<div class="main-content">
    <div class="top-bar">
        <h2>BI & Relatórios</h2>
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
            .chart-area { height:220px; width:100%; }
            .legend { display:flex; gap:10px; margin-top:8px; font-size:11px; color:#6b7280; flex-wrap:wrap; }
            .dot { width:8px; height:8px; border-radius:999px; display:inline-block; }
            .list { display:flex; flex-direction:column; gap:10px; }
            .list-item { display:flex; justify-content:space-between; font-size:12px; color:#374151; }
            .progress { height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin-top:6px; }
            .progress > span { display:block; height:100%; background:#f59e0b; }
            .filters { display:flex; gap:10px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
            .filters select, .filters input { padding:8px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:12px; background:#fff; }
            @media (max-width: 1100px) {
                .kpi-grid { grid-template-columns: repeat(2, 1fr); }
                .section { grid-template-columns: 1fr; }
            }
        </style>

        <div class="filters">
            <select>
                <option>Últimos 30 dias</option>
                <option>Últimos 90 dias</option>
                <option>Este ano</option>
            </select>
            <select>
                <option>Todos os projetos</option>
                <option>Projeto Norte</option>
                <option>Projeto Sul</option>
            </select>
            <input type="text" placeholder="Pesquisar relatório...">
        </div>

        <div class="kpi-grid">
            <?php foreach ($kpis as $k): ?>
                <div class="card">
                    <div class="kpi-title"><?= htmlspecialchars($k['titulo']) ?></div>
                    <div class="kpi-value"><?= htmlspecialchars($k['valor']) ?></div>
                    <div class="kpi-sub"><?= htmlspecialchars($k['sub']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="section">
            <div class="card">
                <div class="kpi-title">Tendência de Custos (12 meses)</div>
                <svg class="chart-area" viewBox="0 0 600 220" role="img" aria-label="Tendência de custos">
                    <polyline fill="none" stroke="#60a5fa" stroke-width="3" points="20,180 70,140 120,150 170,120 220,130 270,90 320,110 370,80 420,95 470,70 520,85 580,60"/>
                    <line x1="20" y1="200" x2="580" y2="200" stroke="#e5e7eb"/>
                    <circle cx="70" cy="140" r="3" fill="#60a5fa"/>
                    <circle cx="170" cy="120" r="3" fill="#60a5fa"/>
                    <circle cx="270" cy="90" r="3" fill="#60a5fa"/>
                    <circle cx="370" cy="80" r="3" fill="#60a5fa"/>
                    <circle cx="470" cy="70" r="3" fill="#60a5fa"/>
                </svg>
                <div class="legend">
                    <span><i class="dot" style="background:#60a5fa"></i> Custos totais</span>
                </div>
            </div>

            <div class="card">
                <div class="kpi-title">Distribuição de Custos</div>
                <div class="list">
                    <?php foreach ($custos as $c): ?>
                        <div>
                            <div class="list-item">
                                <span><?= htmlspecialchars($c['categoria']) ?></span>
                                <span><?= (int)$c['valor'] ?>%</span>
                            </div>
                            <div class="progress"><span style="width: <?= (int)$c['valor'] ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
