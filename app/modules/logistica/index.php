<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Logistica | Vilcon System';

$view = $_GET['view'] ?? 'painel';
$mode = $_GET['mode'] ?? 'list';
$q = trim((string)($_GET['q'] ?? ''));
$status_filtro = trim((string)($_GET['status'] ?? 'todos'));

$msg = null;
$erro = null;
$requisicoes = [];

function badgeStatusLogistica(string $status): string {
    $s = strtolower(trim($status));
    if ($s === 'pendente') return 'warn';
    if ($s === 'em transito') return 'info';
    if ($s === 'entregue') return 'ok';
    if ($s === 'cancelada') return 'danger';
    return 'info';
}

function normalizarStatusLogistica(string $status): string {
    $s = strtolower(trim($status));
    if (in_array($s, ['pendente', 'em transito', 'entregue', 'cancelada'], true)) {
        return $s;
    }
    return 'pendente';
}

try {
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS logistica_requisicoes (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            codigo VARCHAR(40) UNIQUE,\n            origem VARCHAR(150) NOT NULL,\n            destino VARCHAR(150) NOT NULL,\n            item VARCHAR(180) NOT NULL,\n            quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,\n            unidade VARCHAR(20) NOT NULL DEFAULT 'un',\n            prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',\n            status VARCHAR(20) NOT NULL DEFAULT 'Pendente',\n            data_requisicao DATE NOT NULL,\n            responsavel VARCHAR(150) NULL,\n            observacoes TEXT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\n    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = trim((string)($_POST['acao'] ?? ''));

        if ($acao === 'criar_requisicao') {
            $origem = trim((string)($_POST['origem'] ?? ''));
            $destino = trim((string)($_POST['destino'] ?? ''));
            $item = trim((string)($_POST['item'] ?? ''));
            $quantidade = (float)($_POST['quantidade'] ?? 0);
            $unidade = trim((string)($_POST['unidade'] ?? 'un'));
            $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
            $data_requisicao = trim((string)($_POST['data_requisicao'] ?? date('Y-m-d')));
            $responsavel = trim((string)($_POST['responsavel'] ?? ''));
            $observacoes = trim((string)($_POST['observacoes'] ?? ''));

            if ($origem === '' || $destino === '' || $item === '' || $quantidade <= 0) {
                throw new RuntimeException('Preencha os campos obrigatorios da requisicao.');
            }

            $stmt = $pdo->prepare("\n                INSERT INTO logistica_requisicoes\n                    (origem, destino, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes)\n                VALUES\n                    (:origem, :destino, :item, :quantidade, :unidade, :prioridade, 'Pendente', :data_requisicao, :responsavel, :observacoes)\n            ");
            $stmt->execute([
                'origem' => $origem,
                'destino' => $destino,
                'item' => $item,
                'quantidade' => $quantidade,
                'unidade' => $unidade !== '' ? $unidade : 'un',
                'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                'data_requisicao' => $data_requisicao,
                'responsavel' => $responsavel !== '' ? $responsavel : null,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
            ]);

            $id = (int)$pdo->lastInsertId();
            $codigo = sprintf('REQ-LOG-%s-%04d', date('Y'), $id);
            $pdo->prepare('UPDATE logistica_requisicoes SET codigo = :codigo WHERE id = :id')
                ->execute(['codigo' => $codigo, 'id' => $id]);

            if (function_exists('registrarAuditoria')) {
                registrarAuditoria($pdo, 'Inseriu requisicao logistica', 'logistica_requisicoes');
            }

            header('Location: ?view=requisicoes&mode=list&saved=1');
            exit;
        }

        if ($acao === 'mudar_status') {
            $id = (int)($_POST['id'] ?? 0);
            $novo_status = trim((string)($_POST['novo_status'] ?? ''));
            $novo_status = normalizarStatusLogistica($novo_status);

            if ($id <= 0) {
                throw new RuntimeException('Requisicao invalida.');
            }

            $status_label = ucfirst($novo_status);
            if ($novo_status === 'em transito') {
                $status_label = 'Em transito';
            }

            $pdo->prepare('UPDATE logistica_requisicoes SET status = :status WHERE id = :id')
                ->execute(['status' => $status_label, 'id' => $id]);

            header('Location: ?view=requisicoes&mode=list&updated=1');
            exit;
        }
    }

    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $msg = 'Requisicao logistica criada com sucesso.';
    }
    if (isset($_GET['updated']) && $_GET['updated'] === '1') {
        $msg = 'Status da requisicao atualizado.';
    }

    $sql = "SELECT id, codigo, origem, destino, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes, created_at\n            FROM logistica_requisicoes\n            ORDER BY id DESC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $requisicoes = array_values(array_filter($rows, function ($r) use ($q, $status_filtro) {
        $texto = strtolower((string)($r['codigo'] ?? '') . ' ' . (string)($r['origem'] ?? '') . ' ' . (string)($r['destino'] ?? '') . ' ' . (string)($r['item'] ?? '') . ' ' . (string)($r['responsavel'] ?? ''));
        if ($q !== '' && strpos($texto, strtolower($q)) === false) {
            return false;
        }

        if ($status_filtro !== '' && $status_filtro !== 'todos') {
            if (normalizarStatusLogistica((string)($r['status'] ?? '')) !== strtolower($status_filtro)) {
                return false;
            }
        }

        return true;
    }));
} catch (Throwable $e) {
    $erro = 'Nao foi possivel processar Logistica: ' . $e->getMessage();
}

$total = count($requisicoes);
$pendentes = 0;
$transito = 0;
$entregues = 0;
$urgentes = 0;
foreach ($requisicoes as $r) {
    $st = normalizarStatusLogistica((string)($r['status'] ?? ''));
    if ($st === 'pendente') $pendentes++;
    if ($st === 'em transito') $transito++;
    if ($st === 'entregue') $entregues++;
    if (strtolower((string)($r['prioridade'] ?? 'normal')) === 'urgente') $urgentes++;
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Logistica</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong>
        </div>
    </div>

    <div class="dashboard-container">
        <style>
            body { background: #f4f7f6; color: #111827; }
            .main-content { background: #f4f7f6; }
            .top-bar { background: #ffffff; border-bottom: 1px solid #e5e7eb; }
            .user-info { color: #6b7280; }
            .user-info strong { color: #111827; }
            .tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
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
            .tab.active { background:#f59e0b; color:#fff; border-color:#f59e0b; }
            .card {
                background:#ffffff;
                border-radius:12px;
                padding:20px;
                box-shadow:0 6px 16px rgba(17,24,39,0.08);
                border:1px solid #e5e7eb;
            }
            .kpi-grid {
                display:grid;
                grid-template-columns: repeat(4, minmax(140px, 1fr));
                gap:12px;
                margin-bottom:14px;
            }
            .kpi {
                background:#fff;
                border:1px solid #e5e7eb;
                border-radius:10px;
                padding:10px;
            }
            .kpi .k-title { font-size:10px; text-transform:uppercase; color:#64748b; font-weight:800; }
            .kpi .k-value { font-size:22px; font-weight:800; color:#111827; margin-top:6px; }
            .kpi.warn { background:#fff7ed; border-color:#fed7aa; }
            .kpi.info { background:#eff6ff; border-color:#bfdbfe; }
            .kpi.ok { background:#ecfdf3; border-color:#bbf7d0; }
            .kpi.danger { background:#fef2f2; border-color:#fecaca; }
            .toolbar {
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:12px;
                margin-bottom:12px;
                flex-wrap:wrap;
            }
            .filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
            .search {
                display:flex;
                align-items:center;
                gap:8px;
                border:1px solid #e5e7eb;
                border-radius:999px;
                padding:8px 12px;
                background:#fff;
            }
            .search input { border:none; outline:none; font-size:12px; min-width:220px; background:transparent; }
            select, input, textarea {
                padding:10px;
                border:1px solid #d1d5db;
                border-radius:6px;
                font-size:13px;
                background:#fff;
                color:#111827;
            }
            .btn { border:none; border-radius:8px; padding:9px 12px; font-size:11px; font-weight:700; cursor:pointer; }
            .btn.primary { background:#111827; color:#fff; }
            .btn.secondary { background:#fff; color:#111827; border:1px solid #d1d5db; }
            .mode { display:flex; gap:8px; margin-bottom:12px; }
            .mode a {
                padding:8px 12px;
                border-radius:8px;
                font-size:11px;
                text-decoration:none;
                border:1px solid #e5e7eb;
                color:#6b7280;
                background:#fff;
                font-weight:700;
            }
            .mode a.active { background:#111827; color:#fff; border-color:#111827; }
            .table-wrap { width:100%; overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; }
            .table { width:100%; min-width:1250px; border-collapse:collapse; font-size:12px; }
            .table th, .table td { padding:10px 8px; border-bottom:1px solid #e5e7eb; text-align:left; white-space:nowrap; }
            .table th { font-size:10px; text-transform:uppercase; color:#64748b; letter-spacing:.4px; background:#f8fafc; }
            .pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
            .pill.warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
            .pill.info { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
            .pill.ok { background:#ecfdf3; color:#15803d; border:1px solid #bbf7d0; }
            .pill.danger { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
            .actions { display:flex; gap:6px; }
            .form-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
            label { font-size:10px; font-weight:800; text-transform:uppercase; color:#6b7280; margin-bottom:5px; }
            .form-group { display:flex; flex-direction:column; }
            .section-title { grid-column:span 4; background:#f8fafc; padding:10px; border-left:4px solid #f59e0b; font-size:11px; font-weight:800; text-transform:uppercase; }
            @media (max-width: 980px) {
                .kpi-grid { grid-template-columns:repeat(2, minmax(120px,1fr)); }
                .form-grid { grid-template-columns:1fr; }
                .section-title { grid-column: span 1; }
                .search input { min-width:130px; }
            }
        </style>

        <div class="tabs">
            <a class="tab <?= $view === 'painel' ? 'active' : '' ?>" href="?view=painel&mode=list">Painel</a>
            <a class="tab <?= $view === 'requisicoes' ? 'active' : '' ?>" href="?view=requisicoes&mode=list">Requisicoes</a>
        </div>

        <div class="card">
            <div class="kpi-grid">
                <div class="kpi"><div class="k-title"><i class="fa-solid fa-list-check"></i> Total</div><div class="k-value"><?= (int)$total ?></div></div>
                <div class="kpi warn"><div class="k-title"><i class="fa-solid fa-hourglass-start"></i> Pendentes</div><div class="k-value"><?= (int)$pendentes ?></div></div>
                <div class="kpi info"><div class="k-title"><i class="fa-solid fa-truck-fast"></i> Em transito</div><div class="k-value"><?= (int)$transito ?></div></div>
                <div class="kpi ok"><div class="k-title"><i class="fa-solid fa-circle-check"></i> Entregues</div><div class="k-value"><?= (int)$entregues ?></div></div>
            </div>

            <?php if ($erro): ?>
                <div style="margin-bottom:10px; background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:10px; border-radius:8px; font-size:12px;">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>
            <?php if ($msg): ?>
                <div style="margin-bottom:10px; background:#ecfdf3; border:1px solid #bbf7d0; color:#166534; padding:10px; border-radius:8px; font-size:12px;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div class="toolbar">
                <form class="filters" method="GET" action="">
                    <input type="hidden" name="view" value="<?= htmlspecialchars((string)$view) ?>">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars((string)$mode) ?>">
                    <div class="search">
                        <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Pesquisar codigo, origem, destino, item...">
                    </div>
                    <select name="status">
                        <option value="todos" <?= $status_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="pendente" <?= $status_filtro === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="em transito" <?= $status_filtro === 'em transito' ? 'selected' : '' ?>>Em transito</option>
                        <option value="entregue" <?= $status_filtro === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                        <option value="cancelada" <?= $status_filtro === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                    <button class="btn primary" type="submit">Filtrar</button>
                    <?php if ($q !== '' || $status_filtro !== 'todos'): ?>
                        <a class="btn secondary" style="text-decoration:none;display:inline-flex;align-items:center;" href="?view=<?= urlencode((string)$view) ?>&mode=<?= urlencode((string)$mode) ?>">Limpar</a>
                    <?php endif; ?>
                </form>
                <div style="font-size:11px; color:#64748b;"><strong><?= (int)$urgentes ?></strong> urgente(s)</div>
            </div>

            <?php if ($view === 'requisicoes'): ?>
                <div class="mode">
                    <a href="?view=requisicoes&mode=list" class="<?= $mode === 'list' ? 'active' : '' ?>"><i class="fa-solid fa-list"></i> Lista</a>
                    <a href="?view=requisicoes&mode=form" class="<?= $mode === 'form' ? 'active' : '' ?>"><i class="fa-solid fa-plus"></i> Nova requisicao</a>
                </div>

                <?php if ($mode === 'form'): ?>
                    <form class="form-grid" method="POST" action="?view=requisicoes&mode=form">
                        <input type="hidden" name="acao" value="criar_requisicao">
                        <div class="section-title">Nova requisicao logistica</div>

                        <div class="form-group">
                            <label>Origem</label>
                            <input type="text" name="origem" placeholder="Ex: Armazem Central" required>
                        </div>
                        <div class="form-group">
                            <label>Destino</label>
                            <input type="text" name="destino" placeholder="Ex: Obra Vilankulo" required>
                        </div>
                        <div class="form-group">
                            <label>Responsavel</label>
                            <input type="text" name="responsavel" placeholder="Ex: Carlos M.">
                        </div>
                        <div class="form-group">
                            <label>Data requisicao</label>
                            <input type="date" name="data_requisicao" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>Item</label>
                            <input type="text" name="item" placeholder="Ex: Cimento 32.5" required>
                        </div>
                        <div class="form-group">
                            <label>Quantidade</label>
                            <input type="number" name="quantidade" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Unidade</label>
                            <input type="text" name="unidade" value="un">
                        </div>

                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: span 3;">
                            <label>Observacoes</label>
                            <textarea name="observacoes" rows="3" placeholder="Detalhes da carga, janela de entrega, etc."></textarea>
                        </div>

                        <div style="grid-column: span 4;">
                            <button class="btn primary" style="padding:11px 14px;">Guardar requisicao</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Codigo</th>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Item</th>
                                    <th>Qtd</th>
                                    <th>Prioridade</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Responsavel</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($requisicoes) === 0): ?>
                                    <tr><td colspan="11" style="text-align:center; color:#64748b; padding:12px;">Sem registos para mostrar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($requisicoes as $r): ?>
                                        <?php
                                            $status = (string)($r['status'] ?? 'Pendente');
                                            $statusNorm = normalizarStatusLogistica($status);
                                            $prio = strtolower((string)($r['prioridade'] ?? 'normal'));
                                        ?>
                                        <tr>
                                            <td><?= (int)($r['id'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars((string)($r['codigo'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($r['origem'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($r['destino'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($r['item'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($r['quantidade'] ?? '0')) ?> <?= htmlspecialchars((string)($r['unidade'] ?? '')) ?></td>
                                            <td><span class="pill <?= $prio === 'urgente' ? 'danger' : ($prio === 'alta' ? 'warn' : 'info') ?>"><?= htmlspecialchars((string)($r['prioridade'] ?? 'Normal')) ?></span></td>
                                            <td><span class="pill <?= badgeStatusLogistica($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                                            <td><?= htmlspecialchars((string)($r['data_requisicao'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($r['responsavel'] ?? '-')) ?></td>
                                            <td>
                                                <form method="POST" action="?view=requisicoes&mode=list" class="actions">
                                                    <input type="hidden" name="acao" value="mudar_status">
                                                    <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                                                    <?php if ($statusNorm === 'pendente'): ?>
                                                        <button class="btn secondary" type="submit" name="novo_status" value="em transito">Despachar</button>
                                                    <?php elseif ($statusNorm === 'em transito'): ?>
                                                        <button class="btn secondary" type="submit" name="novo_status" value="entregue">Entregue</button>
                                                    <?php endif; ?>
                                                    <?php if ($statusNorm !== 'entregue' && $statusNorm !== 'cancelada'): ?>
                                                        <button class="btn secondary" type="submit" name="novo_status" value="cancelada">Cancelar</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <h3 style="margin-bottom:10px;">Painel de operacoes logisticas</h3>
                <p style="font-size:12px; color:#64748b; margin-bottom:10px;">Visao rapida das requisicoes e estado de entrega.</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Origem</th>
                                <th>Destino</th>
                                <th>Item</th>
                                <th>Status</th>
                                <th>Prioridade</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requisicoes) === 0): ?>
                                <tr><td colspan="7" style="text-align:center; color:#64748b; padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($requisicoes, 0, 12) as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($r['codigo'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['origem'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['destino'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['item'] ?? '-')) ?></td>
                                        <td><span class="pill <?= badgeStatusLogistica((string)($r['status'] ?? 'Pendente')) ?>"><?= htmlspecialchars((string)($r['status'] ?? 'Pendente')) ?></span></td>
                                        <td><?= htmlspecialchars((string)($r['prioridade'] ?? 'Normal')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['data_requisicao'] ?? '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
