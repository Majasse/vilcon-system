<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <div>
        <h3>Histórico Geral de Consultas</h3>
        <p style="font-size:12px; color:#6b7280; margin-top:4px;">Registos consolidados de ativos, projetos, pessoal e segurança.</p>
    </div>
    <div class="pill info">Atualizado hoje</div>
</div>

<div class="filter-container">
    <div class="form-group" style="flex:1;">
        <label>Pesquisa Global</label>
        <input type="text" placeholder="Matrícula, Nome, Chassi ou Série...">
    </div>
    <div class="form-group" style="width:250px;">
        <label>Ver por Categoria</label>
        <select>
            <option value="todos">-- Tudo --</option>
            <option value="projetos">Projetos</option>
            <option value="ativos">Ativos</option>
            <option value="pessoal">Motoristas & Operadores</option>
            <option value="elevacao">Equipamento de Elevação</option>
            <option value="financeiro">Compra & Venda</option>
            <option value="alertas">Segurança & Alertas</option>
        </select>
    </div>
    <button class="tab-btn active" style="height:42px;"><i class="fas fa-search"></i> Filtrar</button>
</div>

<table class="table-historico">
    <thead>
        <tr>
            <th>Identificação / Nome</th>
            <th>Categoria / Projeto</th>
            <th>Info Adicional</th>
            <th>Status / Data</th>
            <th style="text-align:center;">Ações</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>ABC-100-MC</strong></td>
            <td><span class="pill ok">Ativo</span> / Obra Pemba</td>
            <td>Chassi: 9BWZZZ...</td>
            <td>12/02/2026</td>
            <td class="action-icons">
                <a href="gerar_pdf.php?id=1" class="icon-download" title="Baixar PDF"><i class="fas fa-file-pdf"></i></a>
                <?php if($nivel_usuario == 'admin'): ?>
                    <a href="editar.php?id=1" class="icon-edit" title="Editar"><i class="fas fa-edit"></i></a>
                    <a href="deletar.php?id=1" class="icon-delete" title="Eliminar" onclick="return confirm('Tem certeza que deseja eliminar?')"><i class="fas fa-trash"></i></a>
                <?php else: ?>
                    <i class="fas fa-edit restricted" title="Acesso Restrito"></i>
                    <i class="fas fa-trash restricted" title="Acesso Restrito"></i>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><strong>RM-022-PE</strong></td>
            <td><span class="pill warn">Alerta</span> / Segurança</td>
            <td>Documento: seguro vencido</td>
            <td>08/02/2026</td>
            <td class="action-icons">
                <a href="gerar_pdf.php?id=2" class="icon-download" title="Baixar PDF"><i class="fas fa-file-pdf"></i></a>
                <?php if($nivel_usuario == 'admin'): ?>
                    <a href="editar.php?id=2" class="icon-edit" title="Editar"><i class="fas fa-edit"></i></a>
                    <a href="deletar.php?id=2" class="icon-delete" title="Eliminar" onclick="return confirm('Tem certeza que deseja eliminar?')"><i class="fas fa-trash"></i></a>
                <?php else: ?>
                    <i class="fas fa-edit restricted" title="Acesso Restrito"></i>
                    <i class="fas fa-trash restricted" title="Acesso Restrito"></i>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><strong>EMP-117</strong></td>
            <td><span class="pill info">Pessoal</span> / Motoristas</td>
            <td>Licença renovada</td>
            <td>02/02/2026</td>
            <td class="action-icons">
                <a href="gerar_pdf.php?id=3" class="icon-download" title="Baixar PDF"><i class="fas fa-file-pdf"></i></a>
                <?php if($nivel_usuario == 'admin'): ?>
                    <a href="editar.php?id=3" class="icon-edit" title="Editar"><i class="fas fa-edit"></i></a>
                    <a href="deletar.php?id=3" class="icon-delete" title="Eliminar" onclick="return confirm('Tem certeza que deseja eliminar?')"><i class="fas fa-trash"></i></a>
                <?php else: ?>
                    <i class="fas fa-edit restricted" title="Acesso Restrito"></i>
                    <i class="fas fa-trash restricted" title="Acesso Restrito"></i>
                <?php endif; ?>
            </td>
        </tr>
    </tbody>
</table>
