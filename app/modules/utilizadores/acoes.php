<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Acoes do Utilizador | Vilcon System';

$erro = null;
$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$dataFiltro = trim((string)($_GET['data'] ?? ''));
$termoFiltro = trim((string)($_GET['q'] ?? ''));
$utilizador = null;
$acoes = [];

function tituloSimples($valor) {
    $v = trim((string)$valor);
    if ($v === '') return '-';
    $v = str_replace(['_', '-'], ' ', $v);
    return ucwords($v);
}

function mapearModuloPorRota($path) {
    $map = [
        '/vilcon-systemon/app/modules/dashboard/index.php' => 'Dashboard',
        '/vilcon-systemon/app/modules/oficina/index.php' => 'Oficina',
        '/vilcon-systemon/app/modules/armazem/index.php' => 'Armazem',
        '/vilcon-systemon/app/modules/transporte/index.php' => 'Transporte',
        '/vilcon-systemon/app/modules/rh/index.php' => 'RH',
        '/vilcon-systemon/app/modules/seguranca/index.php' => 'Seguranca',
        '/vilcon-systemon/app/modules/documental/index.php' => 'Documental',
        '/vilcon-systemon/app/modules/utilizadores/index.php' => 'Utilizadores',
        '/vilcon-systemon/app/modules/relatorios/index.php' => 'Relatorios',
        '/vilcon-systemon/app/modules/aprovacoes/index.php' => 'Aprovacoes',
        '/vilcon-systemon/public/login.php' => 'Login',
    ];
    if (isset($map[$path])) return $map[$path];
    return basename((string)$path);
}

function detalheModuloPorParametros($modulo, array $params) {
    $view = $params['view'] ?? '';
    $tab = $params['tab'] ?? '';
    if ($modulo === 'Oficina') {
        if ($view === 'pedidos_reparacao') return 'Pedidos de reparacao';
        if ($view === 'ordens_servico') return 'Ordens de servico';
        if ($view === 'assiduidade') return 'Assiduidade';
    }
    if ($modulo === 'Armazem') {
        if ($view === 'stock') return 'Stock';
        if ($view === 'entradas') return 'Entradas';
        if ($view === 'saidas') return 'Saidas';
        if ($view === 'fornecedores') return 'Fornecedores';
        if ($view === 'inventario') return 'Inventario';
    }
    if ($modulo === 'Transporte' && $view !== '') {
        return tituloSimples($view);
    }
    if ($modulo === 'RH' && $view !== '') {
        return tituloSimples($view);
    }
    if ($modulo === 'Seguranca' && $view !== '') {
        return tituloSimples($view);
    }
    if ($modulo === 'Documental' && $view !== '') {
        return tituloSimples($view);
    }
    if ($tab !== '') {
        return tituloSimples($tab);
    }
    return '';
}

function formatarAcaoAuditoria($acao, $tabela) {
    $acaoTxt = trim((string)$acao);
    $tabelaTxt = trim((string)$tabela);

    if (stripos($acaoTxt, 'Acesso:') === 0) {
        $rota = trim((string)substr($acaoTxt, 7));
        if (preg_match('/^(GET|POST|PUT|DELETE|OPTIONS)\s+(.+)$/i', $rota, $m)) {
            $rota = trim((string)$m[2]);
        }
        $path = (string)parse_url($rota, PHP_URL_PATH);
        $query = (string)parse_url($rota, PHP_URL_QUERY);
        $params = [];
        if ($query !== '') {
            parse_str($query, $params);
        }
        $modulo = mapearModuloPorRota($path);
        $detalhe = detalheModuloPorParametros($modulo, $params);
        $descricao = 'Visualizou modulo';
        $contexto = $modulo;
        if ($detalhe !== '') {
            $contexto .= ' - ' . $detalhe;
        }
        return ['acao' => $descricao, 'contexto' => $contexto];
    }

    if (stripos($acaoTxt, 'LOGIN:') === 0) {
        return ['acao' => 'Login', 'contexto' => trim((string)substr($acaoTxt, 6))];
    }

    $map = [
        'Inseriu pedido de reparacao' => 'Adicionou pedido de reparacao',
        'Inseriu alerta de seguranca' => 'Adicionou alerta de seguranca',
    ];
    foreach ($map as $orig => $dest) {
        if (stripos($acaoTxt, $orig) === 0) {
            return ['acao' => $dest, 'contexto' => $tabelaTxt !== '' ? $tabelaTxt : '-'];
        }
    }

    if (stripos($acaoTxt, 'Inseriu ') === 0) {
        return ['acao' => 'Adicionou registo', 'contexto' => trim(substr($acaoTxt, 8))];
    }
    if (stripos($acaoTxt, 'Atualizou ') === 0) {
        return ['acao' => 'Atualizou registo', 'contexto' => trim(substr($acaoTxt, 9))];
    }
    if (stripos($acaoTxt, 'Eliminou ') === 0) {
        return ['acao' => 'Eliminou registo', 'contexto' => trim(substr($acaoTxt, 9))];
    }

    return ['acao' => $acaoTxt !== '' ? $acaoTxt : '-', 'contexto' => $tabelaTxt !== '' ? $tabelaTxt : '-'];
}

function classificarTipoAcao($acaoOriginal, $acaoFormatada) {
    $orig = strtolower(trim((string)$acaoOriginal));
    $fmt = strtolower(trim((string)$acaoFormatada));

    if (strpos($orig, 'acesso:') === 0 || strpos($fmt, 'visualizou modulo') === 0) {
        return 'Acesso';
    }
    if (strpos($orig, 'login:') === 0 || strpos($fmt, 'login') === 0) {
        return 'Sessao';
    }
    return 'Operacao';
}

function classeTipoAcao($tipo) {
    $t = strtolower(trim((string)$tipo));
    if ($t === 'acesso') return 'info';
    if ($t === 'sessao') return 'ok';
    return 'warn';
}

function iconeTipoAcao($tipo) {
    $t = strtolower(trim((string)$tipo));
    if ($t === 'acesso') return 'fa-door-open';
    if ($t === 'sessao') return 'fa-user-check';
    return 'fa-gear';
}

try {
    if ($usuarioId <= 0) {
        throw new RuntimeException('Utilizador invalido.');
    }

    $stmtUser = $pdo->prepare('SELECT id, nome, email, perfil, status FROM usuarios WHERE id = :id LIMIT 1');
    $stmtUser->execute(['id' => $usuarioId]);
    $utilizador = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$utilizador) {
        throw new RuntimeException('Utilizador nao encontrado.');
    }

    $sqlAcoes = 'SELECT acao, tabela_afetada, data_hora
         FROM auditoria
         WHERE usuario_id = :id';
    $params = ['id' => $usuarioId];

    if ($dataFiltro !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $dataFiltro);
        if (!$dt) {
            throw new RuntimeException('Data de filtro invalida.');
        }
        $sqlAcoes .= ' AND DATE(data_hora) = :data_filtro';
        $params['data_filtro'] = $dt->format('Y-m-d');
    }

    $sqlAcoes .= ' ORDER BY data_hora DESC LIMIT 500';

    $stmtAcoes = $pdo->prepare($sqlAcoes);
    $stmtAcoes->execute($params);
    $acoes = $stmtAcoes->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$acoes_formatadas = [];
foreach ($acoes as $acao) {
    $formatado = formatarAcaoAuditoria($acao['acao'] ?? '', $acao['tabela_afetada'] ?? '');
    $tipo = classificarTipoAcao($acao['acao'] ?? '', $formatado['acao'] ?? '');
    $item = [
        'data_hora' => $acao['data_hora'] ?? '-',
        'acao' => $formatado['acao'],
        'contexto' => $formatado['contexto'],
        'tipo' => $tipo,
    ];
    if ($termoFiltro !== '') {
        $texto = strtolower((string)$item['acao'] . ' ' . (string)$item['contexto'] . ' ' . (string)$item['tipo']);
        if (strpos($texto, strtolower($termoFiltro)) === false) {
            continue;
        }
    }
    $acoes_formatadas[] = $item;
}

$totalAcoes = count($acoes_formatadas);
$totalAcessos = 0;
$totalSessao = 0;
$totalOperacoes = 0;
$totalHoje = 0;
$hojeRef = date('Y-m-d');
foreach ($acoes_formatadas as $a) {
    $tipo = strtolower((string)($a['tipo'] ?? ''));
    if ($tipo === 'acesso') $totalAcessos++;
    elseif ($tipo === 'sessao') $totalSessao++;
    else $totalOperacoes++;

    $dataHora = (string)($a['data_hora'] ?? '');
    if (substr($dataHora, 0, 10) === $hojeRef) {
        $totalHoje++;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Acoes do Utilizador</h2>
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
                vertical-align: middle;
            }
            .table th {
                color: #6b7280;
                font-size: 11px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            .muted { color: #6b7280; }
            .error {
                background: #c0392b;
                color: #fff;
                padding: 12px 14px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            .btn-back {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #111827;
                color: #fff;
                border: none;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 11px;
                font-weight: 700;
                text-decoration: none;
            }
            .kpi-grid {
                display:grid;
                grid-template-columns:repeat(5,minmax(130px,1fr));
                gap:10px;
                margin-bottom:12px;
            }
            .kpi-card {
                background:#fff;
                border:1px solid #e5e7eb;
                border-radius:10px;
                padding:10px;
            }
            .kpi-card .k-label {
                font-size:10px;
                text-transform:uppercase;
                color:#64748b;
                font-weight:800;
                display:flex;
                align-items:center;
                gap:6px;
            }
            .kpi-card .k-value {
                margin-top:6px;
                font-size:20px;
                font-weight:800;
                color:#0f172a;
            }
            .kpi-card.info { background:#eff6ff; border-color:#bfdbfe; }
            .kpi-card.ok { background:#ecfdf3; border-color:#bbf7d0; }
            .kpi-card.warn { background:#fff7ed; border-color:#fed7aa; }
            .kpi-card.slate { background:#f8fafc; border-color:#cbd5e1; }
            .filters-row {
                display:flex;
                align-items:flex-end;
                gap:10px;
                flex-wrap:wrap;
                margin-bottom:12px;
                padding:10px;
                border:1px solid #e5e7eb;
                border-radius:10px;
                background:#f8fafc;
            }
            .pill {
                display:inline-block;
                padding:4px 10px;
                border-radius:999px;
                font-size:11px;
                font-weight:700;
            }
            .pill.info { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
            .pill.ok { background:#ecfdf3; color:#15803d; border:1px solid #bbf7d0; }
            .pill.warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
            @media (max-width: 1100px) {
                .kpi-grid { grid-template-columns:repeat(2,minmax(120px,1fr)); }
            }
        </style>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                <a href="index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Voltar aos utilizadores</a>
                <?php if ($utilizador): ?>
                    <div class="muted" style="font-size:12px;">
                        <?= htmlspecialchars((string)$utilizador['nome']) ?> (<?= htmlspecialchars((string)$utilizador['email']) ?>)
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($erro): ?>
                <div class="error"><?= htmlspecialchars($erro) ?></div>
            <?php else: ?>
                <div class="kpi-grid">
                    <div class="kpi-card slate"><div class="k-label"><i class="fa-solid fa-list-check"></i>Total</div><div class="k-value"><?= (int)$totalAcoes ?></div></div>
                    <div class="kpi-card info"><div class="k-label"><i class="fa-solid fa-door-open"></i>Acessos</div><div class="k-value"><?= (int)$totalAcessos ?></div></div>
                    <div class="kpi-card ok"><div class="k-label"><i class="fa-solid fa-user-check"></i>Sessao</div><div class="k-value"><?= (int)$totalSessao ?></div></div>
                    <div class="kpi-card warn"><div class="k-label"><i class="fa-solid fa-gear"></i>Operacoes</div><div class="k-value"><?= (int)$totalOperacoes ?></div></div>
                    <div class="kpi-card slate"><div class="k-label"><i class="fa-solid fa-calendar-day"></i>Hoje</div><div class="k-value"><?= (int)$totalHoje ?></div></div>
                </div>

                <form method="GET" class="filters-row">
                    <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string)$usuarioId) ?>">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label for="data_filtro" class="muted" style="font-size:12px;">Filtrar por data</label>
                        <input
                            id="data_filtro"
                            type="date"
                            name="data"
                            value="<?= htmlspecialchars($dataFiltro) ?>"
                            style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;"
                        >
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label for="texto_filtro" class="muted" style="font-size:12px;">Pesquisar</label>
                        <input
                            id="texto_filtro"
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($termoFiltro) ?>"
                            placeholder="Acao, contexto, tipo..."
                            style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;min-width:220px;"
                        >
                    </div>
                    <button type="submit" style="background:#111827;color:#fff;border:none;padding:8px 12px;border-radius:8px;font-size:11px;font-weight:700;">
                        Aplicar
                    </button>
                    <?php if ($dataFiltro !== '' || $termoFiltro !== ''): ?>
                        <a href="acoes.php?usuario_id=<?= htmlspecialchars((string)$usuarioId) ?>" class="muted" style="font-size:11px;text-decoration:none;">Limpar</a>
                    <?php endif; ?>
                </form>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Tipo</th>
                            <th>Acao</th>
                            <th>Contexto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($acoes_formatadas) === 0): ?>
                            <tr>
                                <td colspan="4" class="muted">Sem acoes registadas para este utilizador.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($acoes_formatadas as $acao): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($acao['data_hora'] ?? '-')) ?></td>
                                    <?php
                                        $tipoAcao = (string)($acao['tipo'] ?? 'Operacao');
                                        $classeTipo = classeTipoAcao($tipoAcao);
                                        $iconeTipo = iconeTipoAcao($tipoAcao);
                                    ?>
                                    <td><span class="pill <?= htmlspecialchars($classeTipo) ?>"><i class="fa-solid <?= htmlspecialchars($iconeTipo) ?>" style="margin-right:6px;"></i><?= htmlspecialchars($tipoAcao) ?></span></td>
                                    <td><?= htmlspecialchars((string)($acao['acao'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($acao['contexto'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
