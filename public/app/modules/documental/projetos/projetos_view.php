<?php
$projectsBase = [
    ['nome' => 'Corredor Norte', 'codigo' => 'PRJ-001', 'responsavel' => 'Michael', 'status' => 'Ativo', 'data_inicio' => '2026-01-05'],
    ['nome' => 'Terminal Cargo Sul', 'codigo' => 'PRJ-002', 'responsavel' => 'Ana Langa', 'status' => 'Em andamento', 'data_inicio' => '2025-12-10'],
    ['nome' => 'Plano Oficina Central', 'codigo' => 'PRJ-003', 'responsavel' => 'Carlos Mussa', 'status' => 'Concluido', 'data_inicio' => '2025-08-20'],
    ['nome' => 'Rota Mina Tete', 'codigo' => 'PRJ-004', 'responsavel' => 'Joao Matola', 'status' => 'Atrasado', 'data_inicio' => '2025-11-02'],
    ['nome' => 'Integracao RFID', 'codigo' => 'PRJ-005', 'responsavel' => 'Michael', 'status' => 'Ativo', 'data_inicio' => '2026-02-01'],
    ['nome' => 'Base Logistica Nampula', 'codigo' => 'PRJ-006', 'responsavel' => 'Sara Nhantumbo', 'status' => 'Concluido', 'data_inicio' => '2025-06-18'],
    ['nome' => 'Expansao Patio Norte', 'codigo' => 'PRJ-007', 'responsavel' => 'David Simango', 'status' => 'Em andamento', 'data_inicio' => '2026-01-14'],
    ['nome' => 'Programa Compliance', 'codigo' => 'PRJ-008', 'responsavel' => 'Helena Jose', 'status' => 'Suspenso', 'data_inicio' => '2025-10-03'],
    ['nome' => 'Revamp Abastecimento', 'codigo' => 'PRJ-009', 'responsavel' => 'Michael', 'status' => 'Ativo', 'data_inicio' => '2026-02-12'],
    ['nome' => 'Hub Industrial Beira', 'codigo' => 'PRJ-010', 'responsavel' => 'Tomaz Chongo', 'status' => 'Atrasado', 'data_inicio' => '2025-09-27'],
    ['nome' => 'Modernizacao Documental', 'codigo' => 'PRJ-011', 'responsavel' => 'Ana Langa', 'status' => 'Concluido', 'data_inicio' => '2025-07-07'],
    ['nome' => 'Operacao Sul Integrada', 'codigo' => 'PRJ-012', 'responsavel' => 'Joao Matola', 'status' => 'Em andamento', 'data_inicio' => '2026-01-29'],
];

$applyFilter = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';
$search = trim((string)($_GET['q'] ?? ''));
$projectFilter = trim((string)($_GET['projeto'] ?? 'todos'));
$statusFilter = trim((string)($_GET['status'] ?? 'todos'));
$responsavelFilter = trim((string)($_GET['responsavel'] ?? ''));
$dataIni = trim((string)($_GET['data_ini'] ?? ''));
$dataFim = trim((string)($_GET['data_fim'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'nome'));
$dir = strtolower(trim((string)($_GET['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;

$projectOptions = [];
foreach ($projectsBase as $item) {
    $projectOptions[$item['nome']] = true;
}
$projectOptions = array_keys($projectOptions);
sort($projectOptions);

$responsavelOptions = [];
foreach ($projectsBase as $item) {
    $responsavelOptions[$item['responsavel']] = true;
}
$responsavelOptions = array_keys($responsavelOptions);
sort($responsavelOptions);

$filtered = [];
if ($applyFilter) {
    foreach ($projectsBase as $item) {
        $matchSearch = $search === '' || stripos($item['nome'] . ' ' . $item['codigo'] . ' ' . $item['responsavel'], $search) !== false;
        $matchProjeto = $projectFilter === 'todos' || $item['nome'] === $projectFilter;
        $matchStatus = $statusFilter === 'todos' || strcasecmp($item['status'], $statusFilter) === 0;
        $matchResponsavel = $responsavelFilter === '' || strcasecmp($item['responsavel'], $responsavelFilter) === 0;
        $matchDataIni = $dataIni === '' || $item['data_inicio'] >= $dataIni;
        $matchDataFim = $dataFim === '' || $item['data_inicio'] <= $dataFim;

        if ($matchSearch && $matchProjeto && $matchStatus && $matchResponsavel && $matchDataIni && $matchDataFim) {
            $filtered[] = $item;
        }
    }
}

$sortable = ['nome', 'codigo', 'responsavel', 'status', 'data_inicio'];
if (!in_array($sort, $sortable, true)) {
    $sort = 'nome';
}

if ($applyFilter) {
    usort($filtered, static function (array $a, array $b) use ($sort, $dir): int {
        $cmp = strcmp((string)$a[$sort], (string)$b[$sort]);
        return $dir === 'desc' ? -$cmp : $cmp;
    });
}

$totalFiltered = count($filtered);
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$rows = $applyFilter ? array_slice($filtered, $offset, $perPage) : [];

$metricsTotal = count($projectsBase);
$metricsAtivo = 0;
$metricsConcluido = 0;
$metricsAtrasado = 0;
foreach ($projectsBase as $item) {
    if ($item['status'] === 'Ativo') {
        $metricsAtivo++;
    }
    if ($item['status'] === 'Concluido') {
        $metricsConcluido++;
    }
    if ($item['status'] === 'Atrasado') {
        $metricsAtrasado++;
    }
}

$baseQuery = [
    'view' => 'projetos',
    'aplicar' => '1',
    'q' => $search,
    'projeto' => $projectFilter,
    'status' => $statusFilter,
    'responsavel' => $responsavelFilter,
    'data_ini' => $dataIni,
    'data_fim' => $dataFim,
];

$sortQuery = static function (string $field) use ($baseQuery, $sort, $dir): string {
    $nextDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
    $query = array_merge($baseQuery, ['sort' => $field, 'dir' => $nextDir, 'page' => 1]);
    return '?' . http_build_query($query);
};

$pageQuery = static function (int $targetPage) use ($baseQuery, $sort, $dir): string {
    $query = array_merge($baseQuery, ['sort' => $sort, 'dir' => $dir, 'page' => $targetPage]);
    return '?' . http_build_query($query);
};
?>

<style>
    .projects-enterprise {
        --bg: #f8f9fb;
        --surface: #ffffff;
        --ink: #0f172a;
        --muted: #64748b;
        --primary: #0d3b88;
        --primary-dark: #0b275a;
        --line: #e2e8f0;
        --radius: 14px;
        --shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        background: transparent;
        border: none;
        border-radius: 0;
        padding: 0;
    }

    .projects-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .projects-breadcrumb {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .projects-title {
        margin: 0;
        font-size: 28px;
        line-height: 1.15;
        color: var(--ink);
        letter-spacing: -0.02em;
    }

    .header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .icon-btn,
    .profile-btn {
        border: 1px solid var(--line);
        background: var(--surface);
        color: #334155;
        border-radius: 10px;
        min-height: 40px;
        padding: 0 12px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s ease;
    }

    .icon-btn:hover,
    .profile-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.12);
        border-color: #cbd5e1;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .metric-card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        padding: 14px;
        box-shadow: var(--shadow);
    }

    .metric-label {
        font-size: 11px;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 6px;
        font-weight: 700;
    }

    .metric-value {
        margin: 0;
        font-size: 26px;
        font-weight: 800;
        color: var(--ink);
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(240px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .quick-card {
        border-radius: 14px;
        border: 1px solid #dbe5f8;
        background: linear-gradient(160deg, #ffffff 0%, #f6f9ff 100%);
        box-shadow: var(--shadow);
        padding: 16px;
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .quick-card--add {
        border-color: #d8eee6;
        background: linear-gradient(160deg, #ffffff 0%, #f1fbf7 100%);
    }

    .quick-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(13, 59, 136, 0.14);
    }

    .quick-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background: var(--primary);
        margin-bottom: 10px;
    }

    .quick-card--add .quick-icon {
        background: #0f766e;
    }

    .quick-title {
        margin: 0 0 6px;
        color: var(--ink);
        font-size: 17px;
    }

    .quick-text {
        margin: 0 0 12px;
        color: var(--muted);
        font-size: 13px;
    }

    .btn-primary,
    .btn-outline {
        border-radius: 10px;
        min-height: 40px;
        padding: 0 14px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all .2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary {
        background: var(--primary);
        border: 1px solid var(--primary);
        color: #ffffff;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    .btn-outline {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
    }

    .filters-card,
    .results-card,
    .new-project-card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    .filters-card {
        padding: 16px;
        margin-bottom: 16px;
    }

    .filters-title {
        margin: 0 0 2px;
        font-size: 16px;
        color: var(--ink);
    }

    .filters-subtitle {
        margin: 0 0 14px;
        font-size: 12px;
        color: var(--muted);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(120px, 1fr));
        gap: 10px;
    }

    .form-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-field label {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .form-field input,
    .form-field select {
        min-height: 38px;
        border: 1px solid #d6deea;
        border-radius: 10px;
        padding: 0 12px;
        font-size: 13px;
        background: #ffffff;
        color: #0f172a;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .form-field input:focus,
    .form-field select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(13, 59, 136, 0.12);
        outline: none;
    }

    .filter-actions {
        grid-column: span 6;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 4px;
    }

    .filter-state {
        margin-top: 12px;
        padding: 12px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 13px;
    }

    .new-project-card {
        display: none;
        margin-bottom: 16px;
        padding: 16px;
    }

    .new-project-card.visible {
        display: block;
    }

    .new-project-grid {
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
    }

    .results-card {
        padding: 0;
        overflow: hidden;
    }

    .results-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
    }

    .results-top h4 {
        margin: 0;
        font-size: 16px;
        color: var(--ink);
    }

    .results-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .table-wrap {
        overflow-x: auto;
    }

    .projects-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }

    .projects-table th,
    .projects-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #edf2f7;
        font-size: 13px;
        color: #1e293b;
        text-align: left;
        vertical-align: middle;
    }

    .projects-table th a {
        color: #334155;
        text-decoration: none;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .projects-table th a:hover {
        color: var(--primary);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        border-radius: 999px;
        padding: 0 10px;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: .02em;
        border: 1px solid transparent;
    }

    .status-ativo { color: #166534; background: #ecfdf3; border-color: #bbf7d0; }
    .status-em-andamento { color: #9a6a00; background: #fff8e7; border-color: #fde68a; }
    .status-concluido { color: #0c4a6e; background: #ecfeff; border-color: #a5f3fc; }
    .status-atrasado,
    .status-suspenso { color: #991b1b; background: #fef2f2; border-color: #fecaca; }

    .row-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .action-link {
        border: 1px solid #d4deec;
        background: #ffffff;
        color: #334155;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        min-height: 30px;
        padding: 0 10px;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
    }

    .action-link:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: #f8fafc;
        border-top: 1px solid var(--line);
    }

    .pagination-links {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .page-link {
        min-width: 34px;
        height: 34px;
        border: 1px solid #d4deec;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        background: #ffffff;
    }

    .page-link.current {
        background: var(--primary);
        color: #ffffff;
        border-color: var(--primary);
    }

    .skeleton {
        display: none;
        margin-top: 10px;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 12px;
        background: #ffffff;
    }

    .skeleton.visible {
        display: block;
    }

    .skeleton-line {
        height: 12px;
        border-radius: 999px;
        background: linear-gradient(90deg, #e2e8f0 0%, #f1f5f9 50%, #e2e8f0 100%);
        background-size: 300px 100%;
        animation: shimmer 1.2s infinite linear;
        margin-bottom: 10px;
    }

    @keyframes shimmer {
        from { background-position: -300px 0; }
        to { background-position: 300px 0; }
    }

    @media (max-width: 1200px) {
        .filters-grid { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
        .filter-actions { grid-column: span 3; }
        .new-project-grid { grid-template-columns: repeat(2, minmax(120px, 1fr)); }
    }

    @media (max-width: 900px) {
        .projects-enterprise { padding: 0; }
        .projects-header { flex-direction: column; }
        .metrics-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
        .quick-actions { grid-template-columns: 1fr; }
        .filters-grid { grid-template-columns: 1fr; }
        .filter-actions { grid-column: span 1; }
        .new-project-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="projects-enterprise">
    <div class="projects-header">
        <div>
            <div class="projects-breadcrumb">Documental &gt; Projetos &gt; Gestao</div>
            <h3 class="projects-title">Gestao de Projetos</h3>
        </div>
        <div class="header-actions">
            <button class="icon-btn" type="button"><i class="fas fa-magnifying-glass"></i> Pesquisa global</button>
            <button class="icon-btn" type="button"><i class="fas fa-bell"></i> Notificacoes</button>
            <button class="profile-btn" type="button"><i class="fas fa-user-circle"></i> Michael <i class="fas fa-angle-down"></i></button>
        </div>
    </div>

    <div class="metrics-grid">
        <article class="metric-card">
            <div class="metric-label">Total de Projetos</div>
            <p class="metric-value"><?= $metricsTotal ?></p>
        </article>
        <article class="metric-card">
            <div class="metric-label">Projetos Ativos</div>
            <p class="metric-value"><?= $metricsAtivo ?></p>
        </article>
        <article class="metric-card">
            <div class="metric-label">Projetos Concluidos</div>
            <p class="metric-value"><?= $metricsConcluido ?></p>
        </article>
        <article class="metric-card">
            <div class="metric-label">Projetos Atrasados</div>
            <p class="metric-value"><?= $metricsAtrasado ?></p>
        </article>
    </div>

    <section class="quick-actions">
        <article class="quick-card">
            <span class="quick-icon"><i class="fas fa-list"></i></span>
            <h4 class="quick-title">Lista de Projetos</h4>
            <p class="quick-text">Visualizar todos os projetos cadastrados.</p>
            <button class="btn-outline" type="button" data-scroll-results>
                <i class="fas fa-table"></i> Abrir Lista
            </button>
        </article>
        <article class="quick-card quick-card--add">
            <span class="quick-icon"><i class="fas fa-plus"></i></span>
            <h4 class="quick-title">Adicionar Projeto</h4>
            <p class="quick-text">Cadastrar um novo projeto com estrutura padrao corporativa.</p>
            <button class="btn-primary" type="button" data-toggle-new-project>
                <i class="fas fa-plus"></i> Adicionar Projeto
            </button>
        </article>
    </section>

    <section class="new-project-card" id="novoProjetoCard">
        <h4 class="filters-title">Novo Projeto</h4>
        <p class="filters-subtitle">Preenchimento rapido para cadastro inicial.</p>
        <form class="new-project-grid" onsubmit="return false;">
            <div class="form-field">
                <label>Nome do Projeto</label>
                <input type="text" placeholder="Ex: Corredor Sul Integrado">
            </div>
            <div class="form-field">
                <label>Codigo</label>
                <input type="text" placeholder="Ex: PRJ-013">
            </div>
            <div class="form-field">
                <label>Responsavel</label>
                <input type="text" placeholder="Ex: Michael">
            </div>
            <div class="form-field">
                <label>Data de Inicio</label>
                <input type="date">
            </div>
            <div class="form-field" style="grid-column: 1 / -1;">
                <button type="button" class="btn-primary"><i class="fas fa-floppy-disk"></i> Guardar Projeto</button>
            </div>
        </form>
    </section>

    <section class="filters-card">
        <h4 class="filters-title">Filtros Avancados</h4>
        <p class="filters-subtitle">Aplique criterios estrategicos para analisar o portfolio de projetos.</p>

        <form method="get" action="" class="filters-grid" id="projectFiltersForm">
            <input type="hidden" name="view" value="projetos">
            <input type="hidden" name="aplicar" value="1">

            <div class="form-field">
                <label>Projeto</label>
                <select name="projeto">
                    <option value="todos">Todos</option>
                    <?php foreach ($projectOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= $projectFilter === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Status</label>
                <select name="status">
                    <option value="todos" <?= $statusFilter === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="Ativo" <?= $statusFilter === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="Em andamento" <?= $statusFilter === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                    <option value="Concluido" <?= $statusFilter === 'Concluido' ? 'selected' : '' ?>>Concluido</option>
                    <option value="Suspenso" <?= $statusFilter === 'Suspenso' ? 'selected' : '' ?>>Suspenso</option>
                    <option value="Atrasado" <?= $statusFilter === 'Atrasado' ? 'selected' : '' ?>>Atrasado</option>
                </select>
            </div>

            <div class="form-field">
                <label>Responsavel</label>
                <select name="responsavel">
                    <option value="">Todos</option>
                    <?php foreach ($responsavelOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= $responsavelFilter === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label>Data inicial</label>
                <input type="date" name="data_ini" value="<?= htmlspecialchars($dataIni) ?>">
            </div>

            <div class="form-field">
                <label>Data final</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
            </div>

            <div class="form-field">
                <label>Pesquisa</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nome, codigo ou responsavel">
            </div>

            <div class="filter-actions">
                <button class="btn-primary" type="submit" id="applyFiltersBtn"><i class="fas fa-filter"></i> Aplicar Filtros</button>
                <a class="btn-outline" href="?view=projetos"><i class="fas fa-rotate-left"></i> Limpar</a>
            </div>
        </form>

        <?php if (!$applyFilter): ?>
            <div class="filter-state">A lista de projetos sera exibida apos a aplicacao dos filtros.</div>
        <?php elseif ($applyFilter && $totalFiltered === 0): ?>
            <div class="filter-state">Nenhum projeto encontrado com os criterios selecionados.</div>
        <?php else: ?>
            <div class="filter-state">Foram encontrados <?= $totalFiltered ?> projetos com os filtros atuais.</div>
        <?php endif; ?>

        <div class="skeleton" id="filterSkeleton">
            <div class="skeleton-line" style="width: 96%;"></div>
            <div class="skeleton-line" style="width: 84%;"></div>
            <div class="skeleton-line" style="width: 92%;"></div>
            <div class="skeleton-line" style="width: 76%; margin-bottom: 0;"></div>
        </div>
    </section>

    <section class="results-card" id="resultsSection">
        <div class="results-top">
            <h4>Resultados</h4>
            <div class="results-actions">
                <button type="button" class="btn-outline" id="exportExcel"><i class="fas fa-file-excel"></i> Exportar Excel</button>
                <button type="button" class="btn-outline" onclick="window.print();"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="projects-table" id="projectsTable">
                <thead>
                    <tr>
                        <th><a href="<?= htmlspecialchars($sortQuery('nome')) ?>">Nome do Projeto</a></th>
                        <th><a href="<?= htmlspecialchars($sortQuery('codigo')) ?>">Codigo</a></th>
                        <th><a href="<?= htmlspecialchars($sortQuery('responsavel')) ?>">Responsavel</a></th>
                        <th><a href="<?= htmlspecialchars($sortQuery('status')) ?>">Status</a></th>
                        <th><a href="<?= htmlspecialchars($sortQuery('data_inicio')) ?>">Data de Inicio</a></th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$applyFilter): ?>
                        <tr><td colspan="6">Aplique os filtros para carregar a lista de projetos.</td></tr>
                    <?php elseif (empty($rows)): ?>
                        <tr><td colspan="6">Nenhum projeto encontrado com os criterios selecionados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($row['codigo']) ?></td>
                                <td><?= htmlspecialchars($row['responsavel']) ?></td>
                                <td><span class="status-badge <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td><?= htmlspecialchars($row['data_inicio']) ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="#" class="action-link">Editar</a>
                                        <a href="#" class="action-link">Ver</a>
                                        <a href="#" class="action-link">Excluir</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span>Pagina <?= $page ?> de <?= $totalPages ?></span>
            <div class="pagination-links">
                <?php if ($applyFilter): ?>
                    <a class="page-link <?= $page <= 1 ? 'current' : '' ?>" href="<?= htmlspecialchars($pageQuery(max(1, $page - 1))) ?>">&lt;</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="page-link <?= $i === $page ? 'current' : '' ?>" href="<?= htmlspecialchars($pageQuery($i)) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a class="page-link <?= $page >= $totalPages ? 'current' : '' ?>" href="<?= htmlspecialchars($pageQuery(min($totalPages, $page + 1))) ?>">&gt;</a>
                <?php else: ?>
                    <span class="page-link current">1</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    var toggleButton = document.querySelector('[data-toggle-new-project]');
    var newProjectCard = document.getElementById('novoProjetoCard');
    if (toggleButton && newProjectCard) {
        toggleButton.addEventListener('click', function () {
            newProjectCard.classList.toggle('visible');
            if (newProjectCard.classList.contains('visible')) {
                newProjectCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    var scrollResultsButton = document.querySelector('[data-scroll-results]');
    var resultsSection = document.getElementById('resultsSection');
    if (scrollResultsButton && resultsSection) {
        scrollResultsButton.addEventListener('click', function () {
            resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    var filterForm = document.getElementById('projectFiltersForm');
    var filterSkeleton = document.getElementById('filterSkeleton');
    var applyButton = document.getElementById('applyFiltersBtn');
    if (filterForm && filterSkeleton && applyButton) {
        filterForm.addEventListener('submit', function () {
            filterSkeleton.classList.add('visible');
            applyButton.setAttribute('disabled', 'disabled');
            applyButton.style.opacity = '0.75';
        });
    }

    var exportButton = document.getElementById('exportExcel');
    var table = document.getElementById('projectsTable');
    if (exportButton && table) {
        exportButton.addEventListener('click', function () {
            var rows = table.querySelectorAll('tr');
            var csv = [];
            rows.forEach(function (row) {
                var cols = row.querySelectorAll('th, td');
                var data = [];
                cols.forEach(function (col, idx) {
                    if (idx === cols.length - 1) {
                        return;
                    }
                    data.push('"' + col.innerText.replace(/"/g, '""').trim() + '"');
                });
                if (data.length) {
                    csv.push(data.join(','));
                }
            });
            var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'projetos_documental.csv';
            link.click();
            URL.revokeObjectURL(link.href);
        });
    }
})();
</script>
