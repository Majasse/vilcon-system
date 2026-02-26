<div class="header-section">
    <div class="tab-menu">
        <a class="tab-btn active"><i class="fas fa-tools"></i> Oficina</a>
    </div>
</div>

<div class="sub-tab-container">
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=ordens_servico&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'ordens_servico' ? 'active' : '' ?>">Ordens Serviço</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=pedidos_reparacao&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'pedidos_reparacao' ? 'active' : '' ?>">Pedidos Reparação</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=requisicoes&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'requisicoes' ? 'active' : '' ?>">Requisicoes</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=manutencao&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'manutencao' ? 'active' : '' ?>">Manutenção</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=checklist&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'checklist' ? 'active' : '' ?>">Checklist</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=presencas&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'presencas' ? 'active' : '' ?>">Controle de Presencas</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=avarias&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'avarias' ? 'active' : '' ?>">Avarias</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=relatorios&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'relatorios' ? 'active' : '' ?>">Relatórios</a>
</div>
