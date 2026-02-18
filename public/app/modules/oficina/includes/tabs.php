<div class="header-section">
    <div class="tab-menu">
        <a class="tab-btn active"><i class="fas fa-tools"></i> Oficina</a>
        <a class="tab-btn" href="/vilcon-systemon/app/modules/armazem/index.php"><i class="fas fa-warehouse"></i> Armazém</a>
    </div>
</div>

<div class="sub-tab-container">
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=ordens_servico&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'ordens_servico' ? 'active' : '' ?>">Ordens Serviço</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=pedidos_reparacao&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'pedidos_reparacao' ? 'active' : '' ?>">Pedidos Reparação</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=requisicoes&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'requisicoes' ? 'active' : '' ?>">Requisicoes</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=manutencao&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'manutencao' ? 'active' : '' ?>">Manutenção</a>
    <a href="?view=checklist" class="sub-tab-btn <?= $view == 'checklist' ? 'active' : '' ?>">Checklist</a>
    <a href="?view=assiduidade" class="sub-tab-btn <?= $view == 'assiduidade' ? 'active' : '' ?>">Assiduidade</a>
    <a href="?tab=<?= urlencode((string)($tab ?? 'oficina')) ?>&view=avarias&mode=list&aplicar=1" class="sub-tab-btn <?= $view == 'avarias' ? 'active' : '' ?>">Avarias</a>
    <a href="?view=relatorios" class="sub-tab-btn <?= $view == 'relatorios' ? 'active' : '' ?>">Relatórios</a>
</div>
