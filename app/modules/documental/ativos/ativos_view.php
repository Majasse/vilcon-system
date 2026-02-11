<?php
$ativos = [];
$erro_ativos = null;

try {
    $sql = "
        SELECT
            *
        FROM activos
        ORDER BY id ASC
    ";
    $stmt = $pdo->query($sql);
    $ativos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $erro_ativos = 'Nao foi possivel carregar os ativos.';
}
?>
<div data-mode-scope>
    <style>
        .ativos-table-wrap {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
        }
        .ativos-table-wrap .list-table {
            min-width: 1900px;
            margin: 0;
        }
        .ativos-table-wrap .list-table th,
        .ativos-table-wrap .list-table td {
            white-space: nowrap;
            padding: 8px 9px;
            font-size: 12px;
        }
        .ativos-table-wrap .list-table th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            z-index: 1;
        }
    </style>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-truck"></i> Gestao de Ativos</h3>
            <p>Visualize a lista de ativos ou adicione um novo registro.</p>
        </div>
        <div class="tool-actions">
            <button type="button" class="btn-mode active" data-target="ativos-lista"><i class="fas fa-list"></i> Ver lista</button>
            <button type="button" class="btn-mode" data-target="ativos-form"><i class="fas fa-plus"></i> Adicionar</button>
        </div>
    </div>

    <div class="filter-container">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" placeholder="Matricula, chassi, marca...">
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar por tipo</label>
            <select>
                <option value="todos">Todos</option>
                <option>Bulldozer</option>
                <option>Escavadora</option>
                <option>Retroescavadora</option>
            </select>
        </div>
        <button type="button" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
    </div>

    <div id="ativos-lista" class="panel-view">
        <div class="ativos-table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>id</th>
                        <th>equipamento</th>
                        <th>matricula</th>
                        <th>marca</th>
                        <th>propriedade</th>
                        <th>modelo</th>
                        <th>motor</th>
                        <th>quadro</th>
                        <th>livrete</th>
                        <th>seguros</th>
                        <th>inspeccao</th>
                        <th>manifesto</th>
                        <th>radio</th>
                        <th>speal_kit</th>
                        <th>extintor</th>
                        <th>reflectores</th>
                        <th>macaco</th>
                        <th>chave_roda</th>
                        <th>estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($erro_ativos !== null): ?>
                        <tr>
                            <td colspan="19"><?= htmlspecialchars($erro_ativos) ?></td>
                        </tr>
                    <?php elseif (empty($ativos)): ?>
                        <tr>
                            <td colspan="19">Sem registos na tabela activos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ativos as $ativo): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($ativo['id'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['equipamento'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['matricula'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['marca'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['propriedade'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['modelo'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['motor'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['quadro'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['livrete'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['seguros'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['inspeccao'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['manifesto'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['radio'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['speal_kit'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['extintor'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['reflectores'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['macaco'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['chave_roda'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['estado'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="ativos-form" class="panel-view hidden">
        <form class="form-grid" enctype="multipart/form-data">
            <div class="section-title">Informacao Base e Alocacao</div>
            <div class="form-group"><label>Matricula / Codigo Interno</label><input type="text" placeholder="Ex: ABC-123-MC"></div>

            <div class="form-group">
                <label>Projeto / Localizacao Atual</label>
                <select>
                    <option>TRABALHO INTERNO (SEDE/OFICINA)</option>
                    <option>PROJETO X (EXEMPLO)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Atividade Atual</label>
                <input type="text" placeholder="Ex: Escavacao, Carga, Em manutencao">
            </div>

            <div class="form-group"><label>No de Chassi</label><input type="text" placeholder="Ex: 9BWZZZ... "></div>
            <div class="form-group">
                <label>Tipo de Ativo</label>
                <select onchange="checkNovo(this, 'novo_tipo')">
                    <option>Bulldozer</option><option>Escavadora</option><option>Retroescavadora</option>
                    <option value="novo">-- Adicionar Novo --</option>
                </select>
                <input type="text" id="novo_tipo" class="input-novo" placeholder="Escreva o tipo aqui">
            </div>
            <div class="form-group">
                <label>Marca</label>
                <select onchange="checkNovo(this, 'nova_marca')">
                    <option>Caterpillar</option><option>Volvo</option><option>Komatsu</option>
                    <option value="novo">-- Adicionar Nova --</option>
                </select>
                <input type="text" id="nova_marca" class="input-novo" placeholder="Escreva a marca aqui">
            </div>

            <div class="section-title">Documentos e Validades Individuais</div>
            <div class="form-group"><label>Vencimento Seguro</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Inspecao Periodica</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Taxas de Radio</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Manifestos</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div style="grid-column: span 3;"><button class="btn-save">Salvar Registro</button></div>
        </form>
    </div>
</div>
