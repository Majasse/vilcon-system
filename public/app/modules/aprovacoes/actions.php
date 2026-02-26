<?php
$erro='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  try{
    $a=n($_POST['acao']??'');
    $u=n($_SESSION['usuario_nome']??'Diretor');
    $temFacturaId = (int)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logistica_requisicoes' AND COLUMN_NAME='factura_id'")->fetchColumn() > 0;
    if(!$temFacturaId){
      $pdo->exec("ALTER TABLE logistica_requisicoes ADD COLUMN factura_id INT NULL");
    }
    
    if($a==='aprovar_oficina'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisição inválida.');
      $st=$pdo->prepare('SELECT id,codigo,item,status,fornecedor_id,factura_id,COALESCE(valor_total,custo_total,0) AS valor_total,area_solicitante FROM logistica_requisicoes WHERE id=:i LIMIT 1');
      $st->execute([':i'=>$id]);
      $r=$st->fetch(PDO::FETCH_ASSOC);
      if(!$r||strtolower((string)$r['status'])!=='pendente') throw new RuntimeException('Apenas requisições Pendentes podem ser aprovadas.');

      $pdo->beginTransaction();
      $facturaIdAtual = (int)($r['factura_id'] ?? 0);
      if($facturaIdAtual <= 0){
        $valorReq = (float)($r['valor_total'] ?? 0);
        $fornecedorId = (int)($r['fornecedor_id'] ?? 0);
        if($fornecedorId > 0 && $valorReq > 0){
          $dataFimMes = date('Y-m-t');
          $descricao = 'Divida por requisicao ' . (string)($r['codigo'] ?? ('REQ-' . $id)) . ' - ' . (string)($r['item'] ?? 'Material');
          $departamentoRaw = strtolower(trim((string)($r['area_solicitante'] ?? 'oficina')));
          $departamento = in_array($departamentoRaw, ['oficina','transporte'], true) ? $departamentoRaw : 'oficina';
          $obsF = 'Gerada automaticamente na aprovacao da requisicao. Pagamento previsto para o fim do mes.';
          $pdo->prepare("INSERT INTO logistica_fin_facturas (fornecedor_id,departamento,descricao,valor_total,data_factura,status,observacoes) VALUES (:f,:d,:ds,:v,:dt,'Pendente',:o)")
            ->execute([
              ':f'=>$fornecedorId,
              ':d'=>$departamento,
              ':ds'=>$descricao,
              ':v'=>$valorReq,
              ':dt'=>$dataFimMes,
              ':o'=>$obsF
            ]);
          $novaFacturaId = (int)$pdo->lastInsertId();
          $codigoFactura = sprintf('FAT-%s-%04d', date('Y'), $novaFacturaId);
          $pdo->prepare('UPDATE logistica_fin_facturas SET codigo=:c WHERE id=:i')->execute([':c'=>$codigoFactura,':i'=>$novaFacturaId]);
          $facturaIdAtual = $novaFacturaId;
        }
      }

      $pdo->prepare('UPDATE logistica_requisicoes SET status=:s, factura_id=:f, decidido_por=:u, decidido_em=NOW() WHERE id=:i')
          ->execute([':s'=>'Aprovada',':f'=>$facturaIdAtual>0?$facturaIdAtual:null,':u'=>$u,':i'=>$id]);
      $pdo->commit();
      header('Location: ?approved=1'); exit;
    }
    if($a==='recusar_oficina'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisição inválida.');
      $st=$pdo->prepare('SELECT status FROM logistica_requisicoes WHERE id=:i LIMIT 1'); $st->execute([':i'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC);
      if(!$r||strtolower((string)$r['status'])!=='pendente') throw new RuntimeException('Apenas requisições Pendentes podem ser recusadas.');
      $pdo->prepare('UPDATE logistica_requisicoes SET status=:s, decidido_por=:u, decidido_em=NOW() WHERE id=:i')
          ->execute([':s'=>'Negada',':u'=>$u,':i'=>$id]);
      header('Location: ?denied=1'); exit;
    }
    if($a==='aprovar_transporte'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisição inválida.');
      $st=$pdo->prepare('SELECT status FROM transporte_requisicoes WHERE id=:i LIMIT 1'); $st->execute([':i'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC);
      if(!$r||strtolower((string)$r['status'])!=='pendente') throw new RuntimeException('Apenas requisições Pendentes podem ser aprovadas.');
      $pdo->prepare('UPDATE transporte_requisicoes SET status=:s, solicitante=:u, criado_em=NOW() WHERE id=:i') // Utilizando colunas disponiveis
          ->execute([':s'=>'Aprovada',':u'=>$u,':i'=>$id]);
      header('Location: ?approved=1'); exit;
    }
    if($a==='recusar_transporte'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisição inválida.');
      $st=$pdo->prepare('SELECT status FROM transporte_requisicoes WHERE id=:i LIMIT 1'); $st->execute([':i'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC);
      if(!$r||strtolower((string)$r['status'])!=='pendente') throw new RuntimeException('Apenas requisições Pendentes podem ser recusadas.');
      $pdo->prepare('UPDATE transporte_requisicoes SET status=:s, solicitante=:u, criado_em=NOW() WHERE id=:i')
          ->execute([':s'=>'Negada',':u'=>$u,':i'=>$id]);
      header('Location: ?denied=1'); exit;
    }
  } catch(Throwable $e){
    if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $erro=$e->getMessage();
  }
}
