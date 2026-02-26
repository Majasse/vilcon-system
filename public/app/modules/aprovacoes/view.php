<?php
$reqOficina = $pdo->query("SELECT id, codigo, origem, destino, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes, valor_total, custo_total FROM logistica_requisicoes WHERE LOWER(status)='pendente' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$reqTransporte = $pdo->query("SELECT id, codigo, item_nome, quantidade_solicitada, unidade, preco_unitario_estimado, valor_total_estimado, moeda, fornecedor_sugerido, prioridade, justificativa, solicitante, status, criado_em FROM transporte_requisicoes WHERE LOWER(status)='pendente' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$approved = isset($_GET['approved']);
$denied = isset($_GET['denied']);
$activeTab = strtolower(trim((string)($_GET['tab'] ?? 'oficina')));
if (!in_array($activeTab, ['oficina', 'transporte'], true)) {
    $activeTab = 'oficina';
}

$statusRawOficina = $pdo->query("SELECT LOWER(COALESCE(status, '')) AS st, COUNT(*) AS total FROM logistica_requisicoes GROUP BY LOWER(COALESCE(status, ''))")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$statusRawTransporte = $pdo->query("SELECT LOWER(COALESCE(status, '')) AS st, COUNT(*) AS total FROM transporte_requisicoes GROUP BY LOWER(COALESCE(status, ''))")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$bucketMap = [
    'pendente' => 'pendentes',
    'pendentes' => 'pendentes',
    'aprovada' => 'aprovadas',
    'aprovado' => 'aprovadas',
    'aprovadas' => 'aprovadas',
    'aprovados' => 'aprovadas',
    'negada' => 'rejeitadas',
    'negado' => 'rejeitadas',
    'rejeitada' => 'rejeitadas',
    'rejeitado' => 'rejeitadas',
    'recusada' => 'rejeitadas',
    'recusado' => 'rejeitadas',
];

$stats = ['pendentes' => 0, 'aprovadas' => 0, 'rejeitadas' => 0, 'total' => 0];
foreach ([$statusRawOficina, $statusRawTransporte] as $source) {
    foreach ($source as $row) {
        $status = strtolower(trim((string)($row['st'] ?? '')));
        $amount = (int)($row['total'] ?? 0);
        $stats['total'] += $amount;
        if (isset($bucketMap[$status])) {
            $stats[$bucketMap[$status]] += $amount;
        }
    }
}

$totalPendentes = count($reqOficina) + count($reqTransporte);
$userLabel = trim((string)($_SESSION['usuario_nome'] ?? 'Utilizador'));
$userLabel = preg_replace('/^\s*oficina\b[\s:\-]*/iu', '', $userLabel);
if ($userLabel === '') {
    $userLabel = 'Utilizador';
}
?>

<div class="main-content">
    <div class="top-bar">
        <h2>Aprovações</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    </div>

    <div class="dashboard-container aprov-clean">
        <style>
            .aprov-clean {
                --bg: #f8f9fb;
                --surface: #ffffff;
                --line: #e5e7eb;
                --ink: #0f172a;
                --muted: #64748b;
                --primary: #1d4ed8;
                --radius: 14px;
                --shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
                background: var(--bg);
                padding: 20px;
                border-radius: 16px;
                border: 1px solid #edf2f7;
            }

            .aprov-top {
                margin-bottom: 16px;
            }

            .aprov-title {
                margin: 0;
                font-size: 28px;
                line-height: 1.1;
                color: var(--ink);
                letter-spacing: -0.02em;
            }

            .aprov-subtitle {
                margin: 6px 0 0;
                color: var(--muted);
                font-size: 13px;
            }

            .alerts {
                margin-bottom: 12px;
            }

            .alert {
                border-radius: 10px;
                padding: 10px 12px;
                border: 1px solid transparent;
                font-size: 13px;
                margin-bottom: 8px;
            }

            .alert.ok {
                color: #14532d;
                background: #f0fdf4;
                border-color: #bbf7d0;
            }

            .alert.er {
                color: #7f1d1d;
                background: #fef2f2;
                border-color: #fecaca;
            }

            .summary-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(150px, 1fr));
                gap: 12px;
                margin-bottom: 16px;
            }

            .summary-card {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                padding: 14px;
            }

            .summary-label {
                margin: 0 0 8px;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--muted);
                font-weight: 700;
            }

            .summary-value {
                margin: 0;
                font-size: 34px;
                line-height: 1;
                color: var(--ink);
                font-weight: 800;
            }

            .summary-card.pending { border-left: 4px solid #f59e0b; }
            .summary-card.approved { border-left: 4px solid #16a34a; }
            .summary-card.rejected { border-left: 4px solid #dc2626; }
            .summary-card.total { border-left: 4px solid #2563eb; }

            .tabbar {
                display: flex;
                gap: 8px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }

            .tab-link {
                text-decoration: none;
                min-height: 38px;
                padding: 0 14px;
                border-radius: 999px;
                border: 1px solid #dbe3ee;
                color: #334155;
                background: #ffffff;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .tab-link.active {
                background: #0f172a;
                border-color: #0f172a;
                color: #ffffff;
            }

            .table-card {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                overflow: hidden;
            }

            .table-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                padding: 14px;
                border-bottom: 1px solid var(--line);
                background: #fcfdff;
            }

            .search-input {
                width: 260px;
                max-width: 100%;
                min-height: 36px;
                border: 1px solid #d6deea;
                border-radius: 10px;
                padding: 0 12px;
                font-size: 13px;
            }

            .search-input:focus {
                outline: none;
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            }

            .table-wrap {
                overflow-x: auto;
            }

            .aprov-table {
                width: 100%;
                min-width: 860px;
                border-collapse: collapse;
            }

            .aprov-table th,
            .aprov-table td {
                text-align: left;
                padding: 12px 14px;
                border-bottom: 1px solid #edf2f7;
                font-size: 13px;
                color: #1e293b;
                vertical-align: middle;
            }

            .aprov-table th {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: #64748b;
                background: #f8fafc;
            }

            .prio {
                display: inline-flex;
                min-height: 24px;
                align-items: center;
                border-radius: 999px;
                padding: 0 9px;
                font-size: 11px;
                font-weight: 700;
                border: 1px solid transparent;
            }

            .prio-alta,
            .prio-urgente { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
            .prio-media,
            .prio-normal { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
            .prio-baixa { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }

            .actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }

            .icon-action {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                border: 1px solid #d6deea;
                background: #ffffff;
                color: #334155;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
            }

            .icon-action.approve { border-color: #bbf7d0; color: #166534; background: #f0fdf4; }
            .icon-action.reject { border-color: #fecaca; color: #991b1b; background: #fef2f2; }
            .icon-action.details { border-color: #dbeafe; color: #1d4ed8; background: #eff6ff; }

            .empty-state {
                padding: 40px 16px;
                text-align: center;
                background: #ffffff;
            }

            .empty-state i {
                font-size: 36px;
                color: #94a3b8;
                margin-bottom: 10px;
                display: inline-block;
            }

            .empty-state h4 {
                margin: 0;
                font-size: 18px;
                color: #0f172a;
            }

            .empty-actions {
                margin-top: 12px;
            }

            .btn-refresh {
                border: 1px solid #d6deea;
                background: #ffffff;
                color: #334155;
                min-height: 36px;
                border-radius: 10px;
                padding: 0 12px;
                text-decoration: none;
                font-size: 12px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                gap: 7px;
            }

            .modal {
                position: fixed;
                inset: 0;
                z-index: 1200;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 16px;
                background: rgba(2, 6, 23, 0.45);
            }

            .modal.open {
                display: flex;
            }

            .modal-card {
                width: min(620px, 100%);
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                box-shadow: 0 20px 44px rgba(15, 23, 42, 0.22);
                overflow: hidden;
            }

            .modal-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 14px;
                border-bottom: 1px solid #e2e8f0;
            }

            .modal-head h5 {
                margin: 0;
                font-size: 15px;
                color: #0f172a;
            }

            .modal-close {
                border: 1px solid #d6deea;
                background: #ffffff;
                color: #334155;
                width: 30px;
                height: 30px;
                border-radius: 8px;
                cursor: pointer;
            }

            .modal-body {
                padding: 14px;
                display: grid;
                grid-template-columns: repeat(2, minmax(120px, 1fr));
                gap: 10px;
            }

            .detail-item {
                border: 1px solid #edf2f7;
                border-radius: 10px;
                padding: 10px;
                background: #f8fafc;
            }

            .detail-item span {
                display: block;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: #64748b;
                margin-bottom: 5px;
                font-weight: 700;
            }

            .detail-item strong {
                font-size: 13px;
                color: #0f172a;
                font-weight: 700;
            }

            .hidden-row {
                display: none;
            }

            @media (max-width: 1024px) {
                .summary-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
            }

            @media (max-width: 820px) {
                .aprov-clean { padding: 14px; }
                .summary-grid { grid-template-columns: 1fr; }
                .table-head { flex-direction: column; align-items: stretch; }
                .search-input { width: 100%; }
                .modal-body { grid-template-columns: 1fr; }
            }
        </style>

        <div class="aprov-top">
            <div>
                <h2 class="aprov-title">Aprovacoes - Logistica</h2>
                <p class="aprov-subtitle">Painel de deliberacao executiva</p>
            </div>
        </div>

        <div class="alerts">
            <?php if ($erro !== ''): ?><div class="alert er"><?= h($erro) ?></div><?php endif; ?>
            <?php if ($approved): ?><div class="alert ok">Requisicao aprovada.</div><?php endif; ?>
            <?php if ($denied): ?><div class="alert ok">Requisicao rejeitada.</div><?php endif; ?>
        </div>

        <section class="summary-grid">
            <article class="summary-card pending">
                <p class="summary-label">Pendentes</p>
                <p class="summary-value"><?= (int)$stats['pendentes'] ?></p>
            </article>
            <article class="summary-card approved">
                <p class="summary-label">Aprovadas</p>
                <p class="summary-value"><?= (int)$stats['aprovadas'] ?></p>
            </article>
            <article class="summary-card rejected">
                <p class="summary-label">Rejeitadas</p>
                <p class="summary-value"><?= (int)$stats['rejeitadas'] ?></p>
            </article>
            <article class="summary-card total">
                <p class="summary-label">Total</p>
                <p class="summary-value"><?= (int)$stats['total'] ?></p>
            </article>
        </section>

        <nav class="tabbar">
            <a class="tab-link <?= $activeTab === 'oficina' ? 'active' : '' ?>" href="?tab=oficina">Oficina</a>
            <a class="tab-link <?= $activeTab === 'transporte' ? 'active' : '' ?>" href="?tab=transporte">Transporte</a>
        </nav>

        <?php if ($totalPendentes === 0): ?>
            <section class="table-card empty-state">
                <i class="fas fa-inbox"></i>
                <h4>Sem requisicoes pendentes</h4>
                <div class="empty-actions">
                    <a class="btn-refresh" href="?tab=<?= h($activeTab) ?>"><i class="fas fa-rotate-right"></i> Atualizar</a>
                </div>
            </section>
        <?php else: ?>
            <?php $rows = $activeTab === 'oficina' ? $reqOficina : $reqTransporte; ?>
            <?php if (!$rows): ?>
                <section class="table-card empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h4>Nenhuma pendencia</h4>
                    <div class="empty-actions">
                        <a class="btn-refresh" href="?tab=<?= h($activeTab) ?>"><i class="fas fa-rotate-right"></i> Atualizar</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="table-card">
                    <header class="table-head">
                        <strong><?= $activeTab === 'oficina' ? 'Oficina' : 'Transporte' ?></strong>
                        <input type="search" id="approvalSearch" class="search-input" placeholder="Pesquisar codigo, solicitante ou item">
                    </header>
                    <div class="table-wrap">
                        <table class="aprov-table" id="approvalTable">
                            <thead>
                                <tr>
                                    <th>Codigo</th>
                                    <th>Solicitante</th>
                                    <th>Item</th>
                                    <th>Quantidade</th>
                                    <th>Prioridade</th>
                                    <th>Custo</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $modalBlocks = []; foreach ($rows as $row): ?>
                                    <?php
                                        $id = (int)($row['id'] ?? 0);
                                        if ($activeTab === 'oficina') {
                                            $codigo = (string)($row['codigo'] ?? '-');
                                            $solicitante = (string)($row['responsavel'] ?? '-');
                                            $item = (string)($row['item'] ?? '-');
                                            $quantidade = m($row['quantidade'] ?? 0) . ' ' . h($row['unidade'] ?? 'un');
                                            $prioridade = trim((string)($row['prioridade'] ?? 'Normal'));
                                            $custoValor = (float)($row['custo_total'] ?? 0);
                                            if ($custoValor <= 0) {
                                                $custoValor = (float)($row['valor_total'] ?? 0);
                                            }
                                            $custo = 'MZN ' . m($custoValor);
                                            $detail = [
                                                'codigo' => $codigo,
                                                'solicitante' => $solicitante,
                                                'origem' => (string)($row['origem'] ?? '-'),
                                                'destino' => (string)($row['destino'] ?? '-'),
                                                'item' => $item,
                                                'quantidade' => strip_tags($quantidade),
                                                'prioridade' => $prioridade,
                                                'observacao' => (string)($row['observacoes'] ?? '-'),
                                                'custo' => $custo,
                                            ];
                                        } else {
                                            $codigo = (string)($row['codigo'] ?? '-');
                                            $solicitante = (string)($row['solicitante'] ?? '-');
                                            $item = (string)($row['item_nome'] ?? '-');
                                            $quantidade = m($row['quantidade_solicitada'] ?? 0) . ' ' . h($row['unidade'] ?? 'un');
                                            $prioridade = trim((string)($row['prioridade'] ?? 'Normal'));
                                            $custo = h((string)($row['moeda'] ?? 'MZN')) . ' ' . m($row['valor_total_estimado'] ?? 0);
                                            $detail = [
                                                'codigo' => $codigo,
                                                'solicitante' => $solicitante,
                                                'fornecedor' => (string)($row['fornecedor_sugerido'] ?? '-'),
                                                'item' => $item,
                                                'quantidade' => strip_tags($quantidade),
                                                'prioridade' => $prioridade,
                                                'justificativa' => (string)($row['justificativa'] ?? '-'),
                                                'custo' => strip_tags($custo),
                                            ];
                                        }
                                        $prioClass = 'prio-' . strtolower(str_replace(' ', '-', $prioridade));
                                        $modalId = 'detail-' . $activeTab . '-' . $id;
                                    ?>
                                    <tr data-search="<?= h(strtolower($codigo . ' ' . $solicitante . ' ' . $item)) ?>">
                                        <td><strong><?= h($codigo) ?></strong></td>
                                        <td><?= h($solicitante) ?></td>
                                        <td><?= h($item) ?></td>
                                        <td><?= $quantidade ?></td>
                                        <td><span class="prio <?= h($prioClass) ?>"><?= h($prioridade) ?></span></td>
                                        <td><?= $custo ?></td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" class="icon-action details" title="Detalhes" data-open-modal="<?= h($modalId) ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Confirmar aprovacao desta requisicao?');" style="display:inline;">
                                                    <input type="hidden" name="acao" value="<?= $activeTab === 'oficina' ? 'aprovar_oficina' : 'aprovar_transporte' ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <button type="submit" class="icon-action approve" title="Aprovar"><i class="fas fa-check"></i></button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Confirmar rejeicao desta requisicao?');" style="display:inline;">
                                                    <input type="hidden" name="acao" value="<?= $activeTab === 'oficina' ? 'recusar_oficina' : 'recusar_transporte' ?>">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <button type="submit" class="icon-action reject" title="Rejeitar"><i class="fas fa-xmark"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                        ob_start();
                                    ?>
                                    <div class="modal" id="<?= h($modalId) ?>" role="dialog" aria-modal="true" aria-hidden="true">
                                        <div class="modal-card">
                                            <div class="modal-head">
                                                <h5>Detalhes da requisicao</h5>
                                                <button type="button" class="modal-close" data-close-modal="<?= h($modalId) ?>">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <?php foreach ($detail as $label => $value): ?>
                                                    <div class="detail-item">
                                                        <span><?= h(ucfirst($label)) ?></span>
                                                        <strong><?= h($value) ?></strong>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                        $modalBlocks[] = ob_get_clean();
                                    ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php if (!empty($modalBlocks)): ?>
                    <?= implode("\n", $modalBlocks) ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var searchInput = document.getElementById('approvalSearch');
    var table = document.getElementById('approvalTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function () {
            var term = (searchInput.value || '').toLowerCase().trim();
            var rows = table.querySelectorAll('tbody tr[data-search]');
            rows.forEach(function (row) {
                var text = row.getAttribute('data-search') || '';
                row.classList.toggle('hidden-row', term !== '' && text.indexOf(term) === -1);
            });
        });
    }

    var openButtons = document.querySelectorAll('[data-open-modal]');
    openButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-open-modal');
            var modal = targetId ? document.getElementById(targetId) : null;
            if (modal) {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            }
        });
    });

    var closeButtons = document.querySelectorAll('[data-close-modal]');
    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-close-modal');
            var modal = targetId ? document.getElementById(targetId) : null;
            if (modal) {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    });

    var modals = document.querySelectorAll('.modal');
    modals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    });
})();
</script>

