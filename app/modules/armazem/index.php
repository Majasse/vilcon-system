<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

$page_title = 'Armazém | Vilcon System';

$view = $_GET['view'] ?? 'stock';
$mode = $_GET['mode'] ?? 'list';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro = $_GET['filtro'] ?? 'todos';

$itens_demo = [
    ['codigo' => 'MAT-001', 'descricao' => 'Óleo 15W40', 'unidade' => 'L', 'stock' => 120, 'min' => 40, 'categoria' => 'Lubrificantes'],
    ['codigo' => 'FERR-014', 'descricao' => 'Chave 13mm', 'unidade' => 'un', 'stock' => 8, 'min' => 5, 'categoria' => 'Ferramentas'],
    ['codigo' => 'PNEU-225', 'descricao' => 'Pneu 225/70 R19.5', 'unidade' => 'un', 'stock' => 3, 'min' => 6, 'categoria' => 'Pneus'],
    ['codigo' => 'FILT-009', 'descricao' => 'Filtro de óleo Toyota', 'unidade' => 'un', 'stock' => 20, 'min' => 10, 'categoria' => 'Filtros'],
];

$filtros_por_view = [
    'stock' => [
        'todos' => 'Todos',
        'baixo' => 'Abaixo do mínimo',
        'ok' => 'OK',
    ],
    'entradas' => [
        'todos' => 'Todos',
        'hoje' => 'Hoje',
        'semana' => 'Esta semana',
    ],
    'saidas' => [
        'todos' => 'Todos',
        'obra' => 'Por Obra',
        'oficina' => 'Para Oficina',
    ],
    'fornecedores' => [
        'todos' => 'Todos',
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
    ],
    'inventario' => [
        'todos' => 'Todos',
        'pendente' => 'Pendente',
        'fechado' => 'Fechado',
    ],
];
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Armazém</h2>
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
            .tabs {
                display: flex;
                gap: 10px;
                margin-bottom: 18px;
                flex-wrap: wrap;
            }
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
            .tab.active {
                background: var(--accent-orange);
                color: #fff;
                border-color: var(--accent-orange);
            }
            .card {
                background: #ffffff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 6px 16px rgba(17,24,39,0.08);
                border: 1px solid #e5e7eb;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }
            .table th,
            .table td {
                padding: 12px 10px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
            }
            .table th {
                color: #6b7280;
                font-size: 11px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            .badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 700;
            }
            .badge.ok { background: rgba(39, 174, 96, 0.2); color: #2ecc71; }
            .badge.alerta { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
            .muted { color: #6b7280; }
            .mode {
                display: flex;
                gap: 8px;
                margin-bottom: 14px;
            }
            .mode a {
                padding: 6px 10px;
                border-radius: 6px;
                font-size: 11px;
                text-decoration: none;
                border: 1px solid #e5e7eb;
                color: #6b7280;
                background: #ffffff;
            }
            .mode a.active {
                background: #111827;
                color: #ffffff;
            }
            .form-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
            }
            .form-group { display: flex; flex-direction: column; }
            label { font-size: 10px; font-weight: 800; color: #6b7280; margin-bottom: 5px; text-transform: uppercase; }
            input, select, textarea { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background:#ffffff; color:#111827; }
            .btn-save { padding: 10px 14px; border-radius: 6px; font-weight: 700; font-size: 11px; border: none; color: #fff; background: var(--accent-orange); cursor: pointer; }
        </style>

        <div class="tabs">
            <a class="tab <?= $view === 'stock' ? 'active' : '' ?>" href="?view=stock">Stock</a>
            <a class="tab <?= $view === 'entradas' ? 'active' : '' ?>" href="?view=entradas">Entradas</a>
            <a class="tab <?= $view === 'saidas' ? 'active' : '' ?>" href="?view=saidas">Saídas</a>
            <a class="tab <?= $view === 'fornecedores' ? 'active' : '' ?>" href="?view=fornecedores">Fornecedores</a>
            <a class="tab <?= $view === 'inventario' ? 'active' : '' ?>" href="?view=inventario">Inventário</a>
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
                    <?= count($itens_demo) ?> registo(s)
                </div>
            </div>

            <div class="mode">
                <a href="?view=<?= $view ?>&mode=list" class="<?= $mode === 'list' ? 'active' : '' ?>">
                    <i class="fa-solid fa-list"></i> Lista
                </a>
                <a href="?view=<?= $view ?>&mode=form" class="<?= $mode === 'form' ? 'active' : '' ?>">
                    <i class="fa-solid fa-plus"></i> Novo Registo
                </a>
            </div>

            <?php if ($mode === 'list' && $view === 'stock'): ?>
                <?php
                    $filtrados = array_filter($itens_demo, function ($item) use ($q, $filtro) {
                        $texto = strtolower($item['codigo'] . ' ' . $item['descricao'] . ' ' . $item['categoria']);
                        if ($q !== '' && strpos($texto, strtolower($q)) === false) {
                            return false;
                        }
                        $baixo = $item['stock'] < $item['min'];
                        if ($filtro === 'baixo' && !$baixo) {
                            return false;
                        }
                        if ($filtro === 'ok' && $baixo) {
                            return false;
                        }
                        return true;
                    });
                ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Unidade</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtrados as $item): ?>
                            <?php $baixo = $item['stock'] < $item['min']; ?>
                            <tr>
                                <td><?= htmlspecialchars($item['codigo']) ?></td>
                                <td><?= htmlspecialchars($item['descricao']) ?></td>
                                <td><?= htmlspecialchars($item['categoria']) ?></td>
                                <td><?= htmlspecialchars($item['unidade']) ?></td>
                                <td><?= htmlspecialchars((string)$item['stock']) ?></td>
                                <td><?= htmlspecialchars((string)$item['min']) ?></td>
                                <td>
                                    <span class="badge <?= $baixo ? 'alerta' : 'ok' ?>">
                                        <?= $baixo ? 'Repor' : 'OK' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($mode === 'form' && $view === 'stock'): ?>
                <form class="form-grid">
                    <div class="form-group">
                        <label>Código</label>
                        <input type="text" name="codigo">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Descrição</label>
                        <input type="text" name="descricao">
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <input type="text" name="categoria">
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <input type="text" name="unidade" placeholder="un, L, kg">
                    </div>
                    <div class="form-group">
                        <label>Stock Inicial</label>
                        <input type="number" name="stock">
                    </div>
                    <div class="form-group">
                        <label>Stock Mínimo</label>
                        <input type="number" name="min">
                    </div>
                    <div class="form-group" style="grid-column: span 4;">
                        <label>Observações</label>
                        <textarea name="obs" rows="3"></textarea>
                    </div>
                    <div style="grid-column: span 4;">
                        <button class="btn-save">Guardar</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="muted">Vista em construção. A pesquisa e o filtro já estão disponíveis para quando houver dados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
