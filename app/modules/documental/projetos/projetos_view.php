<?php
$aplicar_filtro = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';
$pesquisa = trim((string)($_GET['q'] ?? ''));
$status_filtro = trim((string)($_GET['status'] ?? 'todos'));

$projetos_base = [
    ['projeto' => 'Ponte Maputo', 'cliente' => 'Cliente Externo', 'localizacao' => 'Maputo', 'status' => 'Ativo'],
    ['projeto' => 'Base Nampula', 'cliente' => 'VILCON', 'localizacao' => 'Nampula', 'status' => 'Pausado'],
    ['projeto' => 'Patio Tete', 'cliente' => 'Cliente Externo', 'localizacao' => 'Tete', 'status' => 'Concluido'],
];

$projetos = [];
if ($aplicar_filtro) {
    foreach ($projetos_base as $p) {
        $texto = strtolower($p['projeto'] . ' ' . $p['cliente'] . ' ' . $p['localizacao']);
        $okPesquisa = $pesquisa === '' || strpos($texto, strtolower($pesquisa)) !== false;
        $okStatus = $status_filtro === 'todos' || strtolower($p['status']) === strtolower($status_filtro);
        if ($okPesquisa && $okStatus) {
            $projetos[] = $p;
        }
    }
}
?>
<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-diagram-project"></i> Gestao de Projetos</h3>
            <p>Use os atalhos abaixo para abrir lista ou formulario.</p>
        </div>
    </div>

    <div class="module-entry">
        <button type="button" class="module-entry-btn lista" data-open-module-modal="projetos-modal-lista"><i class="fas fa-list"></i> Lista</button>
        <button type="button" class="module-entry-btn form" data-open-module-modal="projetos-modal-form"><i class="fas fa-plus"></i> Adicionar</button>
    </div>

    <?php if (!$aplicar_filtro): ?>
        <div class="filter-container" style="margin-top:8px;">
            <span style="font-size:12px; color:#6b7280;">A lista de projetos so aparece apos aplicar os filtros.</span>
        </div>
    <?php endif; ?>

    <div class="module-modal <?= $aplicar_filtro ? 'open' : '' ?>" id="projetos-modal-lista">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Projetos - Lista e Filtros</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="filter-container" method="get" action="">
                    <input type="hidden" name="view" value="projetos">
                    <input type="hidden" name="aplicar" value="1">
                    <div class="form-group" style="flex:1;">
                        <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Projeto, cliente, localizacao...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-filter"></i> Filtrar status</label>
                        <select name="status">
                            <option value="todos" <?= $status_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="Ativo" <?= $status_filtro === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="Pausado" <?= $status_filtro === 'Pausado' ? 'selected' : '' ?>>Pausado</option>
                            <option value="Concluido" <?= $status_filtro === 'Concluido' ? 'selected' : '' ?>>Concluido</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
                    <a href="?view=projetos" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
                </form>

                <div class="module-tools">
                    <button type="button" class="btn-export" data-export-format="excel"><i class="fas fa-file-excel"></i> Baixar Excel</button>
                    <button type="button" class="btn-export" data-export-format="pdf"><i class="fas fa-file-pdf"></i> Baixar PDF</button>
                </div>

                <div class="panel-view <?= $aplicar_filtro ? '' : 'hidden' ?>">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th>Projeto</th>
                                <th>Cliente</th>
                                <th>Localizacao</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projetos)): ?>
                                <tr><td colspan="4">Sem registos para os filtros aplicados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($projetos as $p): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['projeto']) ?></strong></td>
                                        <td><?= htmlspecialchars($p['cliente']) ?></td>
                                        <td><?= htmlspecialchars($p['localizacao']) ?></td>
                                        <td><span class="pill info"><?= htmlspecialchars($p['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="module-modal" id="projetos-modal-form">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Projetos - Adicionar</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="form-grid">
                    <div class="section-title">Novo Projeto ou Frente de Trabalho</div>
                    <div class="form-group"><label>Nome do Projeto / Obra</label><input type="text" placeholder="Ex: Construcao Ponte Maputo"></div>
                    <div class="form-group"><label>Cliente / Destino</label><select><option value="interno">VILCON (TRABALHO INTERNO)</option><option value="externo">CLIENTE EXTERNO</option></select></div>
                    <div class="form-group"><label>Nome do Cliente</label><input type="text" placeholder="Ex: Consultec / Vale"></div>
                    <div class="form-group"><label>Localizacao / Provincia</label><input type="text" placeholder="Ex: Tete, Moatize"></div>
                    <div class="form-group"><label>Data de Inicio</label><input type="date"></div>
                    <div class="form-group"><label>Status do Projeto</label><select><option>Ativo</option><option>Pausado</option><option>Concluido</option></select></div>
                    <div style="grid-column: span 3;"><button class="btn-save">Registrar Projeto</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
