<?php
$pessoal_lista = [];
$erro_pessoal = null;
$tipos_documento = [];
$pesquisa = trim((string)($_GET['q'] ?? ''));
$tipo_documento_filtro = trim((string)($_GET['tipo_documento'] ?? ''));

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
        $where[] = "(p.nome LIKE :pesquisa OR CAST(p.numero AS CHAR) LIKE :pesquisa OR pd.tipo_documento LIKE :pesquisa)";
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

    $sql .= "
        ORDER BY p.id ASC, pd.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pessoal_lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $erro_pessoal = 'Nao foi possivel carregar os funcionarios.';
}
?>
<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-users"></i> Pessoal</h3>
            <p>Veja a lista de motoristas e operadores ou adicione novo cadastro.</p>
        </div>
        <div class="tool-actions">
            <button type="button" class="btn-mode active" data-target="pessoal-lista"><i class="fas fa-list"></i> Ver lista</button>
            <button type="button" class="btn-mode" data-target="pessoal-form"><i class="fas fa-user-plus"></i> Adicionar</button>
        </div>
    </div>

    <form class="filter-container" method="get" action="">
        <input type="hidden" name="view" value="pessoal">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Nome, numero, tipo documento...">
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
                    <th>Tipo Documento</th>
                    <th>Data Emissao</th>
                    <th>Data Vencimento</th>
                    <th>Criado Em</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($erro_pessoal !== null): ?>
                    <tr>
                        <td colspan="6"><?= htmlspecialchars($erro_pessoal) ?></td>
                    </tr>
                <?php elseif (empty($pessoal_lista)): ?>
                    <tr>
                        <td colspan="6">Sem registos nas tabelas pessoal/pessoal_documentos.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pessoal_lista as $item): ?>
                        <?php
                        $emissao = trim((string)($item['data_emissao'] ?? ''));
                        $vencimento = trim((string)($item['data_vencimento'] ?? ''));
                        $criadoEm = trim((string)($item['documento_created_at'] ?? ''));
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($item['numero'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($item['nome'] ?? '-')) ?></td>
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

    <div id="pessoal-form" class="panel-view hidden">
        <form class="form-grid">
            <div class="section-title">Cadastro e Destino Operacional</div>
            <div class="form-group"><label>Nome Completo</label><input type="text"></div>

            <div class="form-group">
                <label>Projeto / Destino de Trabalho</label>
                <select>
                    <option>TRABALHO INTERNO (SEDE/LOGISTICA)</option>
                    <option>PROJETO X (EXEMPLO)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Atividade Designada</label>
                <input type="text" placeholder="Ex: Operador de Escavadora">
            </div>

            <div class="form-group">
                <label>Categoria</label>
                <select><option>Motorista</option><option>Operador de Maquina</option></select>
            </div>
            <div class="form-group">
                <label>Tipo de Carta</label>
                <select><option>Profissional</option><option>Pesado</option><option>Ligeiro</option><option>Outra</option></select>
            </div>
            <div class="form-group"><label>Validade BI</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Validade Carta</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group">
                <label>Exame Medico / Medical (Validade)</label>
                <div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div>
            </div>
            <div style="grid-column: span 3;"><button class="btn-save">Gravar Pessoal</button></div>
        </form>
    </div>
</div>

