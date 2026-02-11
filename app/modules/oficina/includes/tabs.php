<div class="header-section">
    <div class="tab-menu">
        <a class="tab-btn active"><i class="fas fa-tools"></i> Oficina</a>
        <a class="tab-btn" href="/vilcon-systemon/app/modules/armazem/index.php"><i class="fas fa-warehouse"></i> Armazém</a>
    </div>
</div>

<div class="sub-tab-container">
    <a href="?view=ordens_servico" class="sub-tab-btn <?= $view == 'ordens_servico' ? 'active' : '' ?>">Ordens Serviço</a>
    <a href="?view=pedidos_reparacao" class="sub-tab-btn <?= $view == 'pedidos_reparacao' ? 'active' : '' ?>">Pedidos Reparação</a>
    <a href="?view=manutencao" class="sub-tab-btn <?= $view == 'manutencao' ? 'active' : '' ?>">Manutenção</a>
    <a href="?view=checklist" class="sub-tab-btn <?= $view == 'checklist' ? 'active' : '' ?>">Checklist</a>
    <a href="?view=avarias" class="sub-tab-btn <?= $view == 'avarias' ? 'active' : '' ?>">Avarias</a>
    <a href="?view=relatorios" class="sub-tab-btn <?= $view == 'relatorios' ? 'active' : '' ?>">Relatórios</a>
</div>
