<?php
if(!isset($view)) return;
$aluguerView = trim((string) ($_GET['view'] ?? $view));
$aluguerViewsPermitidas = ['estacionamento', 'viaturas_maquinas', 'timesheets', 'pagamentos', 'clientes', 'modulo'];
if(!in_array($aluguerView, $aluguerViewsPermitidas, true)) {
    $aluguerView = 'estacionamento';
}
?>
<style>
.white-card--aluguer{background:transparent!important;border:none!important;box-shadow:none!important;padding:0!important}
.aluguer-enterprise-app{font-family:'Inter','Segoe UI',sans-serif;color:#0f172a}
.aluguer-enterprise-app~*{display:none!important}
.al-card{background:#fff;border:1px solid #dbe3ef;border-radius:12px;padding:14px}
.al-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
.al-breadcrumb{font-size:12px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.03em}
.al-title{margin:4px 0 0;font-size:26px;font-weight:800}
.al-actions{display:flex;gap:8px;flex-wrap:wrap}
.al-btn{height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:0 12px;background:#fff;font-size:12px;font-weight:700;cursor:pointer}
.al-btn.primary{background:#0b3b82;border-color:#0b3b82;color:#fff}
.al-search{margin-top:10px}.al-search input{width:100%;height:38px;border:1px solid #d3dbe8;border-radius:9px;padding:0 12px}
.al-kpis{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:10px;margin-top:12px}
.al-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px}.al-kpi .l{font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase}.al-kpi .v{font-size:20px;font-weight:800;margin-top:3px}
.al-grid{display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-top:10px}.al-chart{background:linear-gradient(135deg,#0b3b82,#1d4f96);color:#fff;border-radius:12px;padding:12px}.al-chart h4{margin:0 0 8px;font-size:12px;text-transform:uppercase}
.al-bars{display:flex;align-items:flex-end;gap:8px;height:90px}.al-bar{flex:1;background:rgba(255,255,255,.4);border-radius:6px 6px 0 0;position:relative;min-height:8px}.al-bar span{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);font-size:10px;white-space:nowrap}
.al-alerts{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px}.al-alert{padding:7px 8px;border-radius:8px;font-size:12px;font-weight:700;margin-bottom:7px}
.st-ok{background:#ecfdf3;color:#166534}.st-warn{background:#fef9c3;color:#854d0e}.st-bad{background:#fee2e2;color:#991b1b}
.al-filter-row{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:8px;margin-top:12px}.al-filter-row input,.al-filter-row select{height:36px;border:1px solid #d6deea;border-radius:9px;padding:0 10px;font-size:12px}
.al-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff;margin-top:10px}.al-table{width:100%;border-collapse:collapse;min-width:1100px}.al-table th,.al-table td{padding:9px;border-bottom:1px solid #eef2f7;font-size:12px;text-align:left}.al-table th{font-size:11px;color:#64748b;text-transform:uppercase}
.al-status{display:inline-flex;align-items:center;height:22px;border-radius:999px;padding:0 8px;font-size:11px;font-weight:700}.al-green{background:#ecfdf3;color:#166534}.al-yellow{background:#fff7ed;color:#9a3412}.al-red{background:#fee2e2;color:#991b1b}
.al-actions-row a,.al-actions-row button{margin-right:6px}
@media (max-width:1100px){.al-kpis,.al-filter-row{grid-template-columns:repeat(2,minmax(130px,1fr))}.al-grid{grid-template-columns:1fr}}
</style>
<div class="aluguer-enterprise-app" id="aluguerEnterpriseApp">
    <div class="al-card">
        <div class="al-top">
            <div>
                <div class="al-breadcrumb">Sistema Integrado / Aluguer de Equipamentos / <?= htmlspecialchars($tituloView ?? 'Módulo') ?></div>
                <h2 class="al-title">Aluguer de Equipamentos</h2>
            </div>
            <div class="al-actions">
                <button class="al-btn" type="button" onclick="aluguerExportCurrentTable()"><i class="fa-solid fa-file-excel"></i> Excel</button>
                <button class="al-btn" type="button" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
            </div>
        </div>
        <div class="al-search"><input type="text" id="aluguerGlobalSearch" placeholder="Pesquisa rápida global"></div>
        <?php if(!empty($aluguer_feedback)): ?><div style="margin-top:10px;padding:10px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:12px;font-weight:700;"><?= htmlspecialchars($aluguer_feedback) ?></div><?php endif; ?>

        <div class="al-kpis">
            <article class="al-kpi"><div class="l">Receita Total</div><div class="v"><?= number_format((float) ($aluguer_dashboard['receita_total'] ?? 0), 2, ',', '.') ?></div></article>
            <article class="al-kpi"><div class="l">Total Ativos</div><div class="v"><?= count($aluguer_ativos_data ?? []) ?></div></article>
            <article class="al-kpi"><div class="l">Alugueres</div><div class="v"><?= (int) ($aluguer_stats['total_alugueres'] ?? 0) ?></div></article>
            <article class="al-kpi"><div class="l">Ocupação</div><div class="v"><?= number_format((float) ($aluguer_dashboard['ocupacao_percent'] ?? 0), 1, ',', '.') ?>%</div></article>
            <article class="al-kpi"><div class="l">Pendentes</div><div class="v"><?= (int) ($aluguer_stats['pendentes'] ?? 0) ?></div></article>
            <article class="al-kpi"><div class="l">Vencidos</div><div class="v"><?= (int) ($aluguer_stats['vencidos'] ?? 0) ?></div></article>
        </div>

        <div class="al-grid">
            <div class="al-chart">
                <h4>Faturamento Mensal</h4>
                <div class="al-bars">
                    <?php $fatMesRows = $aluguer_dashboard['faturamento_mensal'] ?? []; $fatMax = 0.0; foreach($fatMesRows as $fmr){$fatMax=max($fatMax,(float)($fmr['total']??0));} if(empty($fatMesRows)){$fatMesRows=[['periodo'=>date('Y-m'),'total'=>0]];} foreach($fatMesRows as $fmr): $fatVal=(float)($fmr['total']??0); $fatH=$fatMax>0?max(8,(int)round(($fatVal/$fatMax)*90)):8; ?>
                        <div class="al-bar" style="height: <?= $fatH ?>px"><span><?= htmlspecialchars((string)($fmr['periodo']??'-')) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="al-alerts">
                <div class="al-alert st-ok">Ativos mais alugados: <?= count($aluguer_dashboard['ativos_mais_alugados'] ?? []) ?></div>
                <div class="al-alert st-warn">Alertas manutenção: <?= (int) ($aluguer_dashboard['alertas_manutencao'] ?? 0) ?></div>
                <div class="al-alert st-bad">Pagamentos vencidos: <?= (int) ($aluguer_dashboard['alertas_vencidos'] ?? 0) ?></div>
            </div>
        </div>

        <div class="al-table-wrap">
            <table class="al-table" id="aluguerCurrentTable">
                <thead>
                <?php if($aluguerView === 'estacionamento'): ?>
                    <tr><th>ID</th><th>Cliente</th><th>Viatura/Máquina</th><th>Matrícula</th><th>Data Entrada</th><th>Hora Entrada</th><th>Data Saída</th><th>Hora Saída</th><th>Tempo Total</th><th>Valor Cobrado</th><th>Status</th></tr>
                <?php elseif($aluguerView === 'viaturas_maquinas'): ?>
                    <tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Matrícula / Série</th><th>Ano</th><th>Status</th><th>Próx. Manutenção</th></tr>
                <?php elseif($aluguerView === 'timesheets'): ?>
                    <tr><th>ID</th><th>Cliente</th><th>Projeto</th><th>Ativo</th><th>Operador</th><th>Data</th><th>Hora Início</th><th>Hora Fim</th><th>Total Horas</th><th>Status</th></tr>
                <?php elseif($aluguerView === 'pagamentos'): ?>
                    <tr><th>Nº Fatura</th><th>Cliente</th><th>Referência</th><th>Valor</th><th>Imposto</th><th>Total</th><th>Status</th><th>Emissão</th><th>Vencimento</th><th>Método</th></tr>
                <?php else: ?>
                    <tr><th>ID</th><th>Nome</th><th>NUIT</th><th>Telefone</th><th>Email</th><th>Endereço</th><th>Total Alugueres</th><th>Total Faturado</th><th>Dívida Atual</th><th>Status</th></tr>
                <?php endif; ?>
                </thead>
                <tbody>
                <?php if($aluguerView === 'estacionamento'): foreach(($aluguer_est_data ?? []) as $r): $enTs=!empty($r['data_entrada'])?strtotime((string)$r['data_entrada']):null; $saTs=!empty($r['data_saida'])?strtotime((string)$r['data_saida']):null; $minTot=($enTs&&$saTs&&$saTs>$enTs)?(int)floor(($saTs-$enTs)/60):0; $tempoTxt=$minTot>0?floor($minTot/60).'h '.($minTot%60).'m':'-'; $stEst=strtolower(trim((string)($r['status_registo']??'estacionado'))); ?>
                    <tr><td><?= (int)($r['id']??0) ?></td><td><?= htmlspecialchars((string)($r['cliente']??'-')) ?></td><td><?= htmlspecialchars((string)($r['equipamento']??'-')) ?></td><td><?= htmlspecialchars((string)($r['placa']??'-')) ?></td><td><?= !empty($r['data_entrada'])?date('d/m/Y',strtotime((string)$r['data_entrada'])):'-' ?></td><td><?= !empty($r['data_entrada'])?date('H:i',strtotime((string)$r['data_entrada'])):'-' ?></td><td><?= !empty($r['data_saida'])?date('d/m/Y',strtotime((string)$r['data_saida'])):'-' ?></td><td><?= !empty($r['data_saida'])?date('H:i',strtotime((string)$r['data_saida'])):'-' ?></td><td><?= htmlspecialchars($tempoTxt) ?></td><td><?= number_format((float)($r['valor_cobrado']??0),2,',','.') ?></td><td><span class="al-status <?= $stEst==='saiu'?'al-green':'al-yellow' ?>"><?= htmlspecialchars((string)($r['status_registo']??'Estacionado')) ?></span></td></tr>
                <?php endforeach; elseif($aluguerView === 'viaturas_maquinas'): foreach(($aluguer_ativos_data ?? []) as $a): $stAt=strtolower(trim((string)($a['status_operacional']??''))); ?>
                    <tr><td><?= (int)($a['id']??0) ?></td><td><?= htmlspecialchars((string)($a['descricao']??'-')) ?></td><td><?= htmlspecialchars((string)($a['tipo_ativo']??'-')) ?></td><td><?= htmlspecialchars((string)(($a['matricula']??'')!==''?$a['matricula']:($a['codigo']??'-'))) ?></td><td><?= htmlspecialchars((string)($a['ano_fabrico']??'-')) ?></td><td><span class="al-status <?= in_array($stAt,['operacional'],true)?'al-green':(in_array($stAt,['em manutencao'],true)?'al-yellow':'al-red') ?>"><?= htmlspecialchars((string)($a['status_operacional']??'-')) ?></span></td><td><?= htmlspecialchars((string)($a['proxima_manutencao_km']??'-')) ?></td></tr>
                <?php endforeach; elseif($aluguerView === 'timesheets'): foreach(($aluguer_ts_data ?? []) as $t): $stTs=strtolower(trim((string)($t['status']??'pendente'))); ?>
                    <tr><td><?= (int)($t['id']??0) ?></td><td><?= htmlspecialchars((string)(($t['cliente']??'')!==''?$t['cliente']:($t['empresa_cliente']??'-'))) ?></td><td><?= htmlspecialchars((string)($t['projeto']??'-')) ?></td><td><?= htmlspecialchars((string)(($t['ativo']??'')!==''?$t['ativo']:($t['viatura_id']??'-'))) ?></td><td><?= htmlspecialchars((string)(($t['operador']??'')!==''?$t['operador']:($t['responsavel']??'-'))) ?></td><td><?= !empty($t['data_ref'])?date('d/m/Y',strtotime((string)$t['data_ref'])):'-' ?></td><td><?= htmlspecialchars((string)($t['hora_inicio']??'-')) ?></td><td><?= htmlspecialchars((string)($t['hora_fim']??'-')) ?></td><td><?= number_format((float)($t['horas_uso']??0),2,',','.') ?></td><td><span class="al-status <?= $stTs==='aprovado'?'al-green':($stTs==='faturado'?'al-red':'al-yellow') ?>"><?= htmlspecialchars((string)($t['status']??'Pendente')) ?></span></td></tr>
                <?php endforeach; elseif($aluguerView === 'pagamentos'): foreach(($aluguer_pg_data ?? []) as $p): $stPg=strtolower(trim((string)($p['status']??'pendente'))); ?>
                    <tr><td>FAT-<?= str_pad((string)((int)($p['id']??0)),5,'0',STR_PAD_LEFT) ?></td><td><?= htmlspecialchars((string)(($p['cliente']??'')!==''?$p['cliente']:($p['empresa_cliente']??'-'))) ?></td><td><?= htmlspecialchars((string)($p['referencia']??'-')) ?></td><td><?= number_format((float)($p['valor']??0),2,',','.') ?></td><td><?= number_format((float)($p['imposto']??0),2,',','.') ?></td><td><?= number_format((float)((($p['total']??null)!==null)?$p['total']:($p['valor']??0)),2,',','.') ?></td><td><span class="al-status <?= $stPg==='pago'?'al-green':($stPg==='atrasado'?'al-red':'al-yellow') ?>"><?= htmlspecialchars((string)($p['status']??'Pendente')) ?></span></td><td><?= !empty($p['data_emissao'])?date('d/m/Y',strtotime((string)$p['data_emissao'])):'-' ?></td><td><?= !empty($p['vencimento'])?date('d/m/Y',strtotime((string)$p['vencimento'])):'-' ?></td><td><?= htmlspecialchars((string)($p['metodo_pagamento']??'-')) ?></td></tr>
                <?php endforeach; else: foreach(($aluguer_cli_data ?? []) as $c): $stCli=strtolower(trim((string)($c['status']??'ativo'))); ?>
                    <tr><td><?= (int)($c['id']??0) ?></td><td><?= htmlspecialchars((string)($c['nome']??'-')) ?></td><td><?= htmlspecialchars((string)($c['nuit']??'-')) ?></td><td><?= htmlspecialchars((string)($c['contacto']??'-')) ?></td><td><?= htmlspecialchars((string)($c['email']??'-')) ?></td><td><?= htmlspecialchars((string)($c['endereco']??'-')) ?></td><td><?= (int)($c['total_alugueres']??0) ?></td><td><?= number_format((float)($c['total_faturado']??0),2,',','.') ?></td><td><?= number_format((float)($c['divida_atual']??0),2,',','.') ?></td><td><span class="al-status <?= $stCli==='ativo'?'al-green':'al-red' ?>"><?= htmlspecialchars((string)($c['status']??'Ativo')) ?></span></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function aluguerExportCurrentTable(){var table=document.getElementById('aluguerCurrentTable');if(!table)return;var rows=table.querySelectorAll('tr');var out=[];rows.forEach(function(r){var cols=r.querySelectorAll('th,td');var row=[];cols.forEach(function(c){row.push('"'+c.innerText.replace(/"/g,'""').trim()+'"');});out.push(row.join(','));});var blob=new Blob([out.join('\n')],{type:'text/csv;charset=utf-8;'});var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='aluguer_<?= htmlspecialchars($aluguerView ?? 'lista') ?>.csv';a.click();URL.revokeObjectURL(a.href);}
</script>
