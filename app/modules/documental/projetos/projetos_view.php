<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-diagram-project"></i> Gestao de Projetos</h3>
            <p>Consulte listas de projetos e adicione novos centros de custo.</p>
        </div>
        <div class="tool-actions">
            <button type="button" class="btn-mode active" data-target="projetos-lista"><i class="fas fa-list"></i> Ver lista</button>
            <button type="button" class="btn-mode" data-target="projetos-form"><i class="fas fa-plus"></i> Adicionar</button>
        </div>
    </div>

    <div class="filter-container">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" placeholder="Projeto, cliente, localizacao...">
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar status</label>
            <select>
                <option>Todos</option>
                <option>Ativo</option>
                <option>Pausado</option>
                <option>Concluido</option>
            </select>
        </div>
        <button type="button" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
    </div>

    <div id="projetos-lista" class="panel-view">
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
                <tr>
                    <td><strong>Ponte Maputo</strong></td>
                    <td>Cliente Externo</td>
                    <td>Maputo</td>
                    <td><span class="pill info">Ativo</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="projetos-form" class="panel-view hidden">
        <form class="form-grid">
            <div class="section-title">Novo Projeto ou Frente de Trabalho</div>
            <div class="form-group">
                <label>Nome do Projeto / Obra</label>
                <input type="text" placeholder="Ex: Construcao Ponte Maputo">
            </div>
            <div class="form-group">
                <label>Cliente / Destino</label>
                <select>
                    <option value="interno">VILCON (TRABALHO INTERNO)</option>
                    <option value="externo">CLIENTE EXTERNO</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nome do Cliente</label>
                <input type="text" placeholder="Ex: Consultec / Vale">
            </div>
            <div class="form-group">
                <label>Localizacao / Provincia</label>
                <input type="text" placeholder="Ex: Tete, Moatize">
            </div>
            <div class="form-group">
                <label>Data de Inicio</label>
                <input type="date">
            </div>
            <div class="form-group">
                <label>Status do Projeto</label>
                <select>
                    <option>Ativo</option>
                    <option>Pausado</option>
                    <option>Concluido</option>
                </select>
            </div>
            <div style="grid-column: span 3;"><button class="btn-save">Registrar Projeto</button></div>
        </form>
    </div>
</div>
