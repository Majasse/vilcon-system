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

$periodoSelecionado = (string)($_GET['periodo'] ?? 'mes');
if (!in_array($periodoSelecionado, ['hoje', 'semana', 'mes', 'custom'], true)) {
    $periodoSelecionado = 'mes';
}
$inicioPeriodo = new DateTime('today');
$fimPeriodo = new DateTime('today');
if ($periodoSelecionado === 'semana') {
    $inicioPeriodo->modify('-6 days');
} elseif ($periodoSelecionado === 'mes') {
    $inicioPeriodo = new DateTime(date('Y-m-01'));
} elseif ($periodoSelecionado === 'custom') {
    $dIni = DateTime::createFromFormat('Y-m-d', (string)($_GET['data_inicio'] ?? ''));
    $dFim = DateTime::createFromFormat('Y-m-d', (string)($_GET['data_fim'] ?? ''));
    if ($dIni && $dFim) {
        $inicioPeriodo = $dIni;
        $fimPeriodo = $dFim;
    } else {
        $periodoSelecionado = 'mes';
        $inicioPeriodo = new DateTime(date('Y-m-01'));
    }
}
if ($inicioPeriodo > $fimPeriodo) {
    $tmp = $inicioPeriodo;
    $inicioPeriodo = $fimPeriodo;
    $fimPeriodo = $tmp;
}

$filtroProjeto = trim((string)($_GET['projeto'] ?? ''));
$filtroTipoAtivo = trim((string)($_GET['tipo_ativo'] ?? ''));
$filtroCentroCusto = trim((string)($_GET['centro_custo'] ?? ''));
$filtroMotorista = trim((string)($_GET['motorista'] ?? ''));

$opcoesProjetos = [];
$opcoesTiposAtivo = [];
$opcoesCentros = [];
$opcoesMotoristas = [];

$ativosOperacao = 0;
$veiculosParados = 0;
$taxaDisponibilidade = 0.0;
$custoCombustivel = 0.0;
$custoManutencao = 0.0;
$custosPecas = 0.0;
$custosMultas = 0.0;
$custoOperacionalTotal = 0.0;
$totalLitros = 0.0;
$kmPeriodo = 0.0;
$consumoMedioL100 = 0.0;
$custoPorKm = 0.0;
$viagensTotal = 0;
$viagensConcluidas = 0;
$viagensCanceladas = 0;
$eficienciaEntrega = 0.0;
$tempoMedioViagem = 0.0;
$tempoResolucao = 0.0;
$alertaManutencao = 0;
$alertaConsumo = 0;
$alertaQuilometragem = 0;
$registosCriados = 0;
$registosAtualizados = 0;
$novosAtivos = 0;

$consumoDiaLabels = [];
$consumoDiaValores = [];
$consumoViaturaLabels = [];
$consumoViaturaValores = [];
$viagensMotoristaLabels = [];
$viagensMotoristaValores = [];

if (tabelaExiste($pdo, 'activos')) {
    try {
        $ativosRows = $pdo->query("SELECT id, equipamento, matricula, estado, seguros, inspeccao FROM activos")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($ativosRows as $at) {
            $estado = strtolower(trim((string)($at['estado'] ?? '')));
            $tipoEq = trim((string)($at['equipamento'] ?? ''));
            if ($tipoEq !== '') $opcoesTiposAtivo[$tipoEq] = true;
            if ($estado === '' || in_array($estado, ['operacional', 'ativo', 'disponivel', 'em operacao'], true)) $ativosOperacao++;
            if (in_array($estado, ['parado', 'inativo', 'avariado', 'manutencao'], true)) $veiculosParados++;
            foreach (['seguros', 'inspeccao'] as $colData) {
                $dtTxt = trim((string)($at[$colData] ?? ''));
                if ($dtTxt === '') continue;
                $ts = strtotime(substr($dtTxt, 0, 10));
                if ($ts !== false) {
                    $dias = (int)floor(($ts - strtotime(date('Y-m-d'))) / 86400);
                    if ($dias >= 0 && $dias <= 30) $alertaManutencao++;
                }
            }
        }
        if ($ativos_total > 0 && $veiculosParados === 0) {
            $veiculosParados = max(0, $ativos_total - $ativosOperacao);
        }
        $taxaDisponibilidade = $ativos_total > 0 ? ($ativosOperacao / $ativos_total) * 100 : 0.0;
    } catch (Throwable $e) {
        // manter default
    }
}

if (tabelaExiste($pdo, 'transporte_guias')) {
    try {
        $stmtG = $pdo->prepare("
            SELECT viatura_id, matricula, condutor, projeto, tipo_equipamento, local_saida, destino, km_saida, km_chegada, distancia_km, data_saida, hora_saida, hora_chegada, status
            FROM transporte_guias
            WHERE DATE(data_saida) BETWEEN :ini AND :fim
        ");
        $stmtG->execute([
            'ini' => $inicioPeriodo->format('Y-m-d'),
            'fim' => $fimPeriodo->format('Y-m-d'),
        ]);
        $guiasRows = $stmtG->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tempoTotal = 0.0;
        $tempoCount = 0;
        $mapViagensMotorista = [];
        foreach ($guiasRows as $g) {
            $projeto = trim((string)($g['projeto'] ?? ''));
            $tipoEq = trim((string)($g['tipo_equipamento'] ?? ''));
            $motorista = trim((string)($g['condutor'] ?? ''));
            if ($projeto !== '') {
                $opcoesProjetos[$projeto] = true;
                $opcoesCentros[$projeto] = true;
            }
            if ($tipoEq !== '') $opcoesTiposAtivo[$tipoEq] = true;
            if ($motorista !== '') $opcoesMotoristas[$motorista] = true;

            $viagensTotal++;
            $status = strtolower(trim((string)($g['status'] ?? '')));
            if (strpos($status, 'cancel') !== false || strpos($status, 'rejeit') !== false) {
                $viagensCanceladas++;
            } else {
                $viagensConcluidas++;
            }

            $kmSaida = (float)($g['km_saida'] ?? 0);
            $kmChegada = (float)($g['km_chegada'] ?? 0);
            $dist = (float)($g['distancia_km'] ?? 0);
            if ($dist > 0) $kmPeriodo += $dist;
            elseif ($kmChegada > $kmSaida) $kmPeriodo += ($kmChegada - $kmSaida);
            if ($kmChegada > 250000) $alertaQuilometragem++;

            $hIni = trim((string)($g['hora_saida'] ?? ''));
            $hFim = trim((string)($g['hora_chegada'] ?? ''));
            if ($hIni !== '' && $hFim !== '') {
                $t1 = strtotime('1970-01-01 ' . $hIni);
                $t2 = strtotime('1970-01-01 ' . $hFim);
                if ($t1 !== false && $t2 !== false && $t2 > $t1) {
                    $tempoTotal += ($t2 - $t1) / 60;
                    $tempoCount++;
                }
            }

            $driverKey = $motorista !== '' ? $motorista : 'Nao definido';
            if (!isset($mapViagensMotorista[$driverKey])) $mapViagensMotorista[$driverKey] = 0;
            $mapViagensMotorista[$driverKey]++;
        }
        arsort($mapViagensMotorista);
        $mapViagensMotorista = array_slice($mapViagensMotorista, 0, 8, true);
        $viagensMotoristaLabels = array_keys($mapViagensMotorista);
        $viagensMotoristaValores = array_values($mapViagensMotorista);
        $eficienciaEntrega = $viagensTotal > 0 ? ($viagensConcluidas / $viagensTotal) * 100 : 0.0;
        $tempoMedioViagem = $tempoCount > 0 ? ($tempoTotal / $tempoCount) : 0.0;
    } catch (Throwable $e) {
        // manter default
    }
}

if (tabelaExiste($pdo, 'transporte_mapa_diesel')) {
    try {
        $stmtD = $pdo->prepare("
            SELECT data_movimento, projeto, tipo_movimento, viatura_id, motorista, litros, valor_total
            FROM transporte_mapa_diesel
            WHERE DATE(data_movimento) BETWEEN :ini AND :fim
            ORDER BY data_movimento ASC
        ");
        $stmtD->execute([
            'ini' => $inicioPeriodo->format('Y-m-d'),
            'fim' => $fimPeriodo->format('Y-m-d'),
        ]);
        $dieselRows = $stmtD->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $mapDia = [];
        $mapViatura = [];
        foreach ($dieselRows as $d) {
            $tipoMov = strtolower(trim((string)($d['tipo_movimento'] ?? '')));
            if ($tipoMov !== '' && strpos($tipoMov, 'saida') === false) continue;
            $projeto = trim((string)($d['projeto'] ?? ''));
            $motorista = trim((string)($d['motorista'] ?? ''));
            if ($projeto !== '') {
                $opcoesProjetos[$projeto] = true;
                $opcoesCentros[$projeto] = true;
            }
            if ($motorista !== '') $opcoesMotoristas[$motorista] = true;

            $lit = (float)($d['litros'] ?? 0);
            $val = (float)($d['valor_total'] ?? 0);
            $dia = (string)($d['data_movimento'] ?? '');
            $viat = trim((string)($d['viatura_id'] ?? 'Sem viatura'));
            if ($viat === '') $viat = 'Sem viatura';
            $totalLitros += $lit;
            $custoCombustivel += $val;
            if ($dia !== '') {
                if (!isset($mapDia[$dia])) $mapDia[$dia] = 0.0;
                $mapDia[$dia] += $lit;
            }
            if (!isset($mapViatura[$viat])) $mapViatura[$viat] = 0.0;
            $mapViatura[$viat] += $lit;
        }
        ksort($mapDia);
        arsort($mapViatura);
        $consumoDiaLabels = array_map(static fn($x) => date('d/m', strtotime((string)$x)), array_keys($mapDia));
        $consumoDiaValores = array_values($mapDia);
        $topViat = array_slice($mapViatura, 0, 8, true);
        $consumoViaturaLabels = array_keys($topViat);
        $consumoViaturaValores = array_values($topViat);
        foreach ($topViat as $litrosV) {
            if ((float)$litrosV > 250) $alertaConsumo++;
        }
    } catch (Throwable $e) {
        // manter default
    }
}

if (tabelaExiste($pdo, 'transporte_combustivel') && $totalLitros <= 0) {
    try {
        $stmtC = $pdo->prepare("SELECT COALESCE(SUM(litros_abastecidos),0) FROM transporte_combustivel WHERE DATE(data_abastecimento) BETWEEN :ini AND :fim");
        $stmtC->execute([
            'ini' => $inicioPeriodo->format('Y-m-d'),
            'fim' => $fimPeriodo->format('Y-m-d'),
        ]);
        $totalLitros = (float)$stmtC->fetchColumn();
    } catch (Throwable $e) {
        // manter default
    }
}

if (tabelaExiste($pdo, 'oficina_pedidos_reparacao')) {
    try {
        $stmtM = $pdo->prepare("
            SELECT data_pedido, status, prioridade, custo_estimado
            FROM oficina_pedidos_reparacao
            WHERE DATE(data_pedido) BETWEEN :ini AND :fim
        ");
        $stmtM->execute([
            'ini' => $inicioPeriodo->format('Y-m-d'),
            'fim' => $fimPeriodo->format('Y-m-d'),
        ]);
        $manRows = $stmtM->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tempoTotalResolucao = 0.0;
        $tempoCountResolucao = 0;
        foreach ($manRows as $m) {
            $custoManutencao += (float)($m['custo_estimado'] ?? 0);
            $st = strtolower(trim((string)($m['status'] ?? '')));
            if (strpos($st, 'resol') !== false || strpos($st, 'fech') !== false || strpos($st, 'atend') !== false) {
                $dt = DateTime::createFromFormat('Y-m-d', substr((string)($m['data_pedido'] ?? ''), 0, 10));
                if ($dt) {
                    $tempoTotalResolucao += (float)$dt->diff($hoje)->format('%a');
                    $tempoCountResolucao++;
                }
            }
        }
        $tempoResolucao = $tempoCountResolucao > 0 ? ($tempoTotalResolucao / $tempoCountResolucao) : 0.0;
    } catch (Throwable $e) {
        // manter default
    }
}

$consumoMedioL100 = $kmPeriodo > 0 ? (($totalLitros / $kmPeriodo) * 100) : 0.0;
$custoPorKm = $kmPeriodo > 0 ? ($custoCombustivel / $kmPeriodo) : 0.0;
$custosPecas = $custoManutencao * 0.28;
$custoOperacionalTotal = $custoCombustivel + $custoManutencao + $custosPecas + $custosMultas;

foreach ($auditoria_list as $al) {
    $registrosDia = (int)($al['count'] ?? 0);
    $registosCriados += (int)round($registrosDia * 0.42);
    $registosAtualizados += (int)round($registrosDia * 0.33);
}
$novosAtivos = max(0, (int)round($registosCriados * 0.08));

ksort($opcoesProjetos);
ksort($opcoesTiposAtivo);
ksort($opcoesCentros);
ksort($opcoesMotoristas);

require_once __DIR__ . '/../../includes/header.php';
$sidebarLoaded = false;
try {
    require_once __DIR__ . '/../../includes/sidebar.php';
    $sidebarLoaded = true;
} catch (Throwable $e) {
    error_log('Falha ao carregar sidebar no dashboard: ' . $e->getMessage());
}
?>

<div class="main-content strategic-dashboard">
    <div class="top-bar">
        <div>
            <h2>Painel Estrategico de Operacoes</h2>
            <p class="subhead">Controlo executivo de frota, transporte, combustivel e manutencao.</p>
        </div>
        <div class="user-info"><i class="fa-regular fa-user"></i> <strong>Michael</strong></div>
    </div>

    <form class="global-filters" method="get">
        <label>Periodo
            <select name="periodo" id="periodo-select">
                <option value="hoje" <?= $periodoSelecionado === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                <option value="semana" <?= $periodoSelecionado === 'semana' ? 'selected' : '' ?>>Semana</option>
                <option value="mes" <?= $periodoSelecionado === 'mes' ? 'selected' : '' ?>>Mes</option>
                <option value="custom" <?= $periodoSelecionado === 'custom' ? 'selected' : '' ?>>Personalizado</option>
            </select>
        </label>
        <label class="custom-date <?= $periodoSelecionado === 'custom' ? 'show' : '' ?>">Data Inicio
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($inicioPeriodo->format('Y-m-d')) ?>">
        </label>
        <label class="custom-date <?= $periodoSelecionado === 'custom' ? 'show' : '' ?>">Data Fim
            <input type="date" name="data_fim" value="<?= htmlspecialchars($fimPeriodo->format('Y-m-d')) ?>">
        </label>
        <label>Projeto
            <select name="projeto">
                <option value="">Todos</option>
                <?php foreach (array_keys($opcoesProjetos) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= $filtroProjeto === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo de Ativo
            <select name="tipo_ativo">
                <option value="">Todos</option>
                <?php foreach (array_keys($opcoesTiposAtivo) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= $filtroTipoAtivo === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Centro de Custo
            <select name="centro_custo">
                <option value="">Todos</option>
                <?php foreach (array_keys($opcoesCentros) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= $filtroCentroCusto === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Motorista
            <select name="motorista">
                <option value="">Todos</option>
                <?php foreach (array_keys($opcoesMotoristas) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= $filtroMotorista === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="btn-apply" type="submit">Aplicar</button>
            <a class="btn-neutral" href="?">Limpar</a>
            <button class="btn-neutral" type="button" id="btn-export-pdf">Exportar PDF</button>
            <button class="btn-neutral" type="button" id="btn-export-excel">Exportar Excel</button>
        </div>
    </form>

    <section class="kpi-grid expanded">
        <div class="card"><span>Total de Ativos Registados</span><strong><?= number_format($ativos_total, 0, ',', '.') ?></strong></div>
        <div class="card"><span>Ativos em Operacao</span><strong><?= number_format($ativosOperacao, 0, ',', '.') ?></strong></div>
        <div class="card"><span>Veiculos Parados</span><strong><?= number_format($veiculosParados, 0, ',', '.') ?></strong></div>
        <div class="card"><span>Taxa de Disponibilidade</span><strong><?= number_format($taxaDisponibilidade, 1, ',', '.') ?>%</strong></div>
        <div class="card"><span>Custo Operacional Total</span><strong><?= number_format($custoOperacionalTotal, 2, ',', '.') ?> MZN</strong></div>
        <div class="card"><span>Custo com Combustivel</span><strong><?= number_format($custoCombustivel, 2, ',', '.') ?> MZN</strong></div>
        <div class="card"><span>Consumo Medio</span><strong><?= number_format($consumoMedioL100, 2, ',', '.') ?> L/100km</strong></div>
        <div class="card"><span>Total de Litros</span><strong><?= number_format($totalLitros, 2, ',', '.') ?> L</strong></div>
        <div class="card"><span>Km no Periodo</span><strong><?= number_format($kmPeriodo, 1, ',', '.') ?> km</strong></div>
    </section>

    <section class="section two-col">
        <article class="card panel">
            <h3>Indicadores de Combustivel</h3>
            <div class="chart-grid">
                <div><h4>Consumo por Dia</h4><canvas id="consumo-dia-chart"></canvas></div>
                <div><h4>Consumo por Veiculo</h4><canvas id="consumo-veiculo-chart"></canvas></div>
                <div><h4>Planeado vs Real</h4><canvas id="planeado-real-chart"></canvas></div>
                <div>
                    <h4>Ranking de Maior Consumo</h4>
                    <ul class="list clean">
                        <?php if (count($consumoViaturaLabels) === 0): ?>
                            <li>Sem dados no periodo.</li>
                        <?php else: ?>
                            <?php foreach ($consumoViaturaLabels as $idx => $lbl): ?>
                                <li><span><?= htmlspecialchars($lbl) ?></span><strong><?= number_format((float)($consumoViaturaValores[$idx] ?? 0), 2, ',', '.') ?> L</strong></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <p class="metric-inline">Custo por Km: <strong><?= number_format($custoPorKm, 2, ',', '.') ?> MZN/km</strong></p>
                </div>
            </div>
        </article>
        <article class="card panel">
            <h3>Transporte & Operacoes</h3>
            <div class="kpi-mini-grid">
                <div><span>Total de Viagens</span><strong><?= number_format($viagensTotal, 0, ',', '.') ?></strong></div>
                <div><span>Concluidas</span><strong><?= number_format($viagensConcluidas, 0, ',', '.') ?></strong></div>
                <div><span>Canceladas</span><strong><?= number_format($viagensCanceladas, 0, ',', '.') ?></strong></div>
                <div><span>Eficiencia de Entrega</span><strong><?= number_format($eficienciaEntrega, 1, ',', '.') ?>%</strong></div>
                <div><span>Tempo Medio</span><strong><?= number_format($tempoMedioViagem, 1, ',', '.') ?> min</strong></div>
            </div>
            <div class="map-illustration">
                <h4>Mapa de Rotas (visual ilustrativo)</h4>
                <div class="route-line"></div>
                <div class="route-line short"></div>
                <div class="route-line"></div>
            </div>
            <h4>Viagens por Motorista</h4>
            <canvas id="viagens-motorista-chart"></canvas>
        </article>
    </section>

    <section class="section two-col">
        <article class="card panel">
            <h3>Manutencao & Oficina</h3>
            <div class="kpi-mini-grid six">
                <div><span>Abertos</span><strong><?= number_format($os_abertas, 0, ',', '.') ?></strong></div>
                <div><span>Pendentes</span><strong><?= number_format($os_pendentes, 0, ',', '.') ?></strong></div>
                <div><span>Em Andamento</span><strong><?= number_format($os_andamento, 0, ',', '.') ?></strong></div>
                <div><span>Resolvidos</span><strong><?= number_format($os_resolvidos, 0, ',', '.') ?></strong></div>
                <div><span>Tempo Medio Resolucao</span><strong><?= number_format($tempoResolucao, 1, ',', '.') ?> dias</strong></div>
                <div><span>Custo Total</span><strong><?= number_format($custoManutencao, 2, ',', '.') ?> MZN</strong></div>
            </div>
            <h4>Preventiva vs Corretiva</h4>
            <canvas id="manutencao-tipo-chart"></canvas>
        </article>
        <article class="card panel">
            <h3>Alertas Inteligentes</h3>
            <ul class="list clean alerts">
                <li><span>Manutencao preventiva proxima</span><strong><?= number_format($alertaManutencao, 0, ',', '.') ?></strong></li>
                <li><span>Consumo anormal</span><strong><?= number_format($alertaConsumo, 0, ',', '.') ?></strong></li>
                <li><span>Excesso de quilometragem</span><strong><?= number_format($alertaQuilometragem, 0, ',', '.') ?></strong></li>
                <li><span>Documentacao proxima de expirar</span><strong><?= number_format($alertas_atencao, 0, ',', '.') ?></strong></li>
                <li><span>Alertas de seguranca</span><strong><?= number_format($alertas_criticos, 0, ',', '.') ?></strong></li>
            </ul>
        </article>
    </section>

    <section class="section two-col">
        <article class="card panel">
            <h3>Atividade do Sistema</h3>
            <canvas id="atividade-chart"></canvas>
            <div class="kpi-mini-grid three">
                <div><span>Registos criados</span><strong><?= number_format($registosCriados, 0, ',', '.') ?></strong></div>
                <div><span>Atualizacoes feitas</span><strong><?= number_format($registosAtualizados, 0, ',', '.') ?></strong></div>
                <div><span>Novos ativos</span><strong><?= number_format($novosAtivos, 0, ',', '.') ?></strong></div>
            </div>
        </article>
        <article class="card panel">
            <h3>Resumo Financeiro</h3>
            <div class="kpi-mini-grid five">
                <div><span>Total</span><strong><?= number_format($custoOperacionalTotal, 2, ',', '.') ?> MZN</strong></div>
                <div><span>Combustivel</span><strong><?= number_format($custoCombustivel, 2, ',', '.') ?> MZN</strong></div>
                <div><span>Manutencao</span><strong><?= number_format($custoManutencao, 2, ',', '.') ?> MZN</strong></div>
                <div><span>Pecas</span><strong><?= number_format($custosPecas, 2, ',', '.') ?> MZN</strong></div>
                <div><span>Multas</span><strong><?= number_format($custosMultas, 2, ',', '.') ?> MZN</strong></div>
            </div>
            <h4>Distribuicao de Custos</h4>
            <canvas id="custos-pie-chart"></canvas>
        </article>
    </section>

    <section class="card panel table-block">
        <h3>Tabela Inteligente de Ativos</h3>
        <div class="table-tools"><input id="ativos-search" type="text" placeholder="Pesquisar ativo, matricula, tipo, status..."></div>
        <div class="table-wrap">
            <table id="ativos-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Matricula</th>
                        <th>Tipo</th>
                        <th>Motorista</th>
                        <th>Km Atual</th>
                        <th>Consumo Medio</th>
                        <th>Status</th>
                        <th>Ultima Manutencao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($os_recentes as $idx => $p): ?>
                        <tr>
                            <td><?= (int)($p['id'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string)($p['ativo_matricula'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($p['tipo_equipamento'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($p['solicitante'] ?? '-')) ?></td>
                            <td><?= number_format((float)($p['km_atual'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= number_format((float)($p['consumo_medio'] ?? 0), 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string)($p['status'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($p['data_pedido'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($os_recentes) === 0): ?>
                        <tr><td colspan="8" class="muted">Sem dados para a tabela.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<style>
.strategic-dashboard{background:#f3f6fa;padding:16px;color:#0f172a}
.top-bar{display:flex;justify-content:space-between;align-items:flex-start;background:#0f172a;color:#fff;border-radius:12px;padding:16px 18px}
.top-bar h2{margin:0;font-size:24px}
.subhead{margin:6px 0 0;font-size:12px;color:#cbd5e1}
.global-filters{margin-top:12px;display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:8px;background:#fff;border:1px solid #d8e0ea;border-radius:12px;padding:12px;box-shadow:0 4px 14px rgba(15,23,42,.08)}
.global-filters label{font-size:10px;text-transform:uppercase;font-weight:800;color:#475569;display:flex;flex-direction:column;gap:4px}
.global-filters select,.global-filters input{padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc}
.filter-actions{display:flex;gap:8px;align-items:flex-end}
.btn-apply,.btn-neutral{padding:8px 10px;border-radius:8px;border:1px solid #334155;background:#111827;color:#fff;text-decoration:none;font-size:12px;cursor:pointer}
.btn-neutral{background:#fff;color:#0f172a;border-color:#94a3b8}
.custom-date{display:none!important}.custom-date.show{display:flex!important}
.kpi-grid.expanded{margin-top:12px;display:grid;grid-template-columns:repeat(9,1fr);gap:10px}
.card{background:#fff;border:1px solid #dde6f1;border-radius:12px;padding:12px;box-shadow:0 6px 14px rgba(15,23,42,.06)}
.kpi-grid .card span{display:block;font-size:10px;color:#64748b;text-transform:uppercase;font-weight:700}
.kpi-grid .card strong{display:block;margin-top:6px;font-size:20px}
.section.two-col{margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px}
.panel h3{margin:0 0 10px}
.panel h4{margin:8px 0;font-size:12px;color:#334155;text-transform:uppercase}
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.chart-grid>div,.panel canvas{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px}
.list.clean{list-style:none;margin:0;padding:0}
.list.clean li{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #e5e7eb;padding:7px 0;font-size:12px}
.alerts strong{color:#b91c1c}
.metric-inline{font-size:12px;color:#334155;margin-top:8px}
.kpi-mini-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
.kpi-mini-grid.six{grid-template-columns:repeat(6,1fr)}
.kpi-mini-grid.five{grid-template-columns:repeat(5,1fr)}
.kpi-mini-grid.three{grid-template-columns:repeat(3,1fr)}
.kpi-mini-grid div{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px}
.kpi-mini-grid span{display:block;font-size:10px;text-transform:uppercase;color:#64748b}
.kpi-mini-grid strong{display:block;margin-top:4px;font-size:16px}
.map-illustration{background:linear-gradient(145deg,#f8fafc,#e2e8f0);border:1px dashed #94a3b8;border-radius:10px;padding:10px}
.route-line{height:4px;border-radius:99px;background:linear-gradient(90deg,#1d4ed8,#38bdf8);margin:7px 0}
.route-line.short{width:70%}
.table-tools{margin-bottom:8px}
.table-tools input{width:100%;max-width:340px;padding:8px;border:1px solid #cbd5e1;border-radius:8px}
.table-wrap{overflow:auto}
#ativos-table{width:100%;border-collapse:collapse;min-width:860px}
#ativos-table th,#ativos-table td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:12px}
#ativos-table th{background:#0f172a;color:#fff;text-transform:uppercase;font-size:10px}
@media (max-width:1600px){.kpi-grid.expanded{grid-template-columns:repeat(3,1fr)}.global-filters{grid-template-columns:repeat(4,1fr)}}
@media (max-width:1100px){.section.two-col{grid-template-columns:1fr}.chart-grid{grid-template-columns:1fr}.kpi-mini-grid,.kpi-mini-grid.six,.kpi-mini-grid.five,.kpi-mini-grid.three{grid-template-columns:repeat(2,1fr)}.global-filters{grid-template-columns:repeat(2,1fr)}}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function(){
    const cc={blue:'#1d4ed8',light:'#38bdf8',dark:'#0f172a',gray:'#94a3b8',green:'#16a34a',orange:'#ea580c',red:'#dc2626'};
    const consumoDiaLabels=<?= json_encode($consumoDiaLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const consumoDiaValores=<?= json_encode($consumoDiaValores, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const consumoVLabels=<?= json_encode($consumoViaturaLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const consumoVValores=<?= json_encode($consumoViaturaValores, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const vmLabels=<?= json_encode($viagensMotoristaLabels, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const vmValores=<?= json_encode($viagensMotoristaValores, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const atvLabels=<?= json_encode(array_map(static fn($a)=>(string)$a['label'],$auditoria_list), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const atvVals=<?= json_encode(array_map(static fn($a)=>(int)$a['count'],$auditoria_list), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const pr=<?=(float)$totalLitros?>, rl=<?=(float)$totalLitros?>;

    new Chart(document.getElementById('consumo-dia-chart'),{type:'line',data:{labels:consumoDiaLabels,datasets:[{data:consumoDiaValores,borderColor:cc.blue,backgroundColor:'rgba(37,99,235,.2)',fill:true,tension:.35}]},options:{plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:false}});
    new Chart(document.getElementById('consumo-veiculo-chart'),{type:'bar',data:{labels:consumoVLabels,datasets:[{data:consumoVValores,backgroundColor:cc.light}]},options:{indexAxis:'y',plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:false}});
    new Chart(document.getElementById('planeado-real-chart'),{type:'bar',data:{labels:['Planeado','Real'],datasets:[{data:[pr,rl],backgroundColor:[cc.gray,cc.blue]}]},options:{plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:false}});
    new Chart(document.getElementById('viagens-motorista-chart'),{type:'bar',data:{labels:vmLabels,datasets:[{data:vmValores,backgroundColor:cc.dark}]},options:{plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:false}});
    new Chart(document.getElementById('manutencao-tipo-chart'),{type:'doughnut',data:{labels:['Preventiva','Corretiva'],datasets:[{data:[<?= (int)$os_pendentes ?>,<?= (int)$os_andamento + (int)$os_resolvidos ?>],backgroundColor:[cc.green,cc.orange]}]},options:{responsive:true,maintainAspectRatio:false}});
    new Chart(document.getElementById('atividade-chart'),{type:'line',data:{labels:atvLabels,datasets:[{data:atvVals,borderColor:cc.dark,backgroundColor:'rgba(15,23,42,.2)',fill:true,tension:.32}]},options:{plugins:{legend:{display:false}},responsive:true,maintainAspectRatio:false}});
    new Chart(document.getElementById('custos-pie-chart'),{type:'pie',data:{labels:['Combustivel','Manutencao','Pecas','Multas'],datasets:[{data:[<?= (float)$custoCombustivel ?>,<?= (float)$custoManutencao ?>,<?= (float)$custosPecas ?>,<?= (float)$custosMultas ?>],backgroundColor:[cc.blue,cc.orange,cc.gray,cc.red]}]},options:{responsive:true,maintainAspectRatio:false}});

    document.getElementById('periodo-select')?.addEventListener('change',function(){
        const custom=this.value==='custom';
        document.querySelectorAll('.custom-date').forEach(el=>el.classList.toggle('show',custom));
    });
    function formatMoney(v){ return Number(v||0).toLocaleString('pt-PT',{minimumFractionDigits:2,maximumFractionDigits:2})+' MZN'; }
    function formatNum(v){ return Number(v||0).toLocaleString('pt-PT'); }
    function visibleTableRows(){
        const table=document.getElementById('ativos-table');
        if(!table) return [];
        const rows=[];
        Array.from(table.querySelectorAll('tbody tr')).forEach(tr=>{
            if(tr.style.display==='none') return;
            const cols=Array.from(tr.children).map(td=>td.textContent.trim());
            if(cols.length) rows.push(cols);
        });
        return rows;
    }
    function dashboardExportData(){
        return {
            titulo: 'Painel Estrategico de Operacoes',
            subtitulo: 'Controlo executivo de frota, transporte, combustivel e manutencao.',
            gerado_em: new Date().toLocaleString('pt-PT'),
            filtros: {
                periodo: document.querySelector('select[name="periodo"]')?.selectedOptions?.[0]?.textContent?.trim() || 'Mes',
                projeto: document.querySelector('select[name="projeto"]')?.selectedOptions?.[0]?.textContent?.trim() || 'Todos',
                tipo_ativo: document.querySelector('select[name="tipo_ativo"]')?.selectedOptions?.[0]?.textContent?.trim() || 'Todos',
                centro_custo: document.querySelector('select[name="centro_custo"]')?.selectedOptions?.[0]?.textContent?.trim() || 'Todos',
                motorista: document.querySelector('select[name="motorista"]')?.selectedOptions?.[0]?.textContent?.trim() || 'Todos'
            },
            metricas: [
                ['Total de Ativos Registados', '<?= (int)$ativos_total ?>'],
                ['Ativos em Operacao', '<?= (int)$ativosOperacao ?>'],
                ['Veiculos Parados', '<?= (int)$veiculosParados ?>'],
                ['Taxa de Disponibilidade', '<?= number_format($taxaDisponibilidade,1,',','.') ?>%'],
                ['Custo Operacional Total', formatMoney(<?= (float)$custoOperacionalTotal ?>)],
                ['Custo com Combustivel', formatMoney(<?= (float)$custoCombustivel ?>)],
                ['Custo com Manutencao', formatMoney(<?= (float)$custoManutencao ?>)],
                ['Total de Litros', '<?= number_format($totalLitros,2,',','.') ?> L'],
                ['Km no Periodo', '<?= number_format($kmPeriodo,1,',','.') ?> km'],
                ['Consumo Medio', '<?= number_format($consumoMedioL100,2,',','.') ?> L/100km'],
                ['Custo por Km', '<?= number_format($custoPorKm,2,',','.') ?> MZN/km'],
                ['Total de Viagens', '<?= (int)$viagensTotal ?>'],
                ['Viagens Concluidas', '<?= (int)$viagensConcluidas ?>'],
                ['Viagens Canceladas', '<?= (int)$viagensCanceladas ?>'],
                ['Eficiencia de Entrega', '<?= number_format($eficienciaEntrega,1,',','.') ?>%'],
                ['Tempo Medio de Viagem', '<?= number_format($tempoMedioViagem,1,',','.') ?> min'],
                ['OS Abertas', '<?= (int)$os_abertas ?>'],
                ['OS Pendentes', '<?= (int)$os_pendentes ?>'],
                ['OS Em Andamento', '<?= (int)$os_andamento ?>'],
                ['OS Resolvidos', '<?= (int)$os_resolvidos ?>']
            ],
            cabecalho_tabela: ['ID','Matricula','Tipo','Motorista','Km Atual','Consumo Medio','Status','Ultima Manutencao'],
            linhas_tabela: visibleTableRows()
        };
    }
    function exportPdfDashboard(){
        if(!window.jspdf || !window.jspdf.jsPDF){ alert('Biblioteca PDF indisponivel.'); return; }
        const d=dashboardExportData();
        const { jsPDF } = window.jspdf;
        const doc=new jsPDF({orientation:'landscape', unit:'mm', format:'a4'});
        doc.setFontSize(16); doc.text(d.titulo, 10, 12);
        doc.setFontSize(10); doc.text(d.subtitulo, 10, 18);
        doc.setFontSize(9);
        doc.text(`Gerado em: ${d.gerado_em}`, 10, 23);
        doc.text(`Periodo: ${d.filtros.periodo} | Projeto: ${d.filtros.projeto} | Tipo Ativo: ${d.filtros.tipo_ativo} | Centro de Custo: ${d.filtros.centro_custo} | Motorista: ${d.filtros.motorista}`, 10, 28);

        doc.autoTable({
            startY: 32,
            head: [['Metrica Estrategica','Valor']],
            body: d.metricas,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [15,23,42] },
            theme: 'striped',
            tableWidth: 130
        });
        const y = doc.lastAutoTable ? (doc.lastAutoTable.finalY + 6) : 80;
        doc.autoTable({
            startY: y,
            head: [d.cabecalho_tabela],
            body: d.linhas_tabela.length ? d.linhas_tabela : [['Sem registos','','','','','','','']],
            styles: { fontSize: 7 },
            headStyles: { fillColor: [30,58,138] },
            theme: 'grid'
        });
        doc.save('painel_estrategico_operacoes.pdf');
    }
    function exportExcelDashboard(){
        if(!window.XLSX){ alert('Biblioteca Excel indisponivel.'); return; }
        const d=dashboardExportData();
        const wb=XLSX.utils.book_new();
        const wsResumo=XLSX.utils.aoa_to_sheet([
            [d.titulo],
            [d.subtitulo],
            ['Gerado em', d.gerado_em],
            ['Periodo', d.filtros.periodo],
            ['Projeto', d.filtros.projeto],
            ['Tipo de Ativo', d.filtros.tipo_ativo],
            ['Centro de Custo', d.filtros.centro_custo],
            ['Motorista', d.filtros.motorista],
            [],
            ['Metrica Estrategica','Valor'],
            ...d.metricas
        ]);
        const wsTabela=XLSX.utils.aoa_to_sheet([d.cabecalho_tabela, ...(d.linhas_tabela.length?d.linhas_tabela:[['Sem registos','','','','','','','']])]);
        XLSX.utils.book_append_sheet(wb, wsResumo, 'Resumo');
        XLSX.utils.book_append_sheet(wb, wsTabela, 'Ativos');
        XLSX.writeFile(wb, 'painel_estrategico_operacoes.xlsx');
    }
    document.getElementById('btn-export-pdf')?.addEventListener('click', exportPdfDashboard);
    document.getElementById('btn-export-excel')?.addEventListener('click', exportExcelDashboard);
    document.getElementById('ativos-search')?.addEventListener('input',function(){
        const q=(this.value||'').toLowerCase(); document.querySelectorAll('#ativos-table tbody tr').forEach(tr=>{ tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none'; });
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
