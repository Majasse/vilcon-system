<?php
$aplicar_filtro = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';
$pesquisa = trim((string)($_GET['q'] ?? ''));
$estado_filtro = trim((string)($_GET['estado'] ?? 'todos'));

$equipamentos_base = [
    ['equipamento' => 'Guindaste 20T', 'serie' => 'EL-2026-01', 'inspecao' => '2026-05-01', 'estado' => 'Operacional'],
    ['equipamento' => 'Plataforma Aerea', 'serie' => 'EL-2026-07', 'inspecao' => '2026-04-10', 'estado' => 'Em manutencao'],
];

$equipamentos = [];
if ($aplicar_filtro) {
    foreach ($equipamentos_base as $e) {
        $texto = strtolower($e['equipamento'] . ' ' . $e['serie']);
        $okPesquisa = $pesquisa === '' || strpos($texto, strtolower($pesquisa)) !== false;
        $okEstado = $estado_filtro === 'todos' || strtolower($e['estado']) === strtolower($estado_filtro);
        if ($okPesquisa && $okEstado) {
            $equipamentos[] = $e;
        }
    }
}
?>
<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-screwdriver-wrench"></i> Elevacao</h3>
            <p>Use os atalhos abaixo para abrir lista ou formulario.</p>
        </div>
    </div>

    <div class="module-entry">
        <button type="button" class="module-entry-btn lista" data-open-module-modal="elevacao-modal-lista"><i class="fas fa-list"></i> Lista</button>
        <button type="button" class="module-entry-btn form" data-open-module-modal="elevacao-modal-form"><i class="fas fa-plus"></i> Adicionar</button>
    </div>

    <?php if (!$aplicar_filtro): ?>
        <div class="filter-container" style="margin-top:8px;">
            <span style="font-size:12px; color:#6b7280;">A lista de elevacao so aparece apos aplicar os filtros.</span>
        </div>
    <?php endif; ?>

    <div class="module-modal <?= $aplicar_filtro ? 'open' : '' ?>" id="elevacao-modal-lista">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Elevacao - Lista e Filtros</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="filter-container" method="get" action="">
                    <input type="hidden" name="view" value="elevacao">
                    <input type="hidden" name="aplicar" value="1">
                    <div class="form-group" style="flex:1;">
                        <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Equipamento, serie, localizacao...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-filter"></i> Filtrar por estado</label>
                        <select name="estado">
                            <option value="todos" <?= $estado_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="Operacional" <?= $estado_filtro === 'Operacional' ? 'selected' : '' ?>>Operacional</option>
                            <option value="Em manutencao" <?= $estado_filtro === 'Em manutencao' ? 'selected' : '' ?>>Em manutencao</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
                    <a href="?view=elevacao" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
                </form>

                <div class="module-tools">
                    <button type="button" class="btn-export" data-export-format="excel"><i class="fas fa-file-excel"></i> Baixar Excel</button>
                    <button type="button" class="btn-export" data-export-format="pdf"><i class="fas fa-file-pdf"></i> Baixar PDF</button>
                </div>

                <div class="panel-view <?= $aplicar_filtro ? '' : 'hidden' ?>">
                    <table class="list-table">
                        <thead>
                            <tr><th>Equipamento</th><th>Serie</th><th>Proxima inspecao</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($equipamentos)): ?>
                                <tr><td colspan="4">Sem registos para os filtros aplicados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($equipamentos as $e): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($e['equipamento']) ?></strong></td>
                                        <td><?= htmlspecialchars($e['serie']) ?></td>
                                        <td><?= htmlspecialchars($e['inspecao']) ?></td>
                                        <td><span class="pill ok"><?= htmlspecialchars($e['estado']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="module-modal" id="elevacao-modal-form">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Elevacao - Adicionar</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="form-grid">
                    <div class="section-title">Registro de Equipamento</div>
                    <div class="form-group"><label>Descricao / Nome</label><input type="text" placeholder="Ex: Guindaste 20T"></div>
                    <div class="form-group"><label>No de Serie</label><input type="text"></div>
                    <div class="form-group"><label>Proxima Inspecao</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
                    <div style="grid-column: span 3;"><button class="btn-save">Gravar Equipamento</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
