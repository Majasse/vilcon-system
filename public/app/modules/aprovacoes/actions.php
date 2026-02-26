<?php
$erro='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  try{
    $a=n($_POST['acao']??'');
    $u=n($_SESSION['usuario_nome']??'Diretor');
    
    if($a==='aprovar_oficina'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisição inválida.');
      $st=$pdo->prepare('SELECT status FROM logistica_requisicoes WHERE id=:i LIMIT 1'); $st->execute([':i'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC);
      if(!$r||strtolower((string)$r['status'])!=='pendente') throw new RuntimeException('Apenas requisições Pendentes podem ser aprovadas.');
      $pdo->prepare('UPDATE logistica_requisicoes SET status=:s, decidido_por=:u, decidido_em=NOW() WHERE id=:i')
          ->execute([':s'=>'Aprovada',':u'=>$u,':i'=>$id]);
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
  } catch(Throwable $e){ $erro=$e->getMessage(); }
}
