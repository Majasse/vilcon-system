<?php
// Busca requisições pendentes da Oficina
$reqOficina = $pdo->query("SELECT id, codigo, origem, destino, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes, valor_total, custo_total FROM logistica_requisicoes WHERE origem_modulo='oficina' AND LOWER(status)='pendente' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Busca requisições pendentes de Transporte
$reqTransporte = $pdo->query("SELECT id, codigo, item_nome, quantidade_solicitada, unidade, preco_unitario_estimado, valor_total_estimado, moeda, fornecedor_sugerido, prioridade, justificativa, solicitante, status, criado_em FROM transporte_requisicoes WHERE LOWER(status)='pendente' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$saved=isset($_GET['saved']); $approved=isset($_GET['approved']); $denied=isset($_GET['denied']);
$total_pendentes = count($reqOficina) + count($reqTransporte);
?>
<div class="main-content">
  <div class="top-bar">
    <h2>Módulo de Aprovações</h2>
    <div class="user-info"><i class="fa-regular fa-user"></i><strong><?= h($_SESSION['usuario_nome']??'Utilizador') ?></strong></div>
  </div>
  <div class="dashboard-container aprov-wrapper">
    <style>
      .aprov-wrapper{padding:18px}.al{border-radius:8px;padding:10px 12px;margin-bottom:10px;border:1px solid transparent;font-size:13px}.ok{background:#f0fdf4;color:#14532d;border-color:#bbf7d0}.er{background:#fef2f2;color:#7f1d1d;border-color:#fecaca}
      .box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 4px 12px rgba(17,24,39,.05);padding:14px;margin-bottom:14px}
      .box h4{margin:0 0 10px;color:#111827;font-size:14px;text-transform:uppercase}
      .box header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
      .badge{background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600}
      .tw{overflow:auto;max-height:70vh;border:1px solid #e5e7eb;border-radius:10px}
      .t{width:100%;border-collapse:collapse;min-width:900px;font-size:12px}
      .t th,.t td{border-bottom:1px solid #e5e7eb;padding:8px 9px;text-align:left;vertical-align:top;color:#111827;background:#fff}
      .t th{position:sticky;top:0;z-index:1;background:#f9fafb}
      .btn{border:1px solid #111827;border-radius:6px;padding:6px 10px;font-size:11px;background:#111827;color:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;font-weight:600}
      .btn.w{background:#059669;border-color:#059669} /* Verde Aprovar */
      .btn.r{background:#dc2626;border-color:#dc2626} /* Vermelho Recusar */
      .a{display:flex;gap:6px;flex-wrap:wrap}
      .in{display:inline-block;margin:0}
    </style>

    <?php if($erro!==''): ?><div class="al er"><?= h($erro) ?></div><?php endif; ?>
    <?php if($approved): ?><div class="al ok">Requisição aprovada com sucesso.</div><?php endif; ?>
    <?php if($denied): ?><div class="al ok">Requisição recusada.</div><?php endif; ?>

    <div class="box" style="display:flex;align-items:center;gap:10px;background:#f8fafc;border-color:#cbd5e1">
      <i class="fas fa-inbox fa-2x" style="color:#64748b"></i>
      <div>
        <h3 style="margin:0;font-size:16px;color:#0f172a">Aprovações de Logística (Oficina e Transporte)</h3>
        <p style="margin:2px 0 0 0;font-size:12px;color:#475569">Existem <?= $total_pendentes ?> requisições aguardando deliberação da diretoria.</p>
      </div>
    </div>

    <!-- Tabela Oficina -->
    <article class="box">
      <header>
        <h4>Requisições Pendentes | <span style="color:#2563eb">Oficina</span></h4>
        <span class="badge"><?= count($reqOficina) ?> pendentes</span>
      </header>
      <div class="tw">
        <table class="t">
          <thead>
            <tr>
              <th>Código</th>
              <th>Data/Responsável</th>
              <th>Item Solicitado</th>
              <th>Quantidade</th>
              <th>Prioridade / Observações</th>
              <th>Custos (Estimados)</th>
              <th style="width:190px">Deliberação</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$reqOficina): ?>
            <tr><td colspan="7">Não há requisições pendentes vindas da Oficina.</td></tr>
          <?php else: foreach($reqOficina as $ro): ?>
            <tr>
              <td><strong><?= h($ro['codigo']??'-') ?></strong></td>
              <td><?= h($ro['data_requisicao']??'-') ?><br><small><?= h($ro['responsavel']??'-') ?></small></td>
              <td><?= h($ro['item']??'-') ?></td>
              <td><strong><?= m($ro['quantidade']??0) ?></strong> <?= h($ro['unidade']??'un') ?></td>
              <td><span style="font-weight:bold;color:<?= ($ro['prioridade']==='Alta'||$ro['prioridade']==='Urgente')?'#dc2626':'#4b5563' ?>"><?= h($ro['prioridade']??'Normal') ?></span><br><small style="color:#6b7280"><?= h($ro['observacoes']??'-') ?></small></td>
              <td><div>Valor: <?= m($ro['valor_total']??0) ?></div><div>Custo: <?= m($ro['custo_total']??0) ?></div></td>
              <td>
                <div class="a">
                  <form method="POST" class="in" onsubmit="return confirm('Deseja realmente APROVAR esta requisição?');">
                    <input type="hidden" name="acao" value="aprovar_oficina">
                    <input type="hidden" name="id" value="<?= (int)$ro['id'] ?>">
                    <button type="submit" class="btn w"><i class="fas fa-check"></i> Aprovar</button>
                  </form>
                  <form method="POST" class="in" onsubmit="return confirm('Deseja RECUSAR esta requisição?');">
                    <input type="hidden" name="acao" value="recusar_oficina">
                    <input type="hidden" name="id" value="<?= (int)$ro['id'] ?>">
                    <button type="submit" class="btn r"><i class="fas fa-times"></i> Recusar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </article>

    <!-- Tabela Transporte -->
    <article class="box" style="margin-top:14px">
      <header>
        <h4>Requisições Pendentes | <span style="color:#059669">Transporte</span></h4>
        <span class="badge"><?= count($reqTransporte) ?> pendentes</span>
      </header>
      <div class="tw">
        <table class="t">
          <thead>
            <tr>
              <th>Código</th>
              <th>Data/Solicitante</th>
              <th>Item Solicitado</th>
              <th>Quantidade</th>
              <th>Prioridade / Justificativa</th>
              <th>Custo Est. / Fornecedor</th>
              <th style="width:190px">Deliberação</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$reqTransporte): ?>
            <tr><td colspan="7">Não há requisições pendentes vindas de Transportes.</td></tr>
          <?php else: foreach($reqTransporte as $rt): ?>
            <tr>
              <td><strong><?= h($rt['codigo']??'-') ?></strong></td>
              <td><?= h(date('Y-m-d', strtotime((string)$rt['criado_em']))) ?><br><small><?= h($rt['solicitante']??'-') ?></small></td>
              <td><?= h($rt['item_nome']??'-') ?></td>
              <td><strong><?= m($rt['quantidade_solicitada']??0) ?></strong> <?= h($rt['unidade']??'un') ?></td>
              <td><span style="font-weight:bold;color:<?= ($rt['prioridade']==='Alta'||$rt['prioridade']==='Urgente')?'#dc2626':'#4b5563' ?>"><?= h($rt['prioridade']??'Normal') ?></span><br><small style="color:#6b7280"><?= h($rt['justificativa']??'-') ?></small></td>
              <td><div><?= h($rt['moeda']??'MZN') ?> <?= m($rt['valor_total_estimado']??0) ?></div><small><?= h($rt['fornecedor_sugerido']??'-') ?></small></td>
              <td>
                <div class="a">
                  <form method="POST" class="in" onsubmit="return confirm('Deseja realmente APROVAR esta requisição?');">
                    <input type="hidden" name="acao" value="aprovar_transporte">
                    <input type="hidden" name="id" value="<?= (int)$rt['id'] ?>">
                    <button type="submit" class="btn w"><i class="fas fa-check"></i> Aprovar</button>
                  </form>
                  <form method="POST" class="in" onsubmit="return confirm('Deseja RECUSAR esta requisição?');">
                    <input type="hidden" name="acao" value="recusar_transporte">
                    <input type="hidden" name="id" value="<?= (int)$rt['id'] ?>">
                    <button type="submit" class="btn r"><i class="fas fa-times"></i> Recusar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </article>

  </div>
</div>
