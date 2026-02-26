<?php
$erro_historico = null;

$pesquisa = trim((string)($_GET['q'] ?? ''));
$categoria_filtro = trim((string)($_GET['categoria'] ?? 'todos'));
$status_filtro = trim((string)($_GET['status'] ?? 'todos'));
$janela_alerta = (int)($_GET['janela'] ?? 30);
if ($janela_alerta < 1 || $janela_alerta > 365) {
    $janela_alerta = 30;
}

$linhas = [];

function parseDataISO(?string $valor): ?DateTime {
    $raw = trim((string)$valor);
    if ($raw === '' || $raw === '0000-00-00') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    if ($dt instanceof DateTime) {
        return $dt;
    }

    $dtAlt = DateTime::createFromFormat('d/m/Y', $raw);
    if ($dtAlt instanceof DateTime) {
        return $dtAlt;
    }

    return null;
}

function diasParaValidade(?string $data): ?int {
    $dt = parseDataISO($data);
    if (!$dt) {
        return null;
    }
    $hoje = new DateTime('today');
    return (int)$hoje->diff($dt)->format('%r%a');
}

function classificaStatusValidade(?int $dias, int $janela): array {
    if ($dias === null) {
        return ['key' => 'sem_data', 'label' => 'Sem validade', 'pill' => 'info'];
    }
    if ($dias < 0) {
        return ['key' => 'vencido', 'label' => 'Vencido', 'pill' => 'danger'];
    }
    if ($dias <= $janela) {
        return ['key' => 'proximo', 'label' => 'A vencer', 'pill' => 'warn'];
    }
    return ['key' => 'em_dia', 'label' => 'Em dia', 'pill' => 'ok'];
}

function textoPrazo(?int $dias): string {
    if ($dias === null) {
        return 'Sem data';
    }
    if ($dias < 0) {
        return 'Vencido ha ' . abs($dias) . ' dia(s)';
    }
    if ($dias === 0) {
        return 'Vence hoje';
    }
    return 'Faltam ' . $dias . ' dia(s)';
}

function passaFiltroRelatorio(array $row, string $q, string $categoria, string $status): bool {
    if ($categoria !== '' && $categoria !== 'todos' && (string)($row['categoria_key'] ?? '') !== $categoria) {
        return false;
    }
    if ($status !== '' && $status !== 'todos' && (string)($row['status_key'] ?? '') !== $status) {
        return false;
    }
    if ($q !== '') {
        $texto = strtolower(
            (string)($row['identificacao'] ?? '') . ' ' .
            (string)($row['categoria_nome'] ?? '') . ' ' .
            (string)($row['info'] ?? '') . ' ' .
            (string)($row['origem'] ?? '')
        );
        if (strpos($texto, strtolower($q)) === false) {
            return false;
        }
    }
    return true;
}

try {
    $stmtAtivos = $pdo->query("SELECT id, matricula, equipamento, estado, seguros, inspeccao, manifesto, livrete FROM activos");
    $ativos = $stmtAtivos ? ($stmtAtivos->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $mapaDocsAtivo = [
        'seguros' => 'Seguro',
        'inspeccao' => 'Inspecao',
        'manifesto' => 'Manifesto',
        'livrete' => 'Livrete',
    ];

    foreach ($ativos as $a) {
        $estadoAtivo = strtoupper(trim((string)($a['estado'] ?? '')));
        if ($estadoAtivo === 'VENDIDO') {
            continue;
        }

        $matricula = trim((string)($a['matricula'] ?? ''));
        $ident = $matricula !== '' ? $matricula : ('ATV-' . (int)($a['id'] ?? 0));
        $equip = trim((string)($a['equipamento'] ?? 'Ativo'));

        foreach ($mapaDocsAtivo as $campo => $rotuloDoc) {
            $dataVal = trim((string)($a[$campo] ?? ''));
            if ($dataVal === '' || $dataVal === '0000-00-00') {
                continue;
            }
            $dias = diasParaValidade($dataVal);
            $status = classificaStatusValidade($dias, $janela_alerta);
            $linhas[] = [
                'identificacao' => $ident,
                'categoria_key' => 'ativos',
                'categoria_nome' => 'Ativos / ' . $equip,
                'info' => 'Documento: ' . $rotuloDoc,
                'data_validade' => $dataVal,
                'dias' => $dias,
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'status_pill' => $status['pill'],
                'origem' => 'Cadastro de Ativos',
                'acao_link' => '?view=ativos&ativo_id=' . (int)($a['id'] ?? 0) . '&aplicar=1',
                'acao_titulo' => 'Abrir ativo',
            ];
        }
    }

    try {
        $sqlAtivosDocs = "
            SELECT ad.id, ad.activo_id, ad.tipo_documento, ad.validade, a.matricula, a.equipamento, a.estado
            FROM activos_documentos ad
            INNER JOIN activos a ON a.id = ad.activo_id
            WHERE ad.validade IS NOT NULL AND ad.validade <> '0000-00-00'
        ";
        $stmtAtivosDocs = $pdo->query($sqlAtivosDocs);
        $ativosDocs = $stmtAtivosDocs ? ($stmtAtivosDocs->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($ativosDocs as $doc) {
            $estadoAtivo = strtoupper(trim((string)($doc['estado'] ?? '')));
            if ($estadoAtivo === 'VENDIDO') {
                continue;
            }
            $dataVal = trim((string)($doc['validade'] ?? ''));
            if ($dataVal === '' || $dataVal === '0000-00-00') {
                continue;
            }
            $dias = diasParaValidade($dataVal);
            $status = classificaStatusValidade($dias, $janela_alerta);
            $matricula = trim((string)($doc['matricula'] ?? ''));
            $ident = $matricula !== '' ? $matricula : ('ATV-' . (int)($doc['activo_id'] ?? 0));
            $equip = trim((string)($doc['equipamento'] ?? 'Ativo'));
            $tipoDoc = trim((string)($doc['tipo_documento'] ?? 'Documento'));

            $linhas[] = [
                'identificacao' => $ident,
                'categoria_key' => 'ativos',
                'categoria_nome' => 'Ativos / ' . $equip,
                'info' => 'Documento: ' . $tipoDoc,
                'data_validade' => $dataVal,
                'dias' => $dias,
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'status_pill' => $status['pill'],
                'origem' => 'Documentos do Ativo',
                'acao_link' => '?view=ativos&ativo_id=' . (int)($doc['activo_id'] ?? 0) . '&aplicar=1',
                'acao_titulo' => 'Abrir ativo',
            ];
        }
    } catch (Throwable $e) {
        // tabela opcional pode nao existir
    }

    $sqlPessoal = "
        SELECT pd.id, pd.tipo_documento, pd.data_vencimento, p.id AS pessoal_id, p.nome, p.numero, c.nome AS cargo
        FROM pessoal_documentos pd
        INNER JOIN (
            SELECT MAX(id) AS id
            FROM pessoal_documentos
            GROUP BY pessoal_id, tipo_documento
        ) ult ON ult.id = pd.id
        INNER JOIN pessoal p ON p.id = pd.pessoal_id
        LEFT JOIN cargos c ON c.id = p.cargo_id
        WHERE pd.data_vencimento IS NOT NULL AND pd.data_vencimento <> '0000-00-00'
    ";
    $stmtPessoal = $pdo->query($sqlPessoal);
    $pessoalDocs = $stmtPessoal ? ($stmtPessoal->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    foreach ($pessoalDocs as $doc) {
        $dataVal = trim((string)($doc['data_vencimento'] ?? ''));
        if ($dataVal === '' || $dataVal === '0000-00-00') {
            continue;
        }
        $dias = diasParaValidade($dataVal);
        $status = classificaStatusValidade($dias, $janela_alerta);
        $nome = trim((string)($doc['nome'] ?? ''));
        $numero = trim((string)($doc['numero'] ?? ''));
        $ident = $nome !== '' ? $nome : ('EMP-' . (int)($doc['pessoal_id'] ?? 0));
        if ($numero !== '' && $numero !== '0') {
            $ident .= ' (#' . $numero . ')';
        }
        $cargo = trim((string)($doc['cargo'] ?? 'Sem cargo'));

        $linhas[] = [
            'identificacao' => $ident,
            'categoria_key' => 'pessoal',
            'categoria_nome' => 'Pessoal / ' . $cargo,
            'info' => 'Documento: ' . trim((string)($doc['tipo_documento'] ?? 'Documento')),
            'data_validade' => $dataVal,
            'dias' => $dias,
            'status_key' => $status['key'],
            'status_label' => $status['label'],
            'status_pill' => $status['pill'],
            'origem' => 'Documental Pessoal',
            'acao_link' => '?view=pessoal',
            'acao_titulo' => 'Abrir pessoal',
        ];
    }

    try {
        $sqlSeg = "SELECT id, item, tipo_alerta, data_validade, nivel, created_at FROM documental_seguranca WHERE data_validade IS NOT NULL AND data_validade <> '0000-00-00'";
        $stmtSeg = $pdo->query($sqlSeg);
        $rowsSeg = $stmtSeg ? ($stmtSeg->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($rowsSeg as $seg) {
            $dataVal = trim((string)($seg['data_validade'] ?? ''));
            if ($dataVal === '' || $dataVal === '0000-00-00') {
                continue;
            }
            $dias = diasParaValidade($dataVal);
            $status = classificaStatusValidade($dias, $janela_alerta);
            $item = trim((string)($seg['item'] ?? 'Alerta'));

            $linhas[] = [
                'identificacao' => $item,
                'categoria_key' => 'alertas',
                'categoria_nome' => 'Alertas / Seguranca',
                'info' => 'Tipo: ' . trim((string)($seg['tipo_alerta'] ?? 'Alerta')),
                'data_validade' => $dataVal,
                'dias' => $dias,
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'status_pill' => $status['pill'],
                'origem' => 'Alertas Manuais',
                'acao_link' => '?view=alertas',
                'acao_titulo' => 'Abrir alertas',
            ];
        }
    } catch (Throwable $e) {
        // tabela opcional pode nao existir
    }
} catch (Throwable $e) {
    $erro_historico = 'Nao foi possivel carregar o relatorio documental: ' . $e->getMessage();
}

$linhas_filtradas = [];
foreach ($linhas as $row) {
    if (passaFiltroRelatorio($row, $pesquisa, $categoria_filtro, $status_filtro)) {
        $linhas_filtradas[] = $row;
    }
}

usort($linhas_filtradas, static function(array $a, array $b): int {
    $da = $a['dias'];
    $db = $b['dias'];
    if ($da === null && $db === null) return strcmp((string)$a['identificacao'], (string)$b['identificacao']);
    if ($da === null) return 1;
    if ($db === null) return -1;
    if ($da !== $db) return $da <=> $db;
    return strcmp((string)$a['identificacao'], (string)$b['identificacao']);
});

$total_registos = count($linhas_filtradas);
$total_vencidos = 0;
$total_proximos = 0;
$total_em_dia = 0;
foreach ($linhas_filtradas as $row) {
    $k = (string)($row['status_key'] ?? '');
    if ($k === 'vencido') {
        $total_vencidos++;
    } elseif ($k === 'proximo') {
        $total_proximos++;
    } elseif ($k === 'em_dia') {
        $total_em_dia++;
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; gap:10px; flex-wrap:wrap;">
    <div>
        <h3 style="margin:0;">Relatorio Geral de Validade Documental</h3>
        <p style="font-size:12px; color:#6b7280; margin-top:4px;">Monitoramento consolidado de validade de documentos de ativos, pessoal e alertas de seguranca.</p>
    </div>
    <div class="pill info">Janela de alerta: <?= (int)$janela_alerta ?> dias</div>
</div>

<div style="display:grid; grid-template-columns:repeat(4,minmax(160px,1fr)); gap:10px; margin-bottom:14px;">
    <div style="border:1px solid #e5e7eb; border-radius:10px; background:#fff; padding:10px;"><strong>Total:</strong> <?= (int)$total_registos ?></div>
    <div style="border:1px solid #fecaca; border-radius:10px; background:#fff5f5; padding:10px;"><strong>Vencidos:</strong> <?= (int)$total_vencidos ?></div>
    <div style="border:1px solid #fcd34d; border-radius:10px; background:#fffbeb; padding:10px;"><strong>A vencer:</strong> <?= (int)$total_proximos ?></div>
    <div style="border:1px solid #bbf7d0; border-radius:10px; background:#f0fdf4; padding:10px;"><strong>Em dia:</strong> <?= (int)$total_em_dia ?></div>
</div>

<form method="get" class="filter-container" style="margin-bottom:14px;">
    <input type="hidden" name="view" value="relatorios">
    <div class="form-group" style="flex:1; min-width:240px;">
        <label>Pesquisa Global</label>
        <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Matricula, Nome, Documento...">
    </div>
    <div class="form-group" style="min-width:220px;">
        <label>Categoria</label>
        <select name="categoria">
            <option value="todos" <?= $categoria_filtro === 'todos' ? 'selected' : '' ?>>-- Tudo --</option>
            <option value="ativos" <?= $categoria_filtro === 'ativos' ? 'selected' : '' ?>>Ativos</option>
            <option value="pessoal" <?= $categoria_filtro === 'pessoal' ? 'selected' : '' ?>>Pessoal</option>
            <option value="alertas" <?= $categoria_filtro === 'alertas' ? 'selected' : '' ?>>Alertas</option>
        </select>
    </div>
    <div class="form-group" style="min-width:220px;">
        <label>Status</label>
        <select name="status">
            <option value="todos" <?= $status_filtro === 'todos' ? 'selected' : '' ?>>-- Todos --</option>
            <option value="vencido" <?= $status_filtro === 'vencido' ? 'selected' : '' ?>>Vencido</option>
            <option value="proximo" <?= $status_filtro === 'proximo' ? 'selected' : '' ?>>A vencer</option>
            <option value="em_dia" <?= $status_filtro === 'em_dia' ? 'selected' : '' ?>>Em dia</option>
        </select>
    </div>
    <div class="form-group" style="min-width:160px;">
        <label>Janela (dias)</label>
        <input type="number" name="janela" min="1" max="365" value="<?= (int)$janela_alerta ?>">
    </div>
    <button class="btn-save" type="submit" style="height:42px;"><i class="fas fa-filter"></i> Filtrar</button>
</form>

<?php if ($erro_historico !== null): ?>
    <div style="background:#ffe5e5; color:#b91c1c; border:1px solid #fecaca; border-radius:8px; padding:10px; font-weight:700; margin-bottom:12px;">
        <?= htmlspecialchars($erro_historico) ?>
    </div>
<?php endif; ?>

<table class="list-table">
    <thead>
        <tr>
            <th>Identificacao / Nome</th>
            <th>Categoria</th>
            <th>Info Adicional</th>
            <th>Status / Validade</th>
            <th style="text-align:center;">Acoes</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($linhas_filtradas)): ?>
            <tr>
                <td colspan="5" style="text-align:center; color:#6b7280;">Sem registos para os filtros selecionados.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($linhas_filtradas as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string)$row['identificacao']) ?></strong></td>
                    <td><?= htmlspecialchars((string)$row['categoria_nome']) ?></td>
                    <td>
                        <?= htmlspecialchars((string)$row['info']) ?>
                        <div style="font-size:11px; color:#6b7280; margin-top:3px;">Origem: <?= htmlspecialchars((string)$row['origem']) ?></div>
                    </td>
                    <td>
                        <span class="pill <?= htmlspecialchars((string)$row['status_pill']) ?>"><?= htmlspecialchars((string)$row['status_label']) ?></span>
                        <div style="font-size:11px; color:#6b7280; margin-top:3px;">
                            <?= htmlspecialchars((string)$row['data_validade']) ?> | <?= htmlspecialchars(textoPrazo($row['dias'])) ?>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <a href="<?= htmlspecialchars((string)$row['acao_link']) ?>" class="btn-mode" style="padding:6px 10px; font-size:11px;" title="<?= htmlspecialchars((string)$row['acao_titulo']) ?>">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

