<?php
$seguranca = [];
$erro_seguranca = null;
$msg_seguranca = null;

$pesquisa = trim((string)($_GET['q'] ?? ''));
$nivel_filtro = trim((string)($_GET['nivel'] ?? 'todos'));
$tipo_filtro = trim((string)($_GET['tipo'] ?? 'todos'));
$tipos_alerta = [];

function normalizarNivelSeguranca($dataValidade, $nivelBase = '') {
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

function classeNivelSeguranca($nivel) {
    if ($nivel === 'critico') return 'danger';
    if ($nivel === 'atencao') return 'warn';
    return 'ok';
}

function alertaDataAtivo(array $ativo, $campo, $tipoNome, $diasLimite = 30) {
    $valor = trim((string)($ativo[$campo] ?? ''));
    if ($valor === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$dt) {
        return null;
    }

    $hoje = new DateTime('today');
    $dias = (int)$hoje->diff($dt)->format('%r%a');

    if ($dias > $diasLimite) {
        return null;
    }

    $item = trim((string)($ativo['matricula'] ?? ''));
    if ($item === '') {
        $item = trim((string)($ativo['equipamento'] ?? 'Ativo #' . (string)($ativo['id'] ?? '-')));
    }

    $aviso = '';
    $nivel = 'normal';
    if ($dias < 0) {
        $nivel = 'critico';
        $aviso = strtoupper($tipoNome) . ' VENCIDO HA ' . abs($dias) . ' DIA(S). RENOVAR URGENTE.';
    } elseif ($dias === 0) {
        $nivel = 'critico';
        $aviso = strtoupper($tipoNome) . ' VENCE HOJE. BLOQUEAR OPERACAO ATE REGULARIZAR.';
    } else {
        $nivel = 'atencao';
        $aviso = strtoupper($tipoNome) . ' VENCE EM ' . $dias . ' DIA(S).';
    }

    return [
        'id' => 'AUTO-' . (string)($ativo['id'] ?? '0') . '-' . $campo,
        'item' => $item,
        'tipo_alerta' => $tipoNome,
        'data_validade' => $valor,
        'nivel' => $nivel,
        'observacoes' => $aviso,
        'created_at' => date('Y-m-d H:i:s'),
        'origem' => 'Automatico',
        'dias_restantes' => $dias,
    ];
}

function alertaRadioAtivo(array $ativo) {
    $radio = (string)($ativo['radio'] ?? '');
    if ($radio === '1') {
        return null;
    }

    $item = trim((string)($ativo['matricula'] ?? ''));
    if ($item === '') {
        $item = trim((string)($ativo['equipamento'] ?? 'Ativo #' . (string)($ativo['id'] ?? '-')));
    }

    return [
        'id' => 'AUTO-' . (string)($ativo['id'] ?? '0') . '-radio',
        'item' => $item,
        'tipo_alerta' => 'Taxa de Radio',
        'data_validade' => '',
        'nivel' => 'critico',
        'observacoes' => 'TAXA DE RADIO NAO REGULARIZADA. RESOLVER COM PRIORIDADE.',
        'created_at' => date('Y-m-d H:i:s'),
        'origem' => 'Automatico',
        'dias_restantes' => -9999,
    ];
}

function passaFiltroSeguranca(array $row, $pesquisa, $nivel, $tipo) {
    $texto = strtolower((string)($row['item'] ?? '') . ' ' . (string)($row['tipo_alerta'] ?? '') . ' ' . (string)($row['observacoes'] ?? ''));
    $q = strtolower(trim((string)$pesquisa));
    if ($q !== '' && strpos($texto, $q) === false) {
        return false;
    }

    if ($nivel !== '' && $nivel !== 'todos') {
        if (strtolower((string)($row['nivel'] ?? '')) !== strtolower($nivel)) {
            return false;
        }
    }

    if ($tipo !== '' && $tipo !== 'todos') {
        if ((string)($row['tipo_alerta'] ?? '') !== (string)$tipo) {
            return false;
        }
    }

    return true;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS documental_seguranca (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item VARCHAR(180) NOT NULL,
        tipo_alerta VARCHAR(100) NOT NULL,
        data_validade DATE NULL,
        nivel VARCHAR(20) NULL,
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'guardar_alerta') {
        $item = trim((string)($_POST['item'] ?? ''));
        $tipo_alerta = trim((string)($_POST['tipo_alerta'] ?? ''));
        $data_validade = trim((string)($_POST['data_validade'] ?? ''));
        $nivel = trim((string)($_POST['nivel'] ?? ''));
        $observacoes = trim((string)($_POST['observacoes'] ?? ''));

        if ($item === '' || $tipo_alerta === '') {
            throw new RuntimeException('Preencha pelo menos item e tipo de alerta.');
        }

        $nivel = strtolower($nivel);
        if (!in_array($nivel, ['critico', 'atencao', 'normal'], true)) {
            $nivel = normalizarNivelSeguranca($data_validade, '');
        }

        $dataVal = null;
        if ($data_validade !== '') {
            $dt = DateTime::createFromFormat('Y-m-d', $data_validade);
            if (!$dt) {
                throw new RuntimeException('Data de validade invalida.');
            }
            $dataVal = $dt->format('Y-m-d');
        }

        $stmtIns = $pdo->prepare(
            'INSERT INTO documental_seguranca (item, tipo_alerta, data_validade, nivel, observacoes)
             VALUES (:item, :tipo, :validade, :nivel, :obs)'
        );
        $stmtIns->bindValue(':item', $item);
        $stmtIns->bindValue(':tipo', $tipo_alerta);
        $stmtIns->bindValue(':validade', $dataVal, $dataVal === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmtIns->bindValue(':nivel', $nivel);
        $stmtIns->bindValue(':obs', $observacoes !== '' ? $observacoes : null, $observacoes !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmtIns->execute();

        if (function_exists('registrarAuditoria')) {
            registrarAuditoria($pdo, 'Inseriu alerta de seguranca', 'documental_seguranca');
        }

        header('Location: ?view=seguranca&saved=1');
        exit;
    }

    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $msg_seguranca = 'Alerta guardado com sucesso.';
    }

    $stmtManual = $pdo->query('SELECT id, item, tipo_alerta, data_validade, nivel, observacoes, created_at FROM documental_seguranca ORDER BY id DESC');
    $manual = $stmtManual->fetchAll(PDO::FETCH_ASSOC);

    $seguranca_todos = [];

    foreach ($manual as $row) {
        $row['nivel'] = normalizarNivelSeguranca((string)($row['data_validade'] ?? ''), (string)($row['nivel'] ?? ''));
        $row['origem'] = 'Manual';
        $row['dias_restantes'] = 9999;
        if (!empty($row['data_validade'])) {
            $hoje = new DateTime('today');
            $dv = DateTime::createFromFormat('Y-m-d', (string)$row['data_validade']);
            if ($dv) {
                $row['dias_restantes'] = (int)$hoje->diff($dv)->format('%r%a');
            }
        }
        $seguranca_todos[] = $row;
    }

    $stmtAtivos = $pdo->query("SELECT id, equipamento, matricula, livrete, seguros, inspeccao, manifesto, radio, estado FROM activos WHERE estado <> 'VENDIDO' OR estado IS NULL");
    $ativos = $stmtAtivos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ativos as $ativo) {
        $a1 = alertaDataAtivo($ativo, 'inspeccao', 'Inspeccao');
        $a2 = alertaDataAtivo($ativo, 'seguros', 'Seguro');
        $a3 = alertaDataAtivo($ativo, 'manifesto', 'Manifesto');
        $a4 = alertaDataAtivo($ativo, 'livrete', 'Livrete');
        $a5 = alertaRadioAtivo($ativo);

        foreach ([$a1, $a2, $a3, $a4, $a5] as $a) {
            if ($a !== null) {
                $seguranca_todos[] = $a;
            }
        }
    }

    $tipos = [];
    foreach ($seguranca_todos as $row) {
        $tipo = trim((string)($row['tipo_alerta'] ?? ''));
        if ($tipo !== '') {
            $tipos[$tipo] = true;
        }
    }
    $tipos_alerta = array_keys($tipos);
    sort($tipos_alerta);

    foreach ($seguranca_todos as $row) {
        if (passaFiltroSeguranca($row, $pesquisa, $nivel_filtro, $tipo_filtro)) {
            $seguranca[] = $row;
        }
    }

    usort($seguranca, function ($a, $b) {
        $peso = ['critico' => 0, 'atencao' => 1, 'normal' => 2];
        $na = strtolower((string)($a['nivel'] ?? 'normal'));
        $nb = strtolower((string)($b['nivel'] ?? 'normal'));
        $pa = $peso[$na] ?? 9;
        $pb = $peso[$nb] ?? 9;
        if ($pa !== $pb) return $pa <=> $pb;

        $da = (int)($a['dias_restantes'] ?? 9999);
        $db = (int)($b['dias_restantes'] ?? 9999);
        if ($da !== $db) return $da <=> $db;

        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });
} catch (Throwable $e) {
    $erro_seguranca = 'Nao foi possivel processar a seguranca: ' . $e->getMessage();
}

$total_alertas = count($seguranca);
$total_criticos = 0;
$total_atencao = 0;
$total_normais = 0;
$total_automaticos = 0;
$total_manuais = 0;
foreach ($seguranca as $row) {
    $nivel_row = strtolower((string)($row['nivel'] ?? 'normal'));
    if ($nivel_row === 'critico') $total_criticos++;
    elseif ($nivel_row === 'atencao') $total_atencao++;
    else $total_normais++;

    $origem_row = strtolower((string)($row['origem'] ?? 'manual'));
    if ($origem_row === 'automatico') $total_automaticos++;
    else $total_manuais++;
}
?>
<div data-mode-scope>
    <style>
        .seg-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }
        .seg-kpi {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
        }
        .seg-kpi .k-title {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 800;
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .seg-kpi .k-value {
            font-size: 20px;
            font-weight: 800;
            margin-top: 6px;
            color: #0f172a;
        }
        .seg-kpi.critico { background: #fef2f2; border-color: #fecaca; }
        .seg-kpi.atencao { background: #fff7ed; border-color: #fed7aa; }
        .seg-kpi.auto { background: #eff6ff; border-color: #bfdbfe; }
        .seg-kpi.manual { background: #f8fafc; border-color: #cbd5e1; }
        .seg-row-critico { background: #fff1f2; }
        .seg-row-atencao { background: #fff7ed; }
        .pill.danger { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
        .pill.warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .pill.ok { background:#ecfdf3; color:#15803d; border:1px solid #bbf7d0; }
        .pill.info { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        @media (max-width: 1100px) {
            .seg-kpi-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
        }
    </style>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-shield-halved"></i> Seguranca</h3>
            <p>Acompanhe alertas e adicione novos registos de seguranca.</p>
        </div>
        <div class="tool-actions">
            <button type="button" class="btn-mode active" data-target="seguranca-lista"><i class="fas fa-list"></i> Ver lista</button>
            <button type="button" class="btn-mode" data-target="seguranca-form"><i class="fas fa-plus"></i> Adicionar</button>
        </div>
    </div>

    <div class="seg-kpi-grid">
        <div class="seg-kpi">
            <div class="k-title"><i class="fa-solid fa-list-check"></i> Total alertas</div>
            <div class="k-value"><?= (int)$total_alertas ?></div>
        </div>
        <div class="seg-kpi critico">
            <div class="k-title"><i class="fa-solid fa-triangle-exclamation"></i> Criticos</div>
            <div class="k-value"><?= (int)$total_criticos ?></div>
        </div>
        <div class="seg-kpi atencao">
            <div class="k-title"><i class="fa-solid fa-bell"></i> Atencao</div>
            <div class="k-value"><?= (int)$total_atencao ?></div>
        </div>
        <div class="seg-kpi auto">
            <div class="k-title"><i class="fa-solid fa-robot"></i> Automaticos</div>
            <div class="k-value"><?= (int)$total_automaticos ?></div>
        </div>
        <div class="seg-kpi manual">
            <div class="k-title"><i class="fa-solid fa-user-pen"></i> Manuais</div>
            <div class="k-value"><?= (int)$total_manuais ?></div>
        </div>
    </div>

    <?php if ($erro_seguranca !== null): ?>
        <div style="margin-bottom:12px; background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:10px; border-radius:8px; font-size:12px;">
            <?= htmlspecialchars($erro_seguranca) ?>
        </div>
    <?php endif; ?>
    <?php if ($msg_seguranca !== null): ?>
        <div style="margin-bottom:12px; background:#ecfdf3; border:1px solid #bbf7d0; color:#166534; padding:10px; border-radius:8px; font-size:12px;">
            <?= htmlspecialchars($msg_seguranca) ?>
        </div>
    <?php endif; ?>

    <form class="filter-container" method="get" action="">
        <input type="hidden" name="view" value="seguranca">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Documento, ativo, alerta...">
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar nivel</label>
            <select name="nivel">
                <option value="todos" <?= $nivel_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="critico" <?= $nivel_filtro === 'critico' ? 'selected' : '' ?>>Critico</option>
                <option value="atencao" <?= $nivel_filtro === 'atencao' ? 'selected' : '' ?>>Atencao</option>
                <option value="normal" <?= $nivel_filtro === 'normal' ? 'selected' : '' ?>>Normal</option>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar tipo</label>
            <select name="tipo">
                <option value="todos" <?= $tipo_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                <?php foreach ($tipos_alerta as $tipo): ?>
                    <option value="<?= htmlspecialchars((string)$tipo) ?>" <?= $tipo_filtro === (string)$tipo ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
        <a href="?view=seguranca" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
    </form>

    <div id="seguranca-lista" class="panel-view">
        <table class="list-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Origem</th>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Validade</th>
                    <th>Prazo</th>
                    <th>Nivel</th>
                    <th>Aviso</th>
                    <th>Criado em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($seguranca)): ?>
                    <tr>
                        <td colspan="9">Sem alertas de seguranca para os filtros aplicados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($seguranca as $row): ?>
                        <?php
                        $nivel = strtolower((string)($row['nivel'] ?? 'normal'));
                        $classe = classeNivelSeguranca($nivel);
                        $isCritico = $nivel === 'critico';
                        ?>
                        <tr class="<?= $nivel === 'critico' ? 'seg-row-critico' : ($nivel === 'atencao' ? 'seg-row-atencao' : '') ?>">
                            <td><?= htmlspecialchars((string)($row['id'] ?? '-')) ?></td>
                            <td><span class="pill info"><?= htmlspecialchars((string)($row['origem'] ?? '-')) ?></span></td>
                            <td><strong><?= htmlspecialchars((string)($row['item'] ?? '-')) ?></strong></td>
                            <td><?= htmlspecialchars((string)($row['tipo_alerta'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($row['data_validade'] ?? '-')) ?></td>
                            <td>
                                <?php
                                    $dias = (int)($row['dias_restantes'] ?? 9999);
                                    if ($dias === 9999) {
                                        echo '-';
                                    } elseif ($dias < 0) {
                                        echo '<span class="pill danger">vencido ha ' . abs($dias) . 'd</span>';
                                    } elseif ($dias === 0) {
                                        echo '<span class="pill danger">vence hoje</span>';
                                    } elseif ($dias <= 30) {
                                        echo '<span class="pill warn">' . $dias . ' dias</span>';
                                    } else {
                                        echo '<span class="pill ok">' . $dias . ' dias</span>';
                                    }
                                ?>
                            </td>
                            <td><span class="pill <?= htmlspecialchars($classe) ?>"><?= htmlspecialchars(ucfirst($nivel)) ?></span></td>
                            <td style="font-weight:700; color:<?= $isCritico ? '#b91c1c' : '#92400e' ?>;">
                                <i class="fa-solid <?= $isCritico ? 'fa-triangle-exclamation' : 'fa-circle-info' ?>" style="margin-right:6px;"></i>
                                <?= htmlspecialchars((string)($row['observacoes'] ?? '-')) ?>
                            </td>
                            <td><?= htmlspecialchars((string)($row['created_at'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="seguranca-form" class="panel-view hidden">
        <form class="form-grid" method="post" action="">
            <input type="hidden" name="acao" value="guardar_alerta">
            <div class="section-title">Novo Alerta de Seguranca</div>
            <div class="form-group"><label>Ativo/Pessoa</label><input type="text" name="item" placeholder="Ex: ABC-123-MC" required></div>
            <div class="form-group">
                <label>Tipo de Alerta</label>
                <select name="tipo_alerta" required>
                    <option value="Seguro">Seguro</option>
                    <option value="Inspecao">Inspecao</option>
                    <option value="Manifesto">Manifesto</option>
                    <option value="Taxa de Radio">Taxa de Radio</option>
                    <option value="Licenca">Licenca</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="form-group"><label>Data de Validade</label><input type="date" name="data_validade"></div>
            <div class="form-group"><label>Nivel</label><select name="nivel"><option value="">Automatico</option><option value="critico">Critico</option><option value="atencao">Atencao</option><option value="normal">Normal</option></select></div>
            <div class="form-group" style="grid-column: span 4;"><label>Observacoes</label><textarea name="observacoes" rows="3" placeholder="Detalhes do alerta"></textarea></div>
            <div style="grid-column: span 3;"><button class="btn-save" type="submit">Guardar Alerta</button></div>
        </form>
    </div>
</div>
