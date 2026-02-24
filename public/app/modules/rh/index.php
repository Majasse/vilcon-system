<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

$page_title = 'RH | Vilcon System';
$view = $_GET['view'] ?? 'colaboradores';
if ($view === 'assiduidade') {
    $view = 'presencas';
}
$mode = $_GET['mode'] ?? 'list';
if (!in_array($mode, ['home', 'list'], true)) {
    $mode = 'list';
}
$aplicar_lista = (string)($_GET['aplicar'] ?? '1') === '1';
$q = trim((string)($_GET['q'] ?? ''));
$cargo_id = isset($_GET['cargo_id']) ? (int)$_GET['cargo_id'] : 0;
$funcionario_id = isset($_GET['funcionario_id']) ? (int)$_GET['funcionario_id'] : 0;
$data_presencas = trim((string)($_GET['data_presencas'] ?? ($_GET['data_assiduidade'] ?? date('Y-m-d'))));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_presencas)) {
    $data_presencas = date('Y-m-d');
}
$hist_data_rh = trim((string)($_GET['hist_data'] ?? ''));
$avaliacao_inicio = trim((string)($_GET['avaliacao_inicio'] ?? date('Y-m-01')));
$avaliacao_fim = trim((string)($_GET['avaliacao_fim'] ?? date('Y-m-d')));

$cargos = [];
$colaboradores = [];
$funcionario = null;
$funcionario_docs = [];
$erro = null;
$avaliacao_lista = [];
$totais_avaliacao = [
    'colaboradores' => 0,
    'media' => 0.0,
    'excelente' => 0,
    'bom' => 0,
    'regular' => 0,
    'fraco' => 0,
];

function garantirTabelaPresencasRh(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS oficina_presencas_rh (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data_presenca DATE NOT NULL,
            pessoal_id INT NOT NULL,
            status_presenca ENUM('Presente','Atraso','Falta','Dispensa') NOT NULL DEFAULT 'Presente',
            observacoes VARCHAR(255) NULL,
            enviado_rh TINYINT(1) NOT NULL DEFAULT 0,
            enviado_em DATETIME NULL,
            criado_por INT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_data (data_presenca),
            INDEX idx_pessoal (pessoal_id),
            INDEX idx_enviado (enviado_rh)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function obterFotoFuncionarioUrl(array $funcionario): ?string {
    $id = (int)($funcionario['id'] ?? 0);
    if ($id <= 0) return null;

    $baseDir = dirname(__DIR__, 3) . '/public/uploads/pessoal/';
    $baseUrl = '/vilcon-systemon/public/uploads/pessoal/';
    $candidatos = [
        $id . '.jpg',
        $id . '.jpeg',
        $id . '.png',
        $id . '.webp',
        'func_' . $id . '.jpg',
        'func_' . $id . '.png',
    ];

    foreach ($candidatos as $file) {
        if (is_file($baseDir . $file)) {
            return $baseUrl . $file;
        }
    }

    return null;
}

function iniciaisNome(string $nome): string {
    $nome = trim($nome);
    if ($nome === '') return 'NA';
    $partes = preg_split('/\s+/', $nome);
    $a = strtoupper(substr((string)($partes[0] ?? 'N'), 0, 1));
    $b = strtoupper(substr((string)($partes[count($partes) - 1] ?? 'A'), 0, 1));
    return $a . $b;
}

function diasIntervaloInclusive(string $inicio, string $fim): int {
    $dtIni = DateTime::createFromFormat('Y-m-d', $inicio);
    $dtFim = DateTime::createFromFormat('Y-m-d', $fim);
    if (!$dtIni || !$dtFim) return 1;
    if ($dtIni > $dtFim) {
        $tmp = $dtIni;
        $dtIni = $dtFim;
        $dtFim = $tmp;
    }
    return max(1, ((int)$dtIni->diff($dtFim)->format('%a')) + 1);
}

function nota0a5(float $valorPercentual): float {
    $normalizado = max(0.0, min(100.0, $valorPercentual));
    return round($normalizado / 20.0, 1);
}

function classificarNotaFinal(float $notaFinal): array {
    if ($notaFinal >= 32.0) return ['texto' => 'Excelente', 'classe' => 'grade-excellent', 'icone' => 'fa-solid fa-trophy'];
    if ($notaFinal >= 25.0) return ['texto' => 'Bom', 'classe' => 'grade-good', 'icone' => 'fa-solid fa-thumbs-up'];
    if ($notaFinal >= 15.0) return ['texto' => 'Regular', 'classe' => 'grade-regular', 'icone' => 'fa-solid fa-scale-balanced'];
    return ['texto' => 'Precisa melhorar', 'classe' => 'grade-needs', 'icone' => 'fa-solid fa-triangle-exclamation'];
}

try {
    garantirTabelaPresencasRh($pdo);

    $cargos = $pdo->query("SELECT id, nome FROM cargos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sql = "
        SELECT p.id, p.numero, p.nome, p.estado, p.created_at, p.cargo_id, c.nome AS cargo_nome
        FROM pessoal p
        INNER JOIN (
            SELECT
                COALESCE(NULLIF(numero, 0), id) AS chave_unica,
                MAX(id) AS id_mais_recente
            FROM pessoal
            GROUP BY COALESCE(NULLIF(numero, 0), id)
        ) ult ON ult.id_mais_recente = p.id
        LEFT JOIN cargos c ON c.id = p.cargo_id
        WHERE 1=1
    ";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (p.nome LIKE :q OR CAST(p.numero AS CHAR) LIKE :q OR c.nome LIKE :q)";
        $params['q'] = '%' . $q . '%';
    }
    if ($cargo_id > 0) {
        $sql .= " AND p.cargo_id = :cargo_id";
        $params['cargo_id'] = $cargo_id;
    }

    $sql .= " ORDER BY p.nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($funcionario_id > 0) {
        $stmtFunc = $pdo->prepare("
            SELECT p.id, p.numero, p.nome, p.estado, p.created_at, p.cargo_id, c.nome AS cargo_nome
            FROM pessoal p
            LEFT JOIN cargos c ON c.id = p.cargo_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmtFunc->execute(['id' => $funcionario_id]);
        $funcionario = $stmtFunc->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($funcionario) {
            $stmtDoc = $pdo->prepare("
                SELECT tipo_documento, data_emissao, data_vencimento, created_at
                FROM pessoal_documentos
                WHERE pessoal_id = :id
                ORDER BY id DESC
            ");
            $stmtDoc->execute(['id' => $funcionario_id]);
            $funcionario_docs = $stmtDoc->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Throwable $e) {
    $erro = 'Nao foi possivel carregar os dados de RH.';
}

$listas_presenca_dias = [];
$lista_presencas_historico_rh = [];

if ($view === 'presencas' && $erro === null) {
    try {
        $sqlDias = "
            SELECT
                apr.data_presenca,
                COUNT(*) AS total_funcionarios,
                SUM(CASE WHEN apr.status_presenca = 'Presente' THEN 1 ELSE 0 END) AS total_presentes,
                SUM(CASE WHEN apr.status_presenca <> 'Presente' THEN 1 ELSE 0 END) AS total_ausentes,
                MIN(apr.enviado_rh) AS enviado_rh_todos,
                MAX(CASE WHEN (apr.lista_fisica_anexo IS NOT NULL AND apr.lista_fisica_anexo <> '') THEN 1 ELSE 0 END) AS possui_anexo,
                MAX(apr.lista_fisica_anexo) AS lista_fisica_anexo
            FROM oficina_presencas_rh apr
            WHERE apr.data_presenca >= DATE_SUB(:data_base, INTERVAL 30 DAY)
            GROUP BY apr.data_presenca
            ORDER BY apr.data_presenca DESC
        ";
        $stmtDias = $pdo->prepare($sqlDias);
        $stmtDias->execute(['data_base' => date('Y-m-d')]);
        $listas_presenca_dias = $stmtDias->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($hist_data_rh !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hist_data_rh)) {
            $histStmt = $pdo->prepare("
                SELECT
                    apr.data_presenca,
                    p.nome AS colaborador,
                    c.nome AS cargo_nome,
                    apr.hora_entrada,
                    apr.hora_saida,
                    apr.status_presenca,
                    apr.enviado_rh
                FROM oficina_presencas_rh apr
                INNER JOIN pessoal p ON p.id = apr.pessoal_id
                LEFT JOIN cargos c ON c.id = p.cargo_id
                WHERE apr.data_presenca = :data_ref
                ORDER BY p.nome ASC
            ");
            $histStmt->execute([':data_ref' => $hist_data_rh]);
            $lista_presencas_historico_rh = $histStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $erro = 'Nao foi possivel carregar o controle de presencas recebido da Oficina.';
    }
}

if ($view === 'avaliacao' && $erro === null) {
    try {
        $dtIni = DateTime::createFromFormat('Y-m-d', $avaliacao_inicio);
        $dtFim = DateTime::createFromFormat('Y-m-d', $avaliacao_fim);
        if (!$dtIni) $dtIni = new DateTime('first day of this month');
        if (!$dtFim) $dtFim = new DateTime();
        if ($dtIni > $dtFim) {
            $tmp = $dtIni;
            $dtIni = $dtFim;
            $dtFim = $tmp;
        }
        $avaliacao_inicio = $dtIni->format('Y-m-d');
        $avaliacao_fim = $dtFim->format('Y-m-d');
        $diasPeriodo = diasIntervaloInclusive($avaliacao_inicio, $avaliacao_fim);

        $statsPorPessoa = [];
        $stmtAval = $pdo->prepare("
            SELECT
                apr.pessoal_id,
                COUNT(*) AS total_registos,
                SUM(CASE WHEN apr.status_presenca = 'Presente' THEN 1 ELSE 0 END) AS presentes,
                SUM(CASE WHEN apr.status_presenca = 'Atraso' THEN 1 ELSE 0 END) AS atrasos,
                SUM(CASE WHEN apr.status_presenca = 'Falta' THEN 1 ELSE 0 END) AS faltas,
                SUM(CASE WHEN apr.status_presenca = 'Dispensa' THEN 1 ELSE 0 END) AS dispensas,
                SUM(CASE WHEN apr.observacoes IS NOT NULL AND TRIM(apr.observacoes) <> '' THEN 1 ELSE 0 END) AS com_observacao
            FROM oficina_presencas_rh apr
            WHERE apr.enviado_rh = 1
              AND apr.data_presenca BETWEEN :ini AND :fim
            GROUP BY apr.pessoal_id
        ");
        $stmtAval->execute(['ini' => $avaliacao_inicio, 'fim' => $avaliacao_fim]);
        foreach (($stmtAval->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $statsPorPessoa[(int)$r['pessoal_id']] = $r;
        }

        $somaNotas = 0.0;
        foreach ($colaboradores as $f) {
            $id = (int)($f['id'] ?? 0);
            $st = $statsPorPessoa[$id] ?? null;

            $total = (int)($st['total_registos'] ?? 0);
            $presentes = (int)($st['presentes'] ?? 0);
            $atrasos = (int)($st['atrasos'] ?? 0);
            $faltas = (int)($st['faltas'] ?? 0);
            $dispensas = (int)($st['dispensas'] ?? 0);
            $comObs = (int)($st['com_observacao'] ?? 0);

            $taxaPresenca = $total > 0 ? (($presentes + $dispensas) / $total) * 100.0 : 0.0;
            $taxaPontualidade = ($presentes + $atrasos) > 0 ? ($presentes / ($presentes + $atrasos)) * 100.0 : 0.0;
            $taxaFaltas = $total > 0 ? ($faltas / $total) * 100.0 : 100.0;
            $taxaCobertura = $diasPeriodo > 0 ? min(100.0, ($total / $diasPeriodo) * 100.0) : 0.0;

            $notaPontualidade = nota0a5($taxaPontualidade);
            $notaQualidade = nota0a5(max(0.0, $taxaPresenca - ($taxaFaltas * 0.6)));
            $notaProdutividade = nota0a5(min(100.0, ($taxaCobertura * 0.9) + ($taxaPresenca * 0.1)));
            $notaTrabalhoEquipe = nota0a5(max(0.0, 100.0 - (($taxaFaltas * 2.0) + (($atrasos / max(1, $total)) * 100.0))));
            $notaComunicacao = nota0a5(min(100.0, (($comObs / max(1, $total)) * 100.0) + ((100.0 - $taxaFaltas) * 0.5)));
            $notaProatividade = nota0a5(min(100.0, ($taxaPresenca * 0.7) + ($taxaCobertura * 0.3)));
            $notaMetas = nota0a5(min(100.0, ($taxaPresenca * 0.5) + ($taxaPontualidade * 0.5)));

            if ($total === 0) {
                $notaPontualidade = 0.0;
                $notaQualidade = 0.0;
                $notaProdutividade = 0.0;
                $notaTrabalhoEquipe = 0.0;
                $notaComunicacao = 0.0;
                $notaProatividade = 0.0;
                $notaMetas = 0.0;
            }

            $notaFinal = round(
                $notaPontualidade + $notaQualidade + $notaProdutividade + $notaTrabalhoEquipe + $notaComunicacao + $notaProatividade + $notaMetas,
                1
            );
            $classificacao = classificarNotaFinal($notaFinal);

            $avaliacao_lista[] = [
                'numero' => (string)($f['numero'] ?? '-'),
                'nome' => (string)($f['nome'] ?? '-'),
                'cargo' => (string)($f['cargo_nome'] ?? '-'),
                'total_registos' => $total,
                'presentes' => $presentes,
                'atrasos' => $atrasos,
                'faltas' => $faltas,
                'dispensas' => $dispensas,
                'pontualidade' => $notaPontualidade,
                'qualidade' => $notaQualidade,
                'produtividade' => $notaProdutividade,
                'trabalho_equipa' => $notaTrabalhoEquipe,
                'comunicacao' => $notaComunicacao,
                'proatividade' => $notaProatividade,
                'metas' => $notaMetas,
                'nota_final' => $notaFinal,
                'classificacao' => $classificacao['texto'],
                'class_css' => $classificacao['classe'],
                'class_icone' => $classificacao['icone'],
                'sem_dados' => $total === 0,
            ];

            $totais_avaliacao['colaboradores']++;
            $somaNotas += $notaFinal;
            if ($classificacao['texto'] === 'Excelente') $totais_avaliacao['excelente']++;
            elseif ($classificacao['texto'] === 'Bom') $totais_avaliacao['bom']++;
            elseif ($classificacao['texto'] === 'Regular') $totais_avaliacao['regular']++;
            else $totais_avaliacao['fraco']++;
        }

        $totais_avaliacao['media'] = $totais_avaliacao['colaboradores'] > 0
            ? round($somaNotas / $totais_avaliacao['colaboradores'], 1)
            : 0.0;
    } catch (Throwable $e) {
        $erro = 'Nao foi possivel gerar a avaliacao automatica.';
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Recursos Humanos</h2>
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
            .tabs { display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
            .tab { padding:8px 14px; border-radius:999px; background:#fff; color:#6b7280; text-decoration:none; font-size:11px; font-weight:700; text-transform:uppercase; border:1px solid #e5e7eb; }
            .tab.active { background:var(--accent-orange); color:#fff; border-color:var(--accent-orange); }
            .card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 6px 16px rgba(17,24,39,0.08); border:1px solid #e5e7eb; }
            .table { width:100%; border-collapse:collapse; font-size:13px; }
            .table th, .table td { padding:12px 10px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:middle; }
            .table th { color:#6b7280; font-size:11px; letter-spacing:.5px; text-transform:uppercase; }
            .muted { color:#6b7280; }
            .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
            .badge.activo { background:rgba(39,174,96,.2); color:#2ecc71; }
            .badge.inactivo { background:rgba(231,76,60,.2); color:#e74c3c; }
            .detail-grid { display:grid; grid-template-columns: 260px 1fr; gap:16px; margin-top:16px; }
            .photo-box { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#f8fafc; min-height:260px; display:flex; align-items:center; justify-content:center; }
            .photo-box img { width:100%; height:100%; object-fit:cover; display:block; }
            .avatar-fallback { width:96px; height:96px; border-radius:999px; background:#111827; color:#fff; display:flex; align-items:center; justify-content:center; font-size:30px; font-weight:800; }
            .detail-card { border:1px solid #e5e7eb; border-radius:12px; padding:14px; background:#fff; }
            .kpi-row { display:grid; grid-template-columns: repeat(5, minmax(100px, 1fr)); gap:10px; margin-bottom:12px; }
            .kpi { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:10px; }
            .kpi .k { font-size:10px; text-transform:uppercase; color:#64748b; font-weight:800; }
            .kpi .v { margin-top:6px; font-size:18px; font-weight:800; color:#0f172a; }
            .export-tools { display:flex; gap:8px; align-items:center; }
            .btn-export {
                border:1px solid #d1d5db;
                background:#ffffff;
                color:#111827;
                padding:8px 12px;
                border-radius:8px;
                font-size:11px;
                font-weight:700;
                cursor:pointer;
                min-height:36px;
            }
            .btn-export i { margin-right:6px; }
            .module-entry {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 14px;
            }
            .module-entry-btn {
                border: 1px solid #d1d5db;
                background: #dbeafe;
                color: #1e3a8a;
                padding: 9px 12px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 700;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .module-modal {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #ffffff;
                overflow: hidden;
            }
            .module-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                padding: 12px 14px;
                background: #111827;
                border-bottom: 1px solid #0f172a;
            }
            .module-modal-header h4 {
                margin: 0;
                color: #ffffff;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: .2px;
            }
            .module-modal-actions { display: flex; gap: 8px; }
            .module-modal-btn {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
                padding: 7px 10px;
                border-radius: 7px;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
            }
            .module-modal-btn.min { background: #fef3c7; border-color: #fbbf24; color: #92400e; }
            .module-modal-btn.close { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
            .module-modal-body { padding: 12px; }
            .module-modal.minimized .module-modal-body { display: none; }
            .avaliacao-head {
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:12px;
                margin-bottom:14px;
                flex-wrap:wrap;
            }
            .avaliacao-filtros {
                display:flex;
                gap:10px;
                align-items:flex-end;
                flex-wrap:wrap;
                margin-bottom:12px;
            }
            .avaliacao-filtros input[type="date"] {
                padding:8px 10px;
                border:1px solid #d1d5db;
                border-radius:8px;
                font-size:12px;
            }
            .kpi-row-avaliacao { display:grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap:10px; margin-bottom:14px; }
            .kpi-av {
                border-radius:12px;
                padding:12px;
                border:1px solid #e5e7eb;
                background: linear-gradient(140deg, #ffffff, #f8fafc);
            }
            .kpi-av .k { font-size:10px; text-transform:uppercase; color:#64748b; font-weight:800; display:flex; align-items:center; gap:6px; }
            .kpi-av .v { margin-top:6px; font-size:20px; font-weight:800; color:#0f172a; }
            .kpi-av .s { font-size:11px; color:#64748b; margin-top:2px; }
            .grade-badge {
                display: inline-block;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 700;
                border: 1px solid transparent;
            }
            .grade-needs { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
            .grade-regular { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
            .grade-good { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
            .grade-excellent { background: #ecfeff; color: #155e75; border-color: #a5f3fc; }
            .grade-nodata { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
            .score-bar {
                width: 120px;
                height: 8px;
                border-radius: 999px;
                background: #e5e7eb;
                overflow: hidden;
            }
            .score-fill {
                height: 100%;
                background: linear-gradient(90deg, #f97316, #f59e0b);
                border-radius: 999px;
            }
            .legend-box {
                margin-bottom: 12px;
                padding: 10px 12px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #f8fafc;
                font-size: 12px;
                color: #334155;
            }
            @media (max-width: 980px) {
                .detail-grid { grid-template-columns: 1fr; }
                .kpi-row { grid-template-columns: repeat(2, minmax(100px, 1fr)); }
                .kpi-row-avaliacao { grid-template-columns: repeat(2, minmax(120px, 1fr)); }
            }
        </style>

        <div class="tabs">
            <a class="tab <?= $view === 'presencas' ? 'active' : '' ?>" href="?view=presencas&mode=list&aplicar=1">Controle de Presencas</a>
            <a class="tab <?= $view === 'avaliacao' ? 'active' : '' ?>" href="?view=avaliacao&mode=list&aplicar=1">Avaliacao</a>
        </div>

        <div class="card">
            <div id="rh-main-body">
            <?php if ($erro !== null): ?>
                <p class="muted"><?= htmlspecialchars($erro) ?></p>
            <?php elseif ($view === 'colaboradores'): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
                    <form method="GET" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <input type="hidden" name="view" value="colaboradores">
                        <input type="hidden" name="mode" value="list">
                        <input type="hidden" name="aplicar" value="1">
                        <div style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e5e7eb; padding:8px 12px; border-radius:999px;">
                            <i class="fa-solid fa-magnifying-glass" style="color:#9ca3af;"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Pesquisar nome, numero ou cargo..." style="background:transparent; border:none; outline:none; color:#111827; font-size:12px; width:240px;">
                        </div>
                        <select name="cargo_id" style="border:1px solid #e5e7eb; border-radius:10px; padding:8px 10px; font-size:12px;">
                            <option value="0">Todos os cargos</option>
                            <?php foreach ($cargos as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$cargo_id === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" style="background:#111827; color:#fff; border:none; padding:8px 12px; border-radius:8px; font-size:11px; font-weight:700;">Filtrar por cargo</button>
                        <?php if ($q !== '' || $cargo_id > 0): ?>
                            <a href="index.php?view=colaboradores&mode=list&aplicar=1" style="color:#6b7280; font-size:11px; text-decoration:none;">Limpar</a>
                        <?php endif; ?>
                    </form>
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div class="muted" style="font-size:11px;"><?= count($colaboradores) ?> funcionario(s)</div>
                        <div class="export-tools">
                            <button type="button" class="btn-export" data-rh-export="excel" data-rh-table="rh-colaboradores-table" data-rh-base="rh_colaboradores" data-rh-title="RH_COLABORADORES">
                                <i class="fas fa-file-excel"></i> Baixar Excel
                            </button>
                            <button type="button" class="btn-export" data-rh-export="pdf" data-rh-table="rh-colaboradores-table" data-rh-base="rh_colaboradores" data-rh-title="RH_COLABORADORES">
                                <i class="fas fa-file-pdf"></i> Baixar PDF
                            </button>
                        </div>
                    </div>
                </div>

                <table class="table" id="rh-colaboradores-table">
                    <thead>
                        <tr>
                            <th>Numero</th>
                            <th>Nome</th>
                            <th>Cargo</th>
                            <th>Estado</th>
                            <th>Criado Em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($colaboradores) === 0): ?>
                            <tr><td colspan="5" class="muted">Sem funcionarios para os filtros aplicados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($colaboradores as $f): ?>
                                <?php $estado = strtolower((string)($f['estado'] ?? '')); ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($f['numero'] ?? '-')) ?></td>
                                    <td>
                                        <a href="?view=colaboradores&mode=list&aplicar=1&funcionario_id=<?= (int)$f['id'] ?>&cargo_id=<?= (int)$cargo_id ?>&q=<?= urlencode($q) ?>" style="color:#0f172a; font-weight:700; text-decoration:none;">
                                            <?= htmlspecialchars((string)($f['nome'] ?? '-')) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars((string)($f['cargo_nome'] ?? '-')) ?></td>
                                    <td><span class="badge <?= htmlspecialchars($estado) ?>"><?= htmlspecialchars((string)($f['estado'] ?? '-')) ?></span></td>
                                    <td><?= htmlspecialchars((string)($f['created_at'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($funcionario): ?>
                    <?php
                        $fotoUrl = obterFotoFuncionarioUrl($funcionario);
                        $iniciais = iniciaisNome((string)($funcionario['nome'] ?? ''));
                    ?>
                    <div class="detail-grid">
                        <div class="photo-box">
                            <?php if ($fotoUrl !== null): ?>
                                <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto do funcionario">
                            <?php else: ?>
                                <div class="avatar-fallback"><?= htmlspecialchars($iniciais) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="detail-card">
                            <h3 style="margin-top:0;"><?= htmlspecialchars((string)$funcionario['nome']) ?></h3>
                            <p class="muted" style="margin-top:4px;">Numero: <?= htmlspecialchars((string)($funcionario['numero'] ?? '-')) ?> | Cargo: <?= htmlspecialchars((string)($funcionario['cargo_nome'] ?? '-')) ?></p>
                            <p class="muted">Estado: <?= htmlspecialchars((string)($funcionario['estado'] ?? '-')) ?> | Registado em: <?= htmlspecialchars((string)($funcionario['created_at'] ?? '-')) ?></p>

                            <h4 style="margin-top:14px; margin-bottom:8px;">Documentos</h4>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Emissao</th>
                                        <th>Vencimento</th>
                                        <th>Criado Em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($funcionario_docs) === 0): ?>
                                        <tr><td colspan="4" class="muted">Sem documentos registados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($funcionario_docs as $d): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)($d['tipo_documento'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($d['data_emissao'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($d['data_vencimento'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($d['created_at'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($view === 'avaliacao'): ?>
                <div class="avaliacao-head">
                    <div>
                        <h3 style="margin:0;">Avaliacao de Desempenho Automatica</h3>
                        <p class="muted" style="margin-top:4px;">Gerada com base em presencas, atrasos, faltas e cobertura do periodo.</p>
                    </div>
                    <div class="export-tools">
                        <button type="button" class="btn-export" data-rh-export="excel" data-rh-table="rh-avaliacao-table" data-rh-base="rh_avaliacao" data-rh-title="RH_AVALIACAO">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn-export" data-rh-export="pdf" data-rh-table="rh-avaliacao-table" data-rh-base="rh_avaliacao" data-rh-title="RH_AVALIACAO">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>

                <form method="GET" class="avaliacao-filtros">
                    <input type="hidden" name="view" value="avaliacao">
                    <input type="hidden" name="mode" value="list">
                    <input type="hidden" name="aplicar" value="1">
                    <div>
                        <label style="display:block; font-size:10px; color:#6b7280; text-transform:uppercase; margin-bottom:5px;">Inicio</label>
                        <input type="date" name="avaliacao_inicio" value="<?= htmlspecialchars($avaliacao_inicio) ?>">
                    </div>
                    <div>
                        <label style="display:block; font-size:10px; color:#6b7280; text-transform:uppercase; margin-bottom:5px;">Fim</label>
                        <input type="date" name="avaliacao_fim" value="<?= htmlspecialchars($avaliacao_fim) ?>">
                    </div>
                    <button type="submit" class="btn-export" style="background:#111827;color:#fff;border-color:#111827;">
                        <i class="fa-solid fa-chart-line"></i> Atualizar
                    </button>
                </form>

                <div class="kpi-row-avaliacao">
                    <div class="kpi-av">
                        <div class="k"><i class="fa-solid fa-users"></i> Colaboradores</div>
                        <div class="v"><?= (int)$totais_avaliacao['colaboradores'] ?></div>
                        <div class="s">No periodo selecionado</div>
                    </div>
                    <div class="kpi-av">
                        <div class="k"><i class="fa-solid fa-star"></i> Media Geral</div>
                        <div class="v"><?= number_format((float)$totais_avaliacao['media'], 1, ',', '.') ?>/35</div>
                        <div class="s">Desempenho medio</div>
                    </div>
                    <div class="kpi-av">
                        <div class="k"><i class="fa-solid fa-trophy"></i> Excelente</div>
                        <div class="v"><?= (int)$totais_avaliacao['excelente'] ?></div>
                        <div class="s">Top performance</div>
                    </div>
                    <div class="kpi-av">
                        <div class="k"><i class="fa-solid fa-thumbs-up"></i> Bom</div>
                        <div class="v"><?= (int)$totais_avaliacao['bom'] ?></div>
                        <div class="s">Faixa recomendada</div>
                    </div>
                    <div class="kpi-av">
                        <div class="k"><i class="fa-solid fa-triangle-exclamation"></i> Regular/Fraco</div>
                        <div class="v"><?= (int)$totais_avaliacao['regular'] + (int)$totais_avaliacao['fraco'] ?></div>
                        <div class="s">Requer acompanhamento</div>
                    </div>
                </div>

                <div class="legend-box">
                    Escala: 0-14 Precisa melhorar | 15-24 Regular | 25-31 Bom | 32-35 Excelente
                </div>

                <div style="overflow:auto;">
                    <table class="table" id="rh-avaliacao-table">
                        <thead>
                            <tr>
                                <th><i class="fa-solid fa-id-badge"></i> Numero</th>
                                <th><i class="fa-solid fa-user"></i> Nome</th>
                                <th><i class="fa-solid fa-briefcase"></i> Cargo</th>
                                <th><i class="fa-solid fa-clock"></i> Pontualidade</th>
                                <th><i class="fa-solid fa-award"></i> Qualidade</th>
                                <th><i class="fa-solid fa-gauge-high"></i> Produtividade</th>
                                <th><i class="fa-solid fa-people-group"></i> Equipa</th>
                                <th><i class="fa-solid fa-comments"></i> Comunicacao</th>
                                <th><i class="fa-solid fa-lightbulb"></i> Proatividade</th>
                                <th><i class="fa-solid fa-bullseye"></i> Metas</th>
                                <th><i class="fa-solid fa-calendar-check"></i> Presencas</th>
                                <th><i class="fa-solid fa-ranking-star"></i> Nota</th>
                                <th><i class="fa-solid fa-shield-halved"></i> Classe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($avaliacao_lista) === 0): ?>
                                <tr><td colspan="13" class="muted">Sem colaboradores para avaliacao.</td></tr>
                            <?php else: ?>
                                <?php foreach ($avaliacao_lista as $av): ?>
                                    <?php
                                        $pctNota = max(0.0, min(100.0, ((float)$av['nota_final'] / 35.0) * 100.0));
                                        $classeExtra = $av['sem_dados'] ? 'grade-nodata' : (string)$av['class_css'];
                                        $textoClasse = $av['sem_dados'] ? 'Sem dados' : (string)$av['classificacao'];
                                        $iconeClasse = $av['sem_dados'] ? 'fa-solid fa-circle-info' : (string)$av['class_icone'];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$av['numero']) ?></td>
                                        <td><strong><?= htmlspecialchars((string)$av['nome']) ?></strong></td>
                                        <td><?= htmlspecialchars((string)$av['cargo']) ?></td>
                                        <td><?= number_format((float)$av['pontualidade'], 1, ',', '.') ?></td>
                                        <td><?= number_format((float)$av['qualidade'], 1, ',', '.') ?></td>
                                        <td><?= number_format((float)$av['produtividade'], 1, ',', '.') ?></td>
                                        <td><?= number_format((float)$av['trabalho_equipa'], 1, ',', '.') ?></td>
                                        <td><?= number_format((float)$av['comunicacao'], 1, ',', '.') ?></td>
                                        <td><?= number_format((float)$av['proatividade'], 1, ',', '.') ?></td>
                                        <td><?= number_format((float)$av['metas'], 1, ',', '.') ?></td>
                                        <td>
                                            <span class="muted" style="font-size:12px;">
                                                P: <?= (int)$av['presentes'] ?> | A: <?= (int)$av['atrasos'] ?> | F: <?= (int)$av['faltas'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= number_format((float)$av['nota_final'], 1, ',', '.') ?></strong>
                                            <div class="score-bar"><div class="score-fill" style="width: <?= number_format($pctNota, 2, '.', '') ?>%;"></div></div>
                                        </td>
                                        <td><span class="grade-badge <?= htmlspecialchars($classeExtra) ?>"><i class="<?= htmlspecialchars($iconeClasse) ?>" style="margin-right:6px;"></i><?= htmlspecialchars($textoClasse) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($view === 'presencas'): ?>
                <div id="painel-listas-presencas-rh" style="margin-top:4px;">
                        <div style="font-size:13px; font-weight:800; color:#334155; margin-bottom:10px;">Listas de Presencas (ultimos 30 dias)</div>
                        <div style="overflow:auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Lista</th>
                                        <th>Total Funcionarios</th>
                                        <th>Presentes</th>
                                        <th>Ausentes</th>
                                        <th>Lista Fisica</th>
                                        <th>Enviado RH</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($listas_presenca_dias)): ?>
                                        <tr><td colspan="7" class="muted">Sem listas de presenca no periodo.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($listas_presenca_dias as $ld): ?>
                                            <?php
                                                $dataLista = (string)($ld['data_presenca'] ?? '');
                                                $enviadoTodos = (int)($ld['enviado_rh_todos'] ?? 0) === 1;
                                                $possuiAnexo = (int)($ld['possui_anexo'] ?? 0) === 1;
                                                $anexoPathLista = (string)($ld['lista_fisica_anexo'] ?? '');
                                            ?>
                                            <tr>
                                                <td><?= !empty($dataLista) ? ('Lista ' . date('d/m/Y', strtotime($dataLista))) : '-' ?></td>
                                                <td><?= (int)($ld['total_funcionarios'] ?? 0) ?></td>
                                                <td><?= (int)($ld['total_presentes'] ?? 0) ?></td>
                                                <td><?= (int)($ld['total_ausentes'] ?? 0) ?></td>
                                                <td>
                                                    <?php if ($possuiAnexo && $anexoPathLista !== ''): ?>
                                                        <a href="<?= htmlspecialchars('/vilcon-systemon/' . ltrim($anexoPathLista, '/')) ?>" target="_blank" class="btn-export" style="font-size:10px;background:#0f766e;color:#fff;border-color:#0f766e;">Ver anexo</a>
                                                    <?php else: ?>
                                                        <span style="font-size:11px; color:#b91c1c; font-weight:700;">Nao anexada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $enviadoTodos ? 'Sim' : 'Nao' ?></td>
                                                <td>
                                                    <a href="?view=presencas&mode=list&aplicar=1&hist_data=<?= urlencode($dataLista) ?>" class="btn-export" style="font-size:10px;background:#334155;color:#fff;border-color:#334155;">Ver historico</a>
                                                    <?php if ($enviadoTodos): ?>
                                                        <span style="font-size:11px; color:#64748b; font-weight:700; margin-left:6px;">Bloqueada apos envio</span>
                                                    <?php else: ?>
                                                        <span style="font-size:11px; color:#92400e; font-weight:700; margin-left:6px;">Pendente envio</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                </div>
                <div id="painel-lista-dia-presencas-rh" style="display:none; position:fixed; inset:0; z-index:1100; background:rgba(15,23,42,0.68); padding:24px; overflow:auto;">
                    <div style="max-width:1150px; margin:0 auto; background:#fff; border-radius:12px; border:1px solid #dbe3ed; padding:14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                            <div style="font-size:13px; font-weight:800; color:#334155;">Lista de Presencas - <?= $hist_data_rh !== '' ? htmlspecialchars(date('d/m/Y', strtotime($hist_data_rh))) : '' ?></div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <button type="button" class="btn-export" style="background:#c2410c; color:#fff; border-color:#c2410c;" onclick="baixarListaDiaPresencasRh('pdf')"><i class="fa-solid fa-file-pdf"></i> Baixar PDF</button>
                                <button type="button" class="btn-export" style="background:#166534; color:#fff; border-color:#166534;" onclick="baixarListaDiaPresencasRh('excel')"><i class="fa-solid fa-file-excel"></i> Baixar Excel</button>
                                <button type="button" class="btn-export" style="background:#1d4ed8; color:#fff; border-color:#1d4ed8;" onclick="baixarListaDiaPresencasRh('word')"><i class="fa-solid fa-file-word"></i> Baixar Word</button>
                                <button type="button" class="btn-export" style="background:#334155; color:#fff; border-color:#334155;" onclick="fecharTelaListaDiaPresencasRh()"><i class="fa-solid fa-xmark"></i> Fechar tela</button>
                            </div>
                        </div>
                        <div style="overflow:auto;">
                            <table class="table" id="rh-presencas-dia-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Funcionario</th>
                                        <th>Cargo</th>
                                        <th>Entrada</th>
                                        <th>Saida</th>
                                        <th>Estado</th>
                                        <th>Enviado RH</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lista_presencas_historico_rh)): ?>
                                        <tr><td colspan="7" class="muted">Sem registos para esta lista.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($lista_presencas_historico_rh as $prh): ?>
                                            <tr>
                                                <td><?= !empty($prh['data_presenca']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$prh['data_presenca']))) : '-' ?></td>
                                                <td><?= htmlspecialchars((string)($prh['colaborador'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($prh['cargo_nome'] ?? '-')) ?></td>
                                                <td><?= !empty($prh['hora_entrada']) ? htmlspecialchars(substr((string)$prh['hora_entrada'], 0, 5)) : '-' ?></td>
                                                <td><?= !empty($prh['hora_saida']) ? htmlspecialchars(substr((string)$prh['hora_saida'], 0, 5)) : '-' ?></td>
                                                <td><?= htmlspecialchars((string)($prh['status_presenca'] ?? '-')) ?></td>
                                                <td><?= (int)($prh['enviado_rh'] ?? 0) === 1 ? 'Sim' : 'Nao' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="muted">Vista em desenvolvimento.</p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
function abrirTelaListaDiaPresencasRh() {
    var el = document.getElementById('painel-lista-dia-presencas-rh');
    if (!el) return;
    el.style.display = 'block';
}

function fecharTelaListaDiaPresencasRh() {
    var el = document.getElementById('painel-lista-dia-presencas-rh');
    if (el) el.style.display = 'none';
    try {
        var url = new URL(window.location.href);
        url.searchParams.delete('hist_data');
        window.history.replaceState({}, '', url.toString());
    } catch (e) {}
}

function nomeArquivoRh(base, ext) {
    var data = new Date();
    var y = data.getFullYear();
    var m = String(data.getMonth() + 1).padStart(2, '0');
    var d = String(data.getDate()).padStart(2, '0');
    return base + '_' + y + m + d + '.' + ext;
}

function exportarExcelRh(tabela, base) {
    var html = '<html><head><meta charset="UTF-8"></head><body>' + tabela.outerHTML + '</body></html>';
    var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = nomeArquivoRh(base, 'xls');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportarWordRh(tabela, base) {
    var html = '<html><head><meta charset="UTF-8"></head><body>' + tabela.outerHTML + '</body></html>';
    var blob = new Blob([html], { type: 'application/msword;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = nomeArquivoRh(base, 'doc');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportarPdfRh(tabela, titulo) {
    var janela = window.open('', '_blank');
    if (!janela) return;
    var logoUrl = window.location.origin + '/vilcon-systemon/public/assets/img/logo-vilcon.png';
    var dataAtual = new Date().toLocaleString('pt-PT');
    var html = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${titulo}</title>
            <style>
                @page { margin: 18mm 12mm; }
                body { font-family: Arial, sans-serif; color: #111111; }
                .pdf-wrap { width: 100%; }
                .pdf-header { border: 2px solid #111111; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
                .pdf-strip { height: 10px; background: #f4b400; }
                .pdf-head-content { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #ffffff; }
                .pdf-brand { display: flex; align-items: center; gap: 12px; }
                .pdf-brand img { width: 130px; height: auto; object-fit: contain; }
                .pdf-brand h1 { margin: 0; font-size: 18px; color: #111111; letter-spacing: 0.4px; }
                .pdf-meta { text-align: right; font-size: 11px; color: #333333; }
                .pdf-meta strong { display: block; color: #111111; margin-bottom: 4px; }
                h2 { margin: 0 0 10px 0; color: #111111; font-size: 14px; text-transform: uppercase; }
                table { width: 100%; border-collapse: collapse; }
                thead th {
                    background: #111111 !important;
                    color: #f4b400 !important;
                    border: 1px solid #111111;
                    padding: 8px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
                }
                tbody td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; color: #111111; }
                tbody tr:nth-child(even) td { background: #fff8e1; }
            </style>
        </head>
        <body>
            <div class="pdf-wrap">
                <div class="pdf-header">
                    <div class="pdf-strip"></div>
                    <div class="pdf-head-content">
                        <div class="pdf-brand">
                            <img src="${logoUrl}" alt="Vilcon">
                            <h1>VILCON</h1>
                        </div>
                        <div class="pdf-meta">
                            <strong>${titulo}</strong>
                            <span>Emitido em: ${dataAtual}</span>
                        </div>
                    </div>
                </div>
                <h2>Relatorio</h2>
                ${tabela.outerHTML}
            </div>
        </body>
        </html>
    `;
    janela.document.write(html);
    janela.document.close();
    janela.focus();
    janela.print();
}

function baixarListaDiaPresencasRh(tipo) {
    var tabela = document.getElementById('rh-presencas-dia-table');
    if (!tabela) {
        alert('Tabela da lista diaria nao encontrada.');
        return;
    }
    if (tipo === 'excel') {
        exportarExcelRh(tabela, 'rh_lista_presencas');
        return;
    }
    if (tipo === 'word') {
        exportarWordRh(tabela, 'rh_lista_presencas');
        return;
    }
    exportarPdfRh(tabela, 'RH_LISTA_PRESENCAS');
}

document.querySelectorAll('[data-rh-export]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tableId = btn.getAttribute('data-rh-table') || 'rh-colaboradores-table';
        var base = btn.getAttribute('data-rh-base') || 'rh_colaboradores';
        var titulo = btn.getAttribute('data-rh-title') || 'RH_COLABORADORES';
        var tabela = document.getElementById(tableId);
        if (!tabela) {
            alert('Tabela para exportacao nao encontrada.');
            return;
        }
        if (btn.getAttribute('data-rh-export') === 'excel') {
            exportarExcelRh(tabela, base);
        } else {
            exportarPdfRh(tabela, titulo);
        }
    });
});

document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-lista-dia-presencas-rh');
    if (!el || el.style.display !== 'block') return;
    if (ev.target === el) fecharTelaListaDiaPresencasRh();
});

<?php if ($view === 'presencas' && $hist_data_rh !== ''): ?>
document.addEventListener('DOMContentLoaded', function() {
    abrirTelaListaDiaPresencasRh();
});
<?php endif; ?>
</script>
