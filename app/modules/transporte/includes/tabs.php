<div class="header-section">
    <div class="tab-menu">
        <a href="?tab=transporte" class="tab-btn active"><i class="fas fa-route"></i> Transporte</a>
        <a href="?tab=gestao_frota" class="tab-btn"><i class="fas fa-shuttle-van"></i> Frota</a>
    </div>
</div>

<div class="sub-tab-container">
    <a href="?tab=transporte&view=entrada&mode=list" class="sub-tab-btn <?= $view == 'entrada' ? 'active' : '' ?>">Ordem de Serviço</a>
    <a href="?tab=transporte&view=pedido_reparacao&mode=list" class="sub-tab-btn <?= $view == 'pedido_reparacao' ? 'active' : '' ?>">Pedido de Reparação</a>
    <a href="?tab=transporte&view=checklist&mode=list" class="sub-tab-btn <?= $view == 'checklist' ? 'active' : '' ?>">Checklist</a>
    <a href="?tab=transporte&view=plano_manutencao&mode=list" class="sub-tab-btn <?= $view == 'plano_manutencao' ? 'active' : '' ?>">Plano Manutenção</a>
    <a href="?tab=transporte&view=avarias&mode=list" class="sub-tab-btn <?= $view == 'avarias' ? 'active' : '' ?>">Registo Avarias</a>
    <a href="?tab=transporte&view=relatorio_atividades&mode=list" class="sub-tab-btn <?= $view == 'relatorio_atividades' ? 'active' : '' ?>">Relatório Atividades</a>
</div>
