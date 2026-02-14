<?php
$pessoal_lista = [];
$erro_pessoal = null;
$tipos_documento = [];

$pesquisa = trim((string)($_GET['q'] ?? ''));
$tipo_documento_filtro = trim((string)($_GET['tipo_documento'] ?? 'todos'));
$perfil_filtro = trim((string)($_GET['perfil'] ?? 'todos'));
$subfiltro_filtro = trim((string)($_GET['subfiltro'] ?? 'todos'));

$subfiltros_por_perfil = [
    'todos' => ['todos' => 'Todos'],
    'motorista' => [
        'todos' => 'Todos',
        'c_carta' => 'C. Carta (Conducao)',
        'c_profissional' => 'C. Profissional',
        'c_pesado' => 'C. Pesado',
        'c_mercadorias' => 'C. Mercadorias',
    ],
    'mecanico' => [
        'todos' => 'Todos',
        'c_formacao' => 'C. Formacao Tecnica',
        'c_certificacao' => 'C. Certificacao',
        'c_seguranca' => 'C. Seguranca',
    ],
    'operador' => [
        'todos' => 'Todos',
        'c_maquinas' => 'C. Maquinas/Equipamentos',
        'c_elevacao' => 'C. Elevacao',
        'c_grua' => 'C. Grua/Guindaste',
    ],
];

function normalizarTextoPessoal($texto) {
    $v = strtolower(trim((string)$texto));
    $de = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç'];
    $para = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c'];
    return str_replace($de, $para, $v);
}

function classificarPerfilPessoal($cargoId, $tipoDocumento) {
    $base = normalizarTextoPessoal((string)$cargoId . ' ' . (string)$tipoDocumento);

    if (strpos($base, 'motor') !== false || strpos($base, 'conduc') !== false || strpos($base, 'carta') !== false) {
        return 'motorista';
    }
    if (strpos($base, 'mecan') !== false) {
        return 'mecanico';
    }
    if (
        strpos($base, 'operador') !== false ||
        strpos($base, 'maquina') !== false ||
        strpos($base, 'escav') !== false ||
        strpos($base, 'grua') !== false ||
        strpos($base, 'guindaste') !== false
    ) {
        return 'operador';
    }

    return 'outros';
}

function correspondeSubfiltroPessoal($perfil, $subfiltro, $tipoDocumento) {
    if ($subfiltro === '' || $subfiltro === 'todos') {
        return true;
    }

    $doc = normalizarTextoPessoal($tipoDocumento);

    if ($perfil === 'motorista') {
        if ($subfiltro === 'c_carta') return strpos($doc, 'carta') !== false || strpos($doc, 'conduc') !== false;
        if ($subfiltro === 'c_profissional') return strpos($doc, 'profissional') !== false;
        if ($subfiltro === 'c_pesado') return strpos($doc, 'pesado') !== false;
        if ($subfiltro === 'c_mercadorias') return strpos($doc, 'mercadoria') !== false || strpos($doc, 'cargas') !== false;
        return true;
    }

    if ($perfil === 'mecanico') {
        if ($subfiltro === 'c_formacao') return strpos($doc, 'formacao') !== false || strpos($doc, 'treinamento') !== false;
        if ($subfiltro === 'c_certificacao') return strpos($doc, 'certific') !== false || strpos($doc, 'qualific') !== false;
        if ($subfiltro === 'c_seguranca') return strpos($doc, 'seguranca') !== false || strpos($doc, 'hse') !== false;
        return true;
    }

    if ($perfil === 'operador') {
        if ($subfiltro === 'c_maquinas') return strpos($doc, 'maquina') !== false || strpos($doc, 'equipamento') !== false || strpos($doc, 'operador') !== false;
        if ($subfiltro === 'c_elevacao') return strpos($doc, 'elevacao') !== false || strpos($doc, 'plataforma') !== false;
        if ($subfiltro === 'c_grua') return strpos($doc, 'grua') !== false || strpos($doc, 'guindaste') !== false || strpos($doc, 'ponte rolante') !== false;
        return true;
    }

    return true;
}

if (!isset($subfiltros_por_perfil[$perfil_filtro])) {
    $perfil_filtro = 'todos';
}
if (!isset($subfiltros_por_perfil[$perfil_filtro][$subfiltro_filtro])) {
    $subfiltro_filtro = 'todos';
}

try {
    $sqlTipos = "
        SELECT DISTINCT tipo_documento
        FROM pessoal_documentos
        WHERE tipo_documento IS NOT NULL
          AND tipo_documento <> ''
        ORDER BY tipo_documento ASC
    ";
    $stmtTipos = $pdo->query($sqlTipos);
    $tipos_documento = $stmtTipos->fetchAll(PDO::FETCH_COLUMN);

    $where = [];
    $params = [];

    if ($pesquisa !== '') {
        $where[] = "(p.nome LIKE :pesquisa OR CAST(p.numero AS CHAR) LIKE :pesquisa OR CAST(p.cargo_id AS CHAR) LIKE :pesquisa OR pd.tipo_documento LIKE :pesquisa)";
        $params[':pesquisa'] = '%' . $pesquisa . '%';
    }

    if ($tipo_documento_filtro !== '' && $tipo_documento_filtro !== 'todos') {
        $where[] = "pd.tipo_documento = :tipo_documento";
        $params[':tipo_documento'] = $tipo_documento_filtro;
    }

    $sql = "
        SELECT
            p.id AS pessoal_id,
            p.numero,
            p.nome,
            p.cargo_id,
            p.estado,
            pd.tipo_documento,
            pd.data_emissao,
            pd.data_vencimento,
            pd.created_at AS documento_created_at
        FROM pessoal p
        LEFT JOIN pessoal_documentos pd
            ON pd.pessoal_id = p.id
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY p.id ASC, pd.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultado as $item) {
        $perfilLinha = classificarPerfilPessoal($item['cargo_id'] ?? '', $item['tipo_documento'] ?? '');

        if ($perfil_filtro !== 'todos' && $perfilLinha !== $perfil_filtro) {
            continue;
        }

        if (!correspondeSubfiltroPessoal($perfil_filtro, $subfiltro_filtro, (string)($item['tipo_documento'] ?? ''))) {
            continue;
        }

        $item['perfil_classificado'] = $perfilLinha;
        $pessoal_lista[] = $item;
    }
} catch (Throwable $e) {
    $erro_pessoal = 'Nao foi possivel carregar os funcionarios.';
}

function labelPerfilPessoal($perfil) {
    if ($perfil === 'motorista') return 'Motorista';
    if ($perfil === 'mecanico') return 'Mecanico';
    if ($perfil === 'operador') return 'Operador';
    return 'Outros';
}
?>
<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-users"></i> Pessoal</h3>
            <p>O cadastro de pessoas e feito no modulo RH. Nesta area documental, apenas consultamos e filtramos documentos.</p>
        </div>
        <div class="tool-actions">
            <a href="/vilcon-systemon/app/modules/rh/index.php" class="btn-mode" style="text-decoration:none;display:inline-flex;align-items:center;">
                <i class="fas fa-arrow-up-right-from-square"></i> Ir para RH
            </a>
        </div>
    </div>

    <form class="filter-container" method="get" action="">
        <input type="hidden" name="view" value="pessoal">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Nome, numero, cargo, tipo documento...">
        </div>
        <div class="form-group">
            <label><i class="fas fa-users"></i> Perfil</label>
            <select name="perfil">
                <option value="todos" <?= $perfil_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="mecanico" <?= $perfil_filtro === 'mecanico' ? 'selected' : '' ?>>Mecanicos</option>
                <option value="motorista" <?= $perfil_filtro === 'motorista' ? 'selected' : '' ?>>Motoristas</option>
                <option value="operador" <?= $perfil_filtro === 'operador' ? 'selected' : '' ?>>Operadores</option>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fas fa-layer-group"></i> Subfiltro Perfil</label>
            <select name="subfiltro">
                <?php foreach (($subfiltros_por_perfil[$perfil_filtro] ?? ['todos' => 'Todos']) as $k => $label): ?>
                    <option value="<?= htmlspecialchars((string)$k) ?>" <?= $subfiltro_filtro === (string)$k ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar documento</label>
            <select name="tipo_documento">
                <option value="todos">Todos</option>
                <?php foreach ($tipos_documento as $tipo): ?>
                    <option value="<?= htmlspecialchars((string)$tipo) ?>" <?= $tipo_documento_filtro === (string)$tipo ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
        <a href="?view=pessoal" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
    </form>

    <div id="pessoal-lista" class="panel-view">
        <table class="list-table">
            <thead>
                <tr>
                    <th>Numero</th>
                    <th>Nome</th>
                    <th>Perfil</th>
                    <th>Tipo Documento</th>
                    <th>Data Emissao</th>
                    <th>Data Vencimento</th>
                    <th>Criado Em</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($erro_pessoal !== null): ?>
                    <tr>
                        <td colspan="7"><?= htmlspecialchars($erro_pessoal) ?></td>
                    </tr>
                <?php elseif (empty($pessoal_lista)): ?>
                    <tr>
                        <td colspan="7">Sem registos para os filtros aplicados.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pessoal_lista as $item): ?>
                        <?php
                        $emissao = trim((string)($item['data_emissao'] ?? ''));
                        $vencimento = trim((string)($item['data_vencimento'] ?? ''));
                        $criadoEm = trim((string)($item['documento_created_at'] ?? ''));
                        $perfilLinha = (string)($item['perfil_classificado'] ?? 'outros');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($item['numero'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($item['nome'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars(labelPerfilPessoal($perfilLinha)) ?></td>
                            <td><?= htmlspecialchars((string)($item['tipo_documento'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($emissao !== '' ? $emissao : '-') ?></td>
                            <td><?= htmlspecialchars($vencimento !== '' ? $vencimento : '-') ?></td>
                            <td><?= htmlspecialchars($criadoEm !== '' ? $criadoEm : '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
