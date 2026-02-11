<div data-mode-scope>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-handshake"></i> Compra / Venda</h3>
            <p>Consulte transacoes registradas e adicione novas movimentacoes.</p>
        </div>
        <div class="tool-actions">
            <button type="button" class="btn-mode active" data-target="compra-lista"><i class="fas fa-list"></i> Ver lista</button>
            <button type="button" class="btn-mode" data-target="compra-form"><i class="fas fa-plus"></i> Adicionar</button>
        </div>
    </div>

    <div class="filter-container">
        <div class="form-group" style="flex:1;">
            <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
            <input type="text" placeholder="Ativo, valor, data...">
        </div>
        <div class="form-group">
            <label><i class="fas fa-filter"></i> Filtrar tipo</label>
            <select>
                <option>Todos</option>
                <option>Compra</option>
                <option>Venda</option>
            </select>
        </div>
        <button type="button" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
    </div>

    <div id="compra-lista" class="panel-view">
        <table class="list-table">
            <thead>
                <tr>
                    <th>Ativo</th>
                    <th>Tipo</th>
                    <th>Data</th>
                    <th>Valor (MZN)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>ABC-123-MC</strong></td>
                    <td><span class="pill info">Compra</span></td>
                    <td>2026-01-30</td>
                    <td>3.500.000</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="compra-form" class="panel-view hidden">
        <form class="form-grid" enctype="multipart/form-data">
            <div class="section-title">Dados da Compra</div>
            <div class="form-group"><label>Data da Compra</label><input type="date"></div>
            <div class="form-group"><label>Valor (MZN)</label><input type="text"></div>
            <div class="form-group"><label>Fotos da Compra</label><label class="btn-upload"><i class="fas fa-camera"></i> Anexar Fotos <input type="file" multiple style="display:none"></label></div>

            <div class="section-title">Dados de Venda</div>
            <div class="form-group"><label>Data da Venda</label><input type="date"></div>
            <div class="form-group"><label>Valor de Venda</label><input type="text"></div>
            <div class="form-group">
                <label>Fotos da Venda</label>
                <label class="btn-upload"><i class="fas fa-camera"></i> Anexar Fotos Venda <input type="file" multiple style="display:none"></label>
            </div>
            <div style="grid-column: span 3;"><button class="btn-save">Registrar Transacao</button></div>
        </form>
    </div>
</div>
