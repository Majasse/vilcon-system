<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

$page_title = 'Seguranca | Vilcon System';

$view = $_GET['view'] ?? 'saidas';
$mode = $_GET['mode'] ?? 'list';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro = $_GET['filtro'] ?? 'todos';

$registos_demo = [];

$filtros_por_view = [
    'saidas' => [
        'todos' => 'Todos',
        'autorizada' => 'Autorizadas',
        'pendente' => 'Pendentes',
        'bloqueada' => 'Bloqueadas',
    ],
    'autorizacoes' => [
        'todos' => 'Todos',
        'hoje' => 'Hoje',
        'semana' => 'Esta Semana',
    ],
    'ocorrencias' => [
        'todos' => 'Todos',
        'desvio' => 'Desvio de Rota',
        'atraso' => 'Atraso',
        'incidente' => 'Incidente',
    ],
    'visitantes' => [
        'todos' => 'Todos',
        'em_guarita' => 'Em Guarita',
        'autorizado' => 'Autorizados',
        'recusado' => 'Recusados',
    ],
];
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Seguranca da Guarita</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong>
        </div>
    </div>

    <div class="dashboard-container">
        <style>
            body { background: #f4f7f6; color: #1f2937; }
            .main-content { background: #f4f7f6; }
            .top-bar { background: #ffffff; border-bottom: 1px solid #e5e7eb; }
            .user-info { color: #6b7280; }
            .user-info strong { color: #111827; }
            .tabs { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
            .tab {
                padding: 8px 14px;
                border-radius: 999px;
                background: #ffffff;
                color: #6b7280;
                text-decoration: none;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                border: 1px solid #e5e7eb;
            }
            .tab.active { background: var(--accent-orange); color: #fff; border-color: var(--accent-orange); }
            .card {
                background: #ffffff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 6px 16px rgba(17,24,39,0.08);
                border: 1px solid #e5e7eb;
            }
            .table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .table th, .table td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
            .table th { color: #6b7280; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
            .badge.autorizada { background: rgba(39, 174, 96, 0.2); color: #2ecc71; }
            .badge.pendente { background: rgba(241, 196, 15, 0.22); color: #b7791f; }
            .badge.bloqueada { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
            .muted { color: #6b7280; }
            .mode { display: flex; gap: 8px; margin-bottom: 14px; }
            .mode a {
                padding: 6px 10px;
                border-radius: 6px;
                font-size: 11px;
                text-decoration: none;
                border: 1px solid #e5e7eb;
                color: #6b7280;
                background: #ffffff;
            }
            .mode a.active { background: #111827; color: #ffffff; }
            .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
            .form-group { display: flex; flex-direction: column; }
            label { font-size: 10px; font-weight: 800; color: #6b7280; margin-bottom: 5px; text-transform: uppercase; }
            input, select, textarea { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background:#ffffff; color:#111827; }
            .btn-save { padding: 10px 14px; border-radius: 6px; font-weight: 700; font-size: 11px; border: none; color: #fff; background: var(--accent-orange); cursor: pointer; }
        </style>

        <div class="tabs">
            <a class="tab <?= $view === 'saidas' ? 'active' : '' ?>" href="?view=saidas">Saidas de Viaturas</a>
            <a class="tab <?= $view === 'autorizacoes' ? 'active' : '' ?>" href="?view=autorizacoes">Autorizacoes</a>
            <a class="tab <?= $view === 'ocorrencias' ? 'active' : '' ?>" href="?view=ocorrencias">Ocorrencias</a>
            <a class="tab <?= $view === 'visitantes' ? 'active' : '' ?>" href="?view=visitantes">Visitantes</a>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
                <form method="GET" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                    <div style="display:flex; align-items:center; gap:8px; background:#ffffff; border:1px solid #e5e7eb; padding:8px 12px; border-radius:999px;">
                        <i class="fa-solid fa-magnifying-glass" style="color:#9ca3af;"></i>
                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($q) ?>"
                            placeholder="Pesquisar..."
                            style="background:transparent; border:none; outline:none; color:#111827; font-size:12px; width:220px;"
                        >
                    </div>
                    <select name="filtro" style="border:1px solid #e5e7eb; border-radius:10px; padding:8px 10px; font-size:12px;">
                        <?php foreach (($filtros_por_view[$view] ?? ['todos' => 'Todos']) as $k => $label): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="background:#111827; color:#fff; border:none; padding:8px 12px; border-radius:8px; font-size:11px; font-weight:700;">
                        Filtrar
                    </button>
                    <?php if ($q !== '' || $filtro !== 'todos'): ?>
                        <a href="index.php?view=<?= htmlspecialchars($view) ?>&mode=<?= htmlspecialchars($mode) ?>" style="color:#6b7280; font-size:11px; text-decoration:none;">Limpar</a>
                    <?php endif; ?>
                </form>
                <div class="muted" style="font-size:11px;">
                    <?= count($registos_demo) ?> registo(s)
                </div>
            </div>

            <div class="mode">
                <a href="?view=<?= $view ?>&mode=list" class="<?= $mode === 'list' ? 'active' : '' ?>">
                    <i class="fa-solid fa-list"></i> Lista
                </a>
                <a href="?view=<?= $view ?>&mode=form" class="<?= $mode === 'form' ? 'active' : '' ?>">
                    <i class="fa-solid fa-plus"></i> Nova Saida
                </a>
            </div>

            <?php if ($mode === 'list' && $view === 'saidas'): ?>
                <?php
                    $filtrados = array_filter($registos_demo, function ($item) use ($q, $filtro) {
                        $texto = strtolower($item['viatura'] . ' ' . $item['motorista'] . ' ' . $item['destino']);
                        if ($q !== '' && strpos($texto, strtolower($q)) === false) {
                            return false;
                        }
                        $status = strtolower($item['status']);
                        if ($filtro === 'autorizada' && $status !== 'autorizada') return false;
                        if ($filtro === 'pendente' && $status !== 'pendente') return false;
                        if ($filtro === 'bloqueada' && $status !== 'bloqueada') return false;
                        return true;
                    });
                ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Viatura</th>
                            <th>Motorista</th>
                            <th>Destino</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($filtrados) === 0): ?>
                            <tr>
                                <td colspan="6" class="muted" style="text-align:center;">Sem registos para mostrar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filtrados as $item): ?>
                                <?php $status = strtolower($item['status']); ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['data']) ?></td>
                                    <td><?= htmlspecialchars($item['hora']) ?></td>
                                    <td><?= htmlspecialchars($item['viatura']) ?></td>
                                    <td><?= htmlspecialchars($item['motorista']) ?></td>
                                    <td><?= htmlspecialchars($item['destino']) ?></td>
                                    <td><span class="badge <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($mode === 'form' && $view === 'saidas'): ?>
                <form class="form-grid">
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="data_saida">
                    </div>
                    <div class="form-group">
                        <label>Hora</label>
                        <input type="time" name="hora_saida">
                    </div>
                    <div class="form-group">
                        <label>Viatura</label>
                        <input type="text" name="viatura" placeholder="ABC-123-MP">
                    </div>
                    <div class="form-group">
                        <label>Motorista</label>
                        <input type="text" name="motorista">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Destino</label>
                        <input type="text" name="destino">
                    </div>
                    <div class="form-group">
                        <label>Autorizado Por</label>
                        <input type="text" name="autorizado_por">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Autorizada">Autorizada</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Bloqueada">Bloqueada</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 4;">
                        <label>Observacoes da Guarita</label>
                        <textarea name="obs" rows="3"></textarea>
                    </div>
                    <div style="grid-column: span 4;">
                        <button class="btn-save">Guardar Saida</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="muted">Vista em construcao. Estrutura pronta para ligar a dados reais da guarita.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
