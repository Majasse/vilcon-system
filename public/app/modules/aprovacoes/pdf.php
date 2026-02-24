<?php
$tp=n($_GET['pdf']??''); $pid=(int)($_GET['id']??0);
if($tp!=='' && $pid>0){
  $st=$pdo->prepare('SELECT * FROM aprovacoes_solicitacoes WHERE id=:i LIMIT 1'); $st->execute([':i'=>$pid]); $s=$st->fetch(PDO::FETCH_ASSOC);
  if(!$s){ http_response_code(404); echo 'SolicitaÃ§Ã£o nÃ£o encontrada.'; exit; }
  $rows=cotRows((string)($s['quadro_cotacoes']??''));
  ?>
<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title><?= $tp==='guia'?'Guia de AutorizaÃ§Ã£o de Compra':'FormulÃ¡rio de AprovaÃ§Ã£o' ?></title>
<style>
body{font-family:Arial,sans-serif;margin:14px;color:#111}.h{border:2px solid #111;border-radius:10px;overflow:hidden}.s{height:10px;background:#f4b400}.hc{display:flex;justify-content:space-between;align-items:center;padding:12px 14px}.b{display:flex;align-items:center;gap:10px}.b img{width:120px}.box{border:1px solid #111;border-radius:8px;padding:10px;margin-top:10px;font-size:12px}table{width:100%;border-collapse:collapse;margin-top:10px;font-size:12px}th,td{border:1px solid #111;padding:7px;text-align:left}.sign{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:20px}.si{border-top:1px solid #111;padding-top:6px;text-align:center;font-size:11px}
</style>    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/global-loader.css">
</head>
<div id="vilcon-global-loader" class="vilcon-loader-overlay" aria-live="polite" aria-busy="true" aria-label="A processar">
    <div class="vilcon-loader-spinner" role="status" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</div>
<div class="h"><div class="s"></div><div class="hc"><div class="b"><img src="/vilcon-systemon/public/assets/img/logo-vilcon.png" alt="Vilcon"><strong><?= $tp==='guia'?'Guia de AutorizaÃ§Ã£o de Compra':'FormulÃ¡rio de AprovaÃ§Ã£o' ?></strong></div><div style="font-size:11px;text-align:right"><div><b>CÃ³digo:</b> <?= h($s['codigo']??'-') ?></div><div><b>Guia:</b> <?= h($s['guia_codigo']??'-') ?></div><div><b>Data:</b> <?= h(date('d/m/Y H:i')) ?></div></div></div></div>
<div class="box"><b>Tipo:</b> <?= h($s['tipo']??'-') ?> | <b>Projeto:</b> <?= h($s['projeto']??'-') ?> | <b>Valor:</b> <?= m($s['valor_estimado']??0) ?></div>
<div class="box"><b>TÃ­tulo:</b> <?= h($s['titulo']??'-') ?></div>
<div class="box"><b>Justificativa tÃ©cnica:</b><br><?= nl2br(h($s['justificativa_tecnica']??'-')) ?></div>
<div class="box"><b>Fornecedor autorizado:</b> <?= h($s['fornecedor_nome']??'-') ?></div>
<table><thead><tr><th>Fornecedor</th><th>Valor</th><th>Prazo</th><th>Obs</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="4">Sem quadro de cotaÃ§Ãµes informado.</td></tr><?php else: foreach($rows as $r): ?><tr><td><?= h($r['f']) ?></td><td><?= h($r['v']) ?></td><td><?= h($r['p']) ?></td><td><?= h($r['o']) ?></td></tr><?php endforeach; endif; ?>
</tbody></table>
<div class="sign"><div class="si">Solicitante</div><div class="si">Diretor (AutorizaÃ§Ã£o)</div><div class="si">Fornecedor (Contraprova)</div></div>
    <script src="/vilcon-systemon/public/assets/js/global-loader.js"></script>
</body></html>
<?php exit; }

