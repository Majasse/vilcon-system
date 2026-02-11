<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-screwdriver-wrench"></i> Elevacao</h3>
            <p>Visualize equipamentos de elevacao e adicione novos registos.</p>
        </div>
        <div class="tool-actions">
            <button type="button" class="btn-mode active" data-target="elevacao-lista"><i class="fas fa-list"></i> Ver lista</button>
            <button type="button" class="btn-mode" data-target="elevacao-form"><i class="fas fa-plus"></i> Adicionar</button>
        </div>
    </div>

    <div class="filter-container">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" placeholder="Equipamento, serie, localizacao...">
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar por estado</label>
            <select>
                <option>Todos</option>
                <option>Operacional</option>
                <option>Em manutencao</option>
            </select>
        </div>
        <button type="button" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
    </div>

    <div id="elevacao-lista" class="panel-view">
        <table class="list-table">
            <thead>
                <tr>
                    <th>Equipamento</th>
                    <th>Serie</th>
                    <th>Proxima inspecao</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Guindaste 20T</strong></td>
                    <td>EL-2026-01</td>
                    <td>2026-05-01</td>
                    <td><span class="pill ok">Operacional</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="elevacao-form" class="panel-view hidden">
        <form class="form-grid">
            <div class="section-title">Registro de Equipamento</div>
            <div class="form-group"><label>Descricao / Nome</label><input type="text" placeholder="Ex: Guindaste 20T"></div>
            <div class="form-group"><label>No de Serie</label><input type="text"></div>
            <div class="form-group"><label>Proxima Inspecao</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
            <div style="grid-column: span 3;"><button class="btn-save">Gravar Equipamento</button></div>
        </form>
    </div>
</div>
