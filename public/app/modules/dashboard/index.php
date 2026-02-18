<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

$page_title = 'Dashboard | Vilcon System';

function tabelaExiste($pdo, $tabela) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabela');
        $stmt->execute(['tabela' => $tabela]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function normalizarNivelSegurancaDashboard($dataValidade, $nivelBase = '') {
    $nivel = strtolower(trim((string)$nivelBase));
    if (in_array($nivel, ['critico', 'atencao', 'normal'], true)) {
        return $nivel;
    }

    if ($dataValidade === null || $dataValidade === '') {
        return 'normal';
    }

    $hoje = new DateTime('today');
    $venc = DateTime::createFromFormat('Y-m-d', (string)$dataValidade);
    if (!$venc) {
        return 'normal';
    }

    $dias = (int)$hoje->diff($venc)->format('%r%a');
    if ($dias < 0) return 'critico';
    if ($dias <= 30) return 'atencao';
    return 'normal';
}

function diasParaDashboard($dataValidade) {
    if ($dataValidade === null || $dataValidade === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', (string)$dataValidade);
    if (!$dt) {
        return null;
    }
    $hoje = new DateTime('today');
    return (int)$hoje->diff($dt)->format('%r%a');
}

$os_total = 0;
$os_abertas = 0;
$os_pendentes = 0;
$os_andamento = 0;
$os_aceitos = 0;
$os_resolvidos = 0;
$os_recentes = [];

$alertas_total = 0;
$alertas_criticos = 0;
$alertas_atencao = 0;
$alertas_lista = [];

$ativos_total = 0;

$auditoria_7d = 0;
$auditoria_series = [];

$hoje = new DateTime('today');
for ($i = 6; $i >= 0; $i--) {
    $dia = (clone $hoje)->modify("-{$i} days");
    $auditoria_series[$dia->format('Y-m-d')] = [
        'label' => $dia->format('d/m'),
        'count' => 0,
    ];
}

if (tabelaExiste($pdo, 'oficina_pedidos_reparacao')) {
    try {
        $stmt = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IS NULL OR status = '' OR LOWER(status) IN ('pendente','aberto') THEN 1 ELSE 0 END) AS pendentes,
                SUM(CASE WHEN LOWER(status) IN ('em andamento','em progresso','andamento') THEN 1 ELSE 0 END) AS andamento,
                SUM(CASE WHEN LOWER(status) IN ('aceito','aceite') THEN 1 ELSE 0 END) AS aceitos,
                SUM(CASE WHEN LOWER(status) IN ('resolvido','fechado','concluido') THEN 1 ELSE 0 END) AS resolvidos
            FROM oficina_pedidos_reparacao
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $os_total = (int)$row['total'];
            $os_pendentes = (int)$row['pendentes'];
            $os_andamento = (int)$row['andamento'];
            $os_aceitos = (int)$row['aceitos'];
            $os_resolvidos = (int)$row['resolvidos'];
            $os_abertas = max(0, $os_total - $os_resolvidos);
        }

        $stmt = $pdo->query("
            SELECT id, ativo_matricula, tipo_equipamento, prioridade, status, data_pedido
            FROM oficina_pedidos_reparacao
            ORDER BY id DESC
            LIMIT 6
        ");
        $os_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Mantem zeros se houver falha.
    }
}

if (tabelaExiste($pdo, 'documental_seguranca')) {
    try {
        $stmt = $pdo->query("SELECT data_validade, nivel FROM documental_seguranca");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $nivel = normalizarNivelSegurancaDashboard($row['data_validade'] ?? null, $row['nivel'] ?? '');
            $alertas_total++;
            if ($nivel === 'critico') $alertas_criticos++;
            if ($nivel === 'atencao') $alertas_atencao++;
        }

        $stmt = $pdo->query("
            SELECT id, item, tipo_alerta, data_validade, nivel, observacoes, created_at
            FROM documental_seguranca
            ORDER BY (data_validade IS NULL), data_validade ASC, id DESC
            LIMIT 6
        ");
        $alertas_lista = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Mantem zeros se houver falha.
    }
}

if (tabelaExiste($pdo, 'activos')) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM activos WHERE estado <> 'VENDIDO' OR estado IS NULL");
        $ativos_total = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // Mantem zeros se houver falha.
    }
}

if (tabelaExiste($pdo, 'auditoria')) {
    try {
        $stmt = $pdo->query("
            SELECT DATE(data_hora) AS dia, COUNT(*) AS total
            FROM auditoria
            WHERE data_hora >= (CURDATE() - INTERVAL 6 DAY)
            GROUP BY DATE(data_hora)
            ORDER BY dia
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $dia = (string)($row['dia'] ?? '');
            if (isset($auditoria_series[$dia])) {
                $auditoria_series[$dia]['count'] = (int)$row['total'];
            }
        }
        foreach ($auditoria_series as $entry) {
            $auditoria_7d += (int)$entry['count'];
        }
    } catch (Throwable $e) {
        // Mantem zeros se houver falha.
    }
}

$os_status = [
    ['label' => 'Pendentes', 'count' => $os_pendentes, 'color' => '#f59e0b'],
    ['label' => 'Em andamento', 'count' => $os_andamento, 'color' => '#60a5fa'],
    ['label' => 'Aceitos', 'count' => $os_aceitos, 'color' => '#34d399'],
    ['label' => 'Resolvidos', 'count' => $os_resolvidos, 'color' => '#a78bfa'],
];
$max_os = 1;
foreach ($os_status as $s) {
    if ($s['count'] > $max_os) $max_os = $s['count'];
}

$auditoria_list = array_values($auditoria_series);
$max_auditoria = 1;
foreach ($auditoria_list as $a) {
    if ($a['count'] > $max_auditoria) $max_auditoria = $a['count'];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="top-bar">
        <h2>Dashboard Estrategico</h2>
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
            .kpi-sub { font-size:11px; color:#6b7280; margin-top:4px; }
            .section { margin-top:18px; display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
            .chart { display:flex; align-items:flex-end; gap:10px; height:140px; }
            .bar { width:18%; background:#f59e0b; border-radius:6px 6px 0 0; position:relative; }
            .bar small { position:absolute; top:-18px; left:50%; transform:translateX(-50%); font-size:10px; color:#6b7280; }
            .legend { display:flex; gap:10px; margin-top:8px; font-size:11px; color:#6b7280; flex-wrap:wrap; }
            .legend span { display:flex; align-items:center; gap:6px; }
            .dot { width:8px; height:8px; border-radius:999px; display:inline-block; }
            .list { display:flex; flex-direction:column; gap:10px; }
            .list-item { display:flex; justify-content:space-between; font-size:12px; color:#374151; }
            .pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
            .pill.warn { background:rgba(245, 158, 11, 0.18); color:#b45309; }
            .pill.crit { background:rgba(239, 68, 68, 0.18); color:#b91c1c; }
            .pill.ok { background:rgba(16, 185, 129, 0.18); color:#047857; }
            .table-mini { width:100%; border-collapse:collapse; font-size:12px; }
            .table-mini th, .table-mini td { padding:10px 8px; border-bottom:1px solid #e5e7eb; text-align:left; }
            .table-mini th { font-size:10px; text-transform:uppercase; color:#6b7280; letter-spacing:.4px; }
            .muted { color:#6b7280; font-size:12px; }
            @media (max-width: 1100px) {
                .kpi-grid { grid-template-columns: repeat(2, 1fr); }
                .section { grid-template-columns: 1fr; }
            }
        </style>

        <div class="kpi-grid">
            <div class="card">
                <div class="kpi-title">Pedidos de Reparacao Abertos</div>
                <div class="kpi-value"><?= htmlspecialchars((string)$os_abertas) ?></div>
                <div class="kpi-sub"><?= htmlspecialchars((string)$os_total) ?> no total</div>
            </div>
            <div class="card">
                <div class="kpi-title">Alertas Criticos</div>
                <div class="kpi-value"><?= htmlspecialchars((string)$alertas_criticos) ?></div>
                <div class="kpi-sub"><?= htmlspecialchars((string)$alertas_atencao) ?> em atencao</div>
            </div>
            <div class="card">
                <div class="kpi-title">Ativos Registados</div>
                <div class="kpi-value"><?= htmlspecialchars((string)$ativos_total) ?></div>
                <div class="kpi-sub">Ativos em operacao</div>
            </div>
            <div class="card">
                <div class="kpi-title">Atividade (7 dias)</div>
                <div class="kpi-value"><?= htmlspecialchars((string)$auditoria_7d) ?></div>
                <div class="kpi-sub">Registos do sistema</div>
            </div>
        </div>

        <div class="section">
            <div class="card">
                <div class="kpi-title">Pedidos de Reparacao por Status</div>
                <div class="chart" aria-label="Pedidos por status">
                    <?php foreach ($os_status as $s): ?>
                        <?php $altura = (int)round(($s['count'] / $max_os) * 100); ?>
                        <div class="bar" style="height:<?= $altura ?>%; background:<?= htmlspecialchars($s['color']) ?>;">
                            <small><?= htmlspecialchars((string)$s['count']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="legend">
                    <?php foreach ($os_status as $s): ?>
                        <span><i class="dot" style="background:<?= htmlspecialchars($s['color']) ?>"></i><?= htmlspecialchars($s['label']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="kpi-title">Atividade do Sistema (7 dias)</div>
                <div class="chart" aria-label="Atividade do sistema">
                    <?php foreach ($auditoria_list as $a): ?>
                        <?php $altura = (int)round(($a['count'] / $max_auditoria) * 100); ?>
                        <div class="bar" style="height:<?= $altura ?>%; background:#111827;">
                            <small><?= htmlspecialchars((string)$a['count']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="legend">
                    <?php foreach ($auditoria_list as $a): ?>
                        <span><i class="dot" style="background:#111827;"></i><?= htmlspecialchars($a['label']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="card">
                <div class="kpi-title">Alertas de Seguranca Proximos</div>
                <?php if (count($alertas_lista) === 0): ?>
                    <p class="muted">Sem alertas registados para mostrar.</p>
                <?php else: ?>
                    <div class="list">
                        <?php foreach ($alertas_lista as $a): ?>
                            <?php
                                $nivel = normalizarNivelSegurancaDashboard($a['data_validade'] ?? null, $a['nivel'] ?? '');
                                $dias = diasParaDashboard($a['data_validade'] ?? null);
                                $classe = $nivel === 'critico' ? 'crit' : ($nivel === 'atencao' ? 'warn' : 'ok');
                            ?>
                            <div class="list-item">
                                <div>
                                    <strong><?= htmlspecialchars((string)($a['item'] ?? '-')) ?></strong>
                                    <div class="muted"><?= htmlspecialchars((string)($a['tipo_alerta'] ?? 'Alerta')) ?></div>
                                </div>
                                <div style="text-align:right;">
                                    <span class="pill <?= $classe ?>"><?= htmlspecialchars(ucfirst($nivel)) ?></span>
                                    <div class="muted">
                                        <?php if ($dias === null): ?>
                                            Sem data
                                        <?php elseif ($dias < 0): ?>
                                            Vencido ha <?= htmlspecialchars((string)abs($dias)) ?> dia(s)
                                        <?php elseif ($dias === 0): ?>
                                            Vence hoje
                                        <?php else: ?>
                                            Em <?= htmlspecialchars((string)$dias) ?> dia(s)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="kpi-title">Pedidos Recentes de Reparacao</div>
                <?php if (count($os_recentes) === 0): ?>
                    <p class="muted">Sem pedidos recentes para mostrar.</p>
                <?php else: ?>
                    <table class="table-mini">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ativo</th>
                                <th>Tipo</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($os_recentes as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($p['id'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($p['ativo_matricula'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($p['tipo_equipamento'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($p['prioridade'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($p['status'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
