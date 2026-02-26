<?php
$periodoTipoUi = (string) ($filtros_relatorio_transporte['periodo_tipo'] ?? 'diario');
$periodoRefUi = (string) ($filtros_relatorio_transporte['periodo_ref'] ?? date('Y-m-d'));
$projectUi = (string) ($filtros_relatorio_transporte['project'] ?? '');
$viaturaUi = (string) ($filtros_relatorio_transporte['viatura'] ?? '');
$motoristaUi = (string) ($filtros_relatorio_transporte['motorista'] ?? '');
$centroUi = (string) ($filtros_relatorio_transporte['centro_custo'] ?? '');
$estadoUi = (string) ($filtros_relatorio_transporte['estado'] ?? '');
$qUi = (string) ($filtros_relatorio_transporte['q'] ?? '');
$rowsRelJson = json_encode($lista_relatorios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$chartCombJson = json_encode($grafico_combustivel_viatura, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$chartGastosJson = json_encode($grafico_gastos_periodo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$chartCustosJson = json_encode($grafico_custos_distribuicao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$chartMotJson = json_encode($grafico_desempenho_motorista, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

$metricDefs = [
    ['k' => 'total_viagens', 'label' => 'Total de Viagens', 'icon' => 'fa-route', 'fmt' => 'int'],
    ['k' => 'total_combustivel', 'label' => 'Total Combustível (L)', 'icon' => 'fa-gas-pump', 'fmt' => 'float'],
    ['k' => 'total_gasto', 'label' => 'Total Gasto (MT)', 'icon' => 'fa-sack-dollar', 'fmt' => 'money'],
    ['k' => 'custo_medio_viagem', 'label' => 'Custo Médio por Viagem', 'icon' => 'fa-coins', 'fmt' => 'money'],
    ['k' => 'total_horas_operacao', 'label' => 'Horas de Operação', 'icon' => 'fa-clock', 'fmt' => 'float'],
    ['k' => 'total_km', 'label' => 'KM Percorridos', 'icon' => 'fa-road', 'fmt' => 'float'],
    ['k' => 'veiculos_ativos', 'label' => 'Veículos Ativos', 'icon' => 'fa-truck', 'fmt' => 'int'],
    ['k' => 'alertas_criticos', 'label' => 'Alertas Críticos', 'icon' => 'fa-triangle-exclamation', 'fmt' => 'int'],
];

$fmtMetric = static function(float $value, string $fmt): string {
    if($fmt === 'int') return number_format((int) round($value), 0, ',', '.');
    if($fmt === 'money') return number_format($value, 2, ',', '.') . ' MT';
    return number_format($value, 2, ',', '.');
};

$calcDelta = static function(float $curr, float $prev): array {
    if(abs($prev) < 0.00001) {
        if(abs($curr) < 0.00001) return ['pct' => 0.0, 'positive' => true];
        return ['pct' => 100.0, 'positive' => true];
    }
    $pct = (($curr - $prev) / abs($prev)) * 100.0;
    return ['pct' => $pct, 'positive' => $pct >= 0];
};
?>
<style>
.tr-report-modern{font-family:'Inter','Poppins','Segoe UI',sans-serif;color:#0f172a}
.tr-report-modern~*{display:none!important}
.tr-card{background:transparent;border:none;border-radius:0;box-shadow:none;padding:0}
.tr-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start}
.tr-breadcrumb{font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase}
.tr-title{margin:4px 0 0;font-size:25px;color:#1E3A8A;font-weight:800}
.tr-actions{display:flex;gap:8px;flex-wrap:wrap}
.tr-btn{height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:0 12px;background:#fff;font-weight:700;font-size:12px;cursor:pointer;color:#1f2937;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.tr-btn.primary{background:#1E3A8A;border-color:#1E3A8A;color:#fff}
.tr-btn.warn{background:#FACC15;border-color:#FACC15;color:#111827}
.tr-filters{margin-top:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px}
.tr-grid{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:8px}
.tr-field label{display:block;font-size:10px;font-weight:800;text-transform:uppercase;color:#64748b;margin-bottom:3px}
.tr-field input,.tr-field select{width:100%;height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 9px;font-size:12px;background:#fff}
.tr-radio{display:flex;gap:10px;align-items:center;padding-top:6px}
.tr-radio label{font-size:12px;font-weight:700;color:#334155;display:inline-flex;gap:4px;align-items:center}
.tr-kpis{margin-top:12px;display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px}
.tr-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px}
.tr-kpi-top{display:flex;justify-content:space-between;align-items:center;gap:8px}
.tr-kpi-icon{width:30px;height:30px;border-radius:9px;background:#e0e7ff;color:#1E3A8A;display:flex;align-items:center;justify-content:center}
.tr-kpi-v{font-size:20px;font-weight:800;margin-top:6px}
.tr-kpi-l{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-top:2px}
.tr-kpi-d{font-size:11px;font-weight:800;margin-top:4px}
.tr-kpi-d.pos{color:#16A34A}.tr-kpi-d.neg{color:#DC2626}
.tr-charts{margin-top:12px;display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:10px}
.tr-chart{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px}
.tr-chart h4{margin:0 0 8px;font-size:12px;color:#334155;text-transform:uppercase}
.tr-table-wrap{margin-top:12px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:auto}
.tr-table{width:100%;border-collapse:collapse;min-width:1400px}
.tr-table th,.tr-table td{padding:8px;border-bottom:1px solid #eef2f7;font-size:12px;text-align:left}
.tr-table th{font-size:11px;text-transform:uppercase;color:#64748b;cursor:pointer;position:sticky;top:0;background:#fff}
.tr-status{display:inline-flex;height:22px;align-items:center;border-radius:999px;padding:0 8px;font-size:11px;font-weight:700}
.tr-status.ok{background:#dcfce7;color:#166534}.tr-status.mid{background:#fef9c3;color:#854d0e}.tr-status.bad{background:#fee2e2;color:#991b1b}
.tr-pager{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:10px}
.tr-empty{padding:26px;text-align:center;color:#64748b}
.tr-loading{display:none;position:fixed;inset:0;background:rgba(255,255,255,.65);z-index:99999;align-items:center;justify-content:center}
.tr-spinner{width:42px;height:42px;border:4px solid #dbeafe;border-top:4px solid #1E3A8A;border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@media (max-width:1200px){.tr-grid{grid-template-columns:repeat(2,minmax(140px,1fr))}.tr-kpis{grid-template-columns:repeat(2,minmax(140px,1fr))}.tr-charts{grid-template-columns:1fr}}
</style>
<div class="tr-report-modern" id="trReportModern">
    <div class="tr-loading" id="trLoading"><div class="tr-spinner"></div></div>
    <div class="tr-card">
        <div class="tr-head">
            <div>
                <div class="tr-breadcrumb">Transporte / Relatórios / Estratégico</div>
                <h2 class="tr-title">Relatórios de Transporte</h2>
            </div>
            <div class="tr-actions">
                <button type="button" class="tr-btn primary" onclick="applyTrFilters()"><i class="fa-solid fa-magnifying-glass"></i> Aplicar Filtro</button>
                <a href="?tab=transporte&view=relatorio_atividades&mode=list" class="tr-btn"><i class="fa-solid fa-rotate"></i> Limpar</a>
                <button type="button" class="tr-btn" onclick="downloadTransportPDF()"><i class="fa-solid fa-file-pdf"></i> Baixar PDF</button>
                <button type="button" class="tr-btn warn" onclick="downloadTransportExcel()"><i class="fa-solid fa-file-excel"></i> Baixar Excel</button>
            </div>
        </div>

        <form class="tr-filters" id="trFilterForm" method="get">
            <input type="hidden" name="tab" value="transporte"><input type="hidden" name="view" value="relatorio_atividades"><input type="hidden" name="mode" value="list">
            <div class="tr-grid">
                <div class="tr-field">
                    <label>Período</label>
                    <div class="tr-radio">
                        <label><input type="radio" name="periodo_tipo" value="diario" <?= $periodoTipoUi === 'diario' ? 'checked' : '' ?>> Diário</label>
                        <label><input type="radio" name="periodo_tipo" value="semanal" <?= $periodoTipoUi === 'semanal' ? 'checked' : '' ?>> Semanal</label>
                        <label><input type="radio" name="periodo_tipo" value="mensal" <?= $periodoTipoUi === 'mensal' ? 'checked' : '' ?>> Mensal</label>
                    </div>
                </div>
                <div class="tr-field"><label>Data de Referência</label><input type="date" name="periodo_ref" value="<?= htmlspecialchars($periodoRefUi) ?>"></div>
                <div class="tr-field"><label>Projeto</label><select name="project"><option value="">Todos</option><?php foreach($projetos_relatorio as $pr): ?><option value="<?= htmlspecialchars((string) $pr) ?>" <?= $projectUi === (string) $pr ? 'selected' : '' ?>><?= htmlspecialchars((string) $pr) ?></option><?php endforeach; ?></select></div>
                <div class="tr-field"><label>Viatura/Máquina</label><select name="viatura"><option value="">Todas</option><?php foreach($viaturas_relatorio as $vr): ?><option value="<?= htmlspecialchars((string) $vr) ?>" <?= $viaturaUi === (string) $vr ? 'selected' : '' ?>><?= htmlspecialchars((string) $vr) ?></option><?php endforeach; ?></select></div>
                <div class="tr-field"><label>Motorista</label><select name="motorista"><option value="">Todos</option><?php foreach($motoristas_relatorio as $mr): ?><option value="<?= htmlspecialchars((string) $mr) ?>" <?= $motoristaUi === (string) $mr ? 'selected' : '' ?>><?= htmlspecialchars((string) $mr) ?></option><?php endforeach; ?></select></div>
                <div class="tr-field"><label>Centro de Custo</label><select name="centro_custo"><option value="">Todos</option><?php foreach($centros_custo_relatorio as $cc): ?><option value="<?= htmlspecialchars((string) $cc) ?>" <?= $centroUi === (string) $cc ? 'selected' : '' ?>><?= htmlspecialchars((string) $cc) ?></option><?php endforeach; ?></select></div>
                <div class="tr-field"><label>Estado</label><select name="estado"><option value="">Todos</option><option <?= $estadoUi==='Concluido'?'selected':'' ?>>Concluido</option><option <?= $estadoUi==='Pendente'?'selected':'' ?>>Pendente</option><option <?= $estadoUi==='Atrasado'?'selected':'' ?>>Atrasado</option></select></div>
                <div class="tr-field"><label>Pesquisar OS/Matrícula</label><input type="text" name="q" value="<?= htmlspecialchars($qUi) ?>" placeholder="Nº OS ou matrícula"></div>
            </div>
        </form>
        <div style="margin-top:8px;display:grid;grid-template-columns:2fr 1fr;gap:8px;">
            <div class="tr-field"><label>Busca instantânea</label><input type="text" id="trInstantSearch" placeholder="Pesquisar na tabela sem recarregar"></div>
            <div class="tr-field"><label>Eficiência</label><input type="text" readonly value="KM/L: <?= number_format((float) ($resumo_relatorio_transporte['eficiencia_km_l'] ?? 0), 2, ',', '.') ?> | Custo/KM: <?= number_format((float) ($resumo_relatorio_transporte['custo_km'] ?? 0), 2, ',', '.') ?>"></div>
        </div>

        <div class="tr-kpis">
            <?php foreach($metricDefs as $m): ?>
                <?php
                    $k = (string) $m['k'];
                    $curr = (float) ($resumo_relatorio_transporte[$k] ?? 0);
                    $prev = (float) ($resumo_relatorio_transporte_prev[$k] ?? 0);
                    $delta = $calcDelta($curr, $prev);
                ?>
                <article class="tr-kpi">
                    <div class="tr-kpi-top"><span class="tr-kpi-icon"><i class="fa-solid <?= htmlspecialchars((string) $m['icon']) ?>"></i></span></div>
                    <div class="tr-kpi-v"><?= htmlspecialchars($fmtMetric($curr, (string) $m['fmt'])) ?></div>
                    <div class="tr-kpi-l"><?= htmlspecialchars((string) $m['label']) ?></div>
                    <div class="tr-kpi-d <?= !empty($delta['positive']) ? 'pos' : 'neg' ?>"><?= (!empty($delta['positive']) ? '+' : '') . number_format((float) ($delta['pct'] ?? 0), 1, ',', '.') ?>% vs período anterior</div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="tr-charts">
            <div class="tr-chart"><h4>Consumo de combustível por viatura</h4><canvas id="trChartCombustivel"></canvas></div>
            <div class="tr-chart"><h4>Evolução de gastos no período</h4><canvas id="trChartGastos"></canvas></div>
            <div class="tr-chart"><h4>Distribuição de custos</h4><canvas id="trChartCustos"></canvas></div>
            <div class="tr-chart"><h4>Desempenho por motorista (KM/L)</h4><canvas id="trChartMotorista"></canvas></div>
        </div>
        <div class="tr-charts" style="margin-top:8px;">
            <div class="tr-chart">
                <h4>Ranking de Motoristas</h4>
                <?php foreach($ranking_motoristas as $rm): ?>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eef2f7;font-size:12px;">
                        <span><?= htmlspecialchars((string) ($rm['nome'] ?? '-')) ?></span>
                        <strong><?= (int) ($rm['viagens'] ?? 0) ?> viagens | <?= number_format((float) ($rm['km_l'] ?? 0), 2, ',', '.') ?> KM/L</strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="tr-chart">
                <h4>Viaturas Mais Econômicas</h4>
                <?php foreach($ranking_viaturas_economicas as $rv): ?>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eef2f7;font-size:12px;">
                        <span><?= htmlspecialchars((string) ($rv['viatura'] ?? '-')) ?></span>
                        <strong><?= number_format((float) ($rv['km_l'] ?? 0), 2, ',', '.') ?> KM/L | <?= number_format((float) ($rv['custo_km'] ?? 0), 2, ',', '.') ?> MT/KM</strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tr-table-wrap">
            <table class="tr-table" id="trReportTable">
                <thead>
                    <tr>
                        <th data-sort="codigo">Nº OS</th>
                        <th data-sort="data_relatorio">Data</th>
                        <th data-sort="viatura_id">Viatura/Máquina</th>
                        <th data-sort="condutor">Motorista</th>
                        <th data-sort="projeto">Projeto</th>
                        <th data-sort="km_inicial">KM Inicial</th>
                        <th data-sort="km_final">KM Final</th>
                        <th data-sort="distancia_km">KM Percorridos</th>
                        <th data-sort="combustivel_l">Combustível (L)</th>
                        <th data-sort="custo_total_mt">Custo Total (MT)</th>
                        <th data-sort="status">Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="trTableBody"></tbody>
            </table>
            <div id="trEmpty" class="tr-empty" style="display:none;">Sem dados para os filtros selecionados.</div>
            <div class="tr-pager">
                <div id="trPagerInfo"></div>
                <div>
                    <button class="tr-btn" type="button" onclick="changeTrPage(-1)">Anterior</button>
                    <button class="tr-btn" type="button" onclick="changeTrPage(1)">Próxima</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.TR_REPORT_ROWS = <?= $rowsRelJson ?>;
window.TR_CHART_COMB = <?= $chartCombJson ?>;
window.TR_CHART_GAST = <?= $chartGastosJson ?>;
window.TR_CHART_CUST = <?= $chartCustosJson ?>;
window.TR_CHART_MOT = <?= $chartMotJson ?>;
let trRows = Array.isArray(window.TR_REPORT_ROWS) ? window.TR_REPORT_ROWS.slice() : [];
let trFiltered = trRows.slice();
let trSort = {key:'data_relatorio',dir:'desc'};
let trPage = 1; const trPageSize = 12;

function trStatusClass(v){const s=String(v||'').toLowerCase(); if(s.includes('atras')) return 'bad'; if(s.includes('pend')) return 'mid'; return 'ok';}
function trPriorityLabel(v){const s=String(v||'').toLowerCase(); if(s.includes('atras')) return 'Alta'; if(s.includes('pend')) return 'Média'; return 'Normal';}
function trFormatNum(v){const n=Number(v||0); return n.toLocaleString('pt-PT',{minimumFractionDigits:2,maximumFractionDigits:2});}
function trFmtDate(v){if(!v) return '-'; const d=new Date(v); if(Number.isNaN(d.getTime())) return v; return d.toLocaleDateString('pt-PT');}
function trSortRows(){
    trFiltered.sort((a,b)=>{const ka=(a[trSort.key]??''); const kb=(b[trSort.key]??''); if(trSort.key==='data_relatorio'){return trSort.dir==='asc'?String(ka).localeCompare(String(kb)):String(kb).localeCompare(String(ka));}
        const na=Number(ka), nb=Number(kb); if(!Number.isNaN(na)&&!Number.isNaN(nb)){return trSort.dir==='asc'?(na-nb):(nb-na);} return trSort.dir==='asc'?String(ka).localeCompare(String(kb)):String(kb).localeCompare(String(ka));});
}
function renderTrTable(){
    trSortRows();
    const body=document.getElementById('trTableBody'); const empty=document.getElementById('trEmpty'); const info=document.getElementById('trPagerInfo');
    if(!body||!empty||!info) return;
    const total=trFiltered.length; const pages=Math.max(1,Math.ceil(total/trPageSize)); if(trPage>pages) trPage=pages;
    const start=(trPage-1)*trPageSize; const slice=trFiltered.slice(start,start+trPageSize);
    body.innerHTML='';
    if(!slice.length){ empty.style.display='block'; info.textContent='0 registos'; return; }
    empty.style.display='none';
    slice.forEach(r=>{
        const status = r.status || '-';
        const pr = trPriorityLabel(status);
        const row = `<tr>
            <td>${r.codigo||('OS-'+String(r.ordem_servico_id||r.id||'').padStart(4,'0'))}</td>
            <td>${trFmtDate(r.data_relatorio)}</td>
            <td>${r.viatura_id||'-'}${r.matricula && r.matricula!=='-' ? ' / '+r.matricula : ''}</td>
            <td>${r.condutor||'-'}</td>
            <td>${r.projeto||'-'}</td>
            <td>${r.km_inicial??'-'}</td>
            <td>${r.km_final??'-'}</td>
            <td>${trFormatNum(r.distancia_km||0)}</td>
            <td>${trFormatNum(r.combustivel_l||0)}</td>
            <td>${trFormatNum(r.custo_total_mt||0)}</td>
            <td><span class="tr-status ${trStatusClass(status)}">${status} (${pr})</span></td>
            <td><a class="tr-btn" href="?tab=transporte&view=relatorio_atividades&mode=form&id=${Number(r.ordem_servico_id||0)}">Visualizar Detalhe</a></td>
        </tr>`;
        body.insertAdjacentHTML('beforeend',row);
    });
    info.textContent=`A mostrar ${start+1}-${Math.min(start+slice.length,total)} de ${total} registos`;
}
function changeTrPage(dir){ trPage += dir; if(trPage<1) trPage=1; renderTrTable(); }
function applyTrFilters(){
    const loading=document.getElementById('trLoading'); if(loading) loading.style.display='flex';
    document.getElementById('trFilterForm').submit();
}
function setupSort(){document.querySelectorAll('#trReportTable th[data-sort]').forEach(th=>{th.addEventListener('click',()=>{const k=th.getAttribute('data-sort'); if(trSort.key===k){trSort.dir=trSort.dir==='asc'?'desc':'asc';}else{trSort.key=k;trSort.dir='asc';} renderTrTable();});});}
function setupInstantSearch(){
    const inp = document.getElementById('trInstantSearch');
    if(!inp) return;
    inp.addEventListener('input', function(){
        const q = String(inp.value || '').toLowerCase().trim();
        if(q === '') {
            trFiltered = trRows.slice();
            trPage = 1;
            renderTrTable();
            return;
        }
        trFiltered = trRows.filter(function(r){
            const txt = [
                r.codigo,r.data_relatorio,r.viatura_id,r.matricula,r.condutor,r.projeto,r.km_inicial,r.km_final,r.distancia_km,r.combustivel_l,r.custo_total_mt,r.status
            ].join(' ').toLowerCase();
            return txt.indexOf(q) !== -1;
        });
        trPage = 1;
        renderTrTable();
    });
}
function bootCharts(){
    if(typeof Chart==='undefined') return;
    const mk=(id,type,labels,data,color)=>{const el=document.getElementById(id); if(!el) return; new Chart(el,{type,data:{labels,datasets:[{label:'',data,backgroundColor:color,borderColor:color,tension:.35,fill:type==='line'}]},options:{responsive:true,plugins:{legend:{display:false}}}});};
    mk('trChartCombustivel','bar',(window.TR_CHART_COMB||[]).map(x=>x.label),(window.TR_CHART_COMB||[]).map(x=>Number(x.value||0)),'#1E3A8A');
    mk('trChartGastos','line',(window.TR_CHART_GAST||[]).map(x=>x.label),(window.TR_CHART_GAST||[]).map(x=>Number(x.value||0)),'#FACC15');
    const elPie=document.getElementById('trChartCustos'); if(elPie){new Chart(elPie,{type:'pie',data:{labels:(window.TR_CHART_CUST||[]).map(x=>x.label),datasets:[{data:(window.TR_CHART_CUST||[]).map(x=>Number(x.value||0)),backgroundColor:['#1E3A8A','#FACC15','#94a3b8']}]},options:{responsive:true}});} 
    mk('trChartMotorista','bar',(window.TR_CHART_MOT||[]).map(x=>x.label),(window.TR_CHART_MOT||[]).map(x=>Number(x.value||0)),'#16A34A');
}
function getCurrentTableRows(){
    return trFiltered.map(r=>[
        r.codigo||('OS-'+String(r.ordem_servico_id||r.id||'').padStart(4,'0')),
        trFmtDate(r.data_relatorio), r.viatura_id||'-', r.condutor||'-', r.projeto||'-',
        r.km_inicial??'-', r.km_final??'-', trFormatNum(r.distancia_km||0), trFormatNum(r.combustivel_l||0), trFormatNum(r.custo_total_mt||0), r.status||'-'
    ]);
}
function downloadTransportPDF(){
    const form = document.getElementById('trFilterForm');
    if(!form) return;
    const fd = new FormData(form);
    const p = new URLSearchParams();
    for (const [k,v] of fd.entries()) p.set(k, String(v));
    p.set('doc','relatorio_transporte_pdf');
    window.location.href = '?' + p.toString();
}
function downloadTransportExcel(){
    const form = document.getElementById('trFilterForm');
    if(!form) return;
    const fd = new FormData(form);
    const p = new URLSearchParams();
    for (const [k,v] of fd.entries()) p.set(k, String(v));
    p.set('doc','relatorio_transporte_excel');
    window.location.href = '?' + p.toString();
}
(function(){
    const host = document.getElementById('trReportModern');
    if(host && typeof host.closest === 'function'){
        const whiteCard = host.closest('.white-card');
        if(whiteCard){
            whiteCard.style.background = 'transparent';
            whiteCard.style.border = 'none';
            whiteCard.style.boxShadow = 'none';
            whiteCard.style.padding = '0';
        }
    }
    trFiltered = trRows.slice();
    setupSort();
    setupInstantSearch();
    renderTrTable();
    bootCharts();
})();
</script>
