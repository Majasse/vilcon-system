<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$novos=[
  'HOSPITAL DE MANGUNGUMETE',
  'TRANSPORTE 2026',
  'OFICINA MECANICA 2026',
  'GESTAO 2026',
  'PERFURACAO 2026',
  'PRODUCAO DE BLOCOS 2026',
  'MURRO DA VILCON',
  'GRAVEL ROAD T22'
];

$maxNum=0;
$st=$pdo->query("SELECT codigo FROM transporte_projectos WHERE codigo LIKE 'PROJ-2026-%'");
foreach(($st?$st->fetchAll(PDO::FETCH_COLUMN):[]) as $c){
  if(preg_match('/^PROJ-2026-(\d{3,})$/',(string)$c,$m)){
    $n=(int)$m[1];
    if($n>$maxNum)$maxNum=$n;
  }
}

$check=$pdo->prepare("SELECT id FROM transporte_projectos WHERE UPPER(TRIM(nome))=UPPER(TRIM(:nome)) LIMIT 1");
$ins=$pdo->prepare("INSERT INTO transporte_projectos (codigo,nome,cliente,localizacao,gestor,combustivel_previsto_l,data_inicio,data_fim,status,descricao,criado_em,atualizado_em) VALUES (:codigo,:nome,:cliente,NULL,NULL,0,NULL,NULL,'Ativo',NULL,NOW(),NOW())");

$inseridos=[];$existentes=[];
foreach($novos as $nome){
  $check->execute([':nome'=>$nome]);
  $id=(int)($check->fetchColumn()?:0);
  if($id>0){$existentes[]=$nome;continue;}
  $maxNum++;
  $codigo='PROJ-2026-'.str_pad((string)$maxNum,3,'0',STR_PAD_LEFT);
  $ins->execute([':codigo'=>$codigo,':nome'=>$nome,':cliente'=>$nome]);
  $inseridos[]=$codigo.' | '.$nome;
}

echo "INSERIDOS:\n";
foreach($inseridos as $x){echo $x,"\n";}
echo "EXISTENTES:\n";
foreach($existentes as $x){echo $x,"\n";}
?>
