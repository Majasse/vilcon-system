<?php
$aplicar_filtro = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';
$pesquisa = trim((string)($_GET['q'] ?? ''));
$tipo_filtro = trim((string)($_GET['tipo'] ?? 'todos'));

$transacoes_base = [
    ['ativo' => 'ABC-123-MC', 'tipo' => 'Compra', 'data' => '2026-01-30', 'valor' => '3.500.000'],
    ['ativo' => 'TTX-902-MC', 'tipo' => 'Venda', 'data' => '2026-02-02', 'valor' => '2.100.000'],
];

$transacoes = [];
if ($aplicar_filtro) {
    foreach ($transacoes_base as $t) {
        $texto = strtolower($t['ativo'] . ' ' . $t['valor'] . ' ' . $t['data']);
        $okPesquisa = $pesquisa === '' || strpos($texto, strtolower($pesquisa)) !== false;
        $okTipo = $tipo_filtro === 'todos' || strtolower($t['tipo']) === strtolower($tipo_filtro);
        if ($okPesquisa && $okTipo) {
            $transacoes[] = $t;
        }
    }
}
?>
<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-handshake"></i> Compra / Venda</h3>
            <p>Use os atalhos abaixo para abrir lista ou formulario.</p>
        </div>
    </div>

    <div class="module-entry">
        <button type="button" class="module-entry-btn lista" data-open-module-modal="compra-modal-lista"><i class="fas fa-list"></i> Lista</button>
        <button type="button" class="module-entry-btn form" data-open-module-modal="compra-modal-form"><i class="fas fa-plus"></i> Adicionar</button>
    </div>

    <?php if (!$aplicar_filtro): ?>
        <div class="filter-container" style="margin-top:8px;">
            <span style="font-size:12px; color:#6b7280;">A lista de transacoes so aparece apos aplicar os filtros.</span>
        </div>
    <?php endif; ?>

    <div class="module-modal <?= $aplicar_filtro ? 'open' : '' ?>" id="compra-modal-lista">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Compra/Venda - Lista e Filtros</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="filter-container" method="get" action="">
                    <input type="hidden" name="view" value="compra_venda">
                    <input type="hidden" name="aplicar" value="1">
                    <div class="form-group" style="flex:1;">
                        <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Ativo, valor, data...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-filter"></i> Filtrar tipo</label>
                        <select name="tipo">
                            <option value="todos" <?= $tipo_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="Compra" <?= $tipo_filtro === 'Compra' ? 'selected' : '' ?>>Compra</option>
                            <option value="Venda" <?= $tipo_filtro === 'Venda' ? 'selected' : '' ?>>Venda</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
                    <a href="?view=compra_venda" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
                </form>

                <div class="module-tools">
                    <button type="button" class="btn-export" data-export-format="excel"><i class="fas fa-file-excel"></i> Baixar Excel</button>
                    <button type="button" class="btn-export" data-export-format="pdf"><i class="fas fa-file-pdf"></i> Baixar PDF</button>
                </div>

                <div class="panel-view <?= $aplicar_filtro ? '' : 'hidden' ?>">
                    <table class="list-table">
                        <thead>
                            <tr><th>Ativo</th><th>Tipo</th><th>Data</th><th>Valor (MZN)</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transacoes)): ?>
                                <tr><td colspan="4">Sem registos para os filtros aplicados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($transacoes as $t): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($t['ativo']) ?></strong></td>
                                        <td><span class="pill info"><?= htmlspecialchars($t['tipo']) ?></span></td>
                                        <td><?= htmlspecialchars($t['data']) ?></td>
                                        <td><?= htmlspecialchars($t['valor']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="module-modal" id="compra-modal-form">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Compra/Venda - Adicionar</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="form-grid" enctype="multipart/form-data">
                    <div class="section-title">Dados da Compra</div>
                    <div class="form-group"><label>Data da Compra</label><input type="date"></div>
                    <div class="form-group"><label>Valor (MZN)</label><input type="text"></div>
                    <div class="form-group"><label>Fotos da Compra</label><label class="btn-upload"><i class="fas fa-camera"></i> Anexar Fotos <input type="file" multiple style="display:none"></label></div>
                    <div class="section-title">Dados de Venda</div>
                    <div class="form-group"><label>Data da Venda</label><input type="date"></div>
                    <div class="form-group"><label>Valor de Venda</label><input type="text"></div>
                    <div class="form-group"><label>Fotos da Venda</label><label class="btn-upload"><i class="fas fa-camera"></i> Anexar Fotos Venda <input type="file" multiple style="display:none"></label></div>
                    <div style="grid-column: span 3;"><button class="btn-save">Registrar Transacao</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
