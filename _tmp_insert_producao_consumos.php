<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows=[
 ['2026-01-06','AJD 146 MP',20,'37317','NETO','B.VILCON','PRODUCAO DE BLOCOS 2026','ORGANIZAR AREIA NA PRODUCAO'],
 ['2026-01-07','ALW 656 MC',40,'37319','NETO','B.VILCON','PRODUCAO DE BLOCOS 2026','CARREGAMENTO DE PAVE PARA MAXIXE'],
 ['2025-01-21','MANITOU',40,'37549','NETO','B.VILCON','PRODUCAO DE BLOCOS 2026','TRABALHOS NA PRODUCAO'],
];

$maxId=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM transporte_mapa_diesel')->fetchColumn();
$chk=$pdo->prepare("SELECT id FROM transporte_mapa_diesel WHERE data_movimento=:d AND UPPER(TRIM(viatura_id))=UPPER(TRIM(:v)) AND TRIM(COALESCE(documento_ref,''))=TRIM(:doc) AND UPPER(TRIM(COALESCE(projeto,'')))=UPPER(TRIM(:p)) LIMIT 1");
$ins=$pdo->prepare("INSERT INTO transporte_mapa_diesel (codigo,data_movimento,projeto,tipo_movimento,documento_ref,ordem_servico_id,origem_registo,viatura_id,motorista,km_horimetro,litros,preco_unitario,valor_total,saldo_tanque_l,fornecedor,responsavel,observacoes,criado_em) VALUES (:codigo,:data_movimento,:projeto,'Saida',:documento_ref,NULL,'Manual',:viatura_id,:motorista,NULL,:litros,0,0,NULL,:fornecedor,:responsavel,:observacoes,NOW())");

$insCount=0;$skipCount=0;
foreach($rows as $r){
  [$data,$viat,$lit,$doc,$mot,$forn,$proj,$obs]=$r;
  $chk->execute([':d'=>$data,':v'=>$viat,':doc'=>$doc,':p'=>$proj]);
  if((int)$chk->fetchColumn()>0){$skipCount++;continue;}
  $maxId++;
  $codigo='DIE-'.substr($data,0,4).'-'.str_pad((string)$maxId,4,'0',STR_PAD_LEFT);
  $ins->execute([
    ':codigo'=>$codigo,
    ':data_movimento'=>$data,
    ':projeto'=>$proj,
    ':documento_ref'=>$doc,
    ':viatura_id'=>$viat,
    ':motorista'=>$mot,
    ':litros'=>$lit,
    ':fornecedor'=>$forn,
    ':responsavel'=>'Michael',
    ':observacoes'=>$obs
  ]);
  $insCount++;
}

echo "INSERIDOS={$insCount}\n";
echo "IGNORADOS_DUPLICADOS={$skipCount}\n";
$tot26=$pdo->query("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='PRODUCAO DE BLOCOS 2026' AND data_movimento BETWEEN '2026-01-01' AND '2026-12-31'")->fetchColumn();
$tot25=$pdo->query("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='PRODUCAO DE BLOCOS 2026' AND data_movimento BETWEEN '2025-01-01' AND '2025-12-31'")->fetchColumn();
echo "TOTAL_2026_LITROS=".(float)$tot26."\n";
echo "TOTAL_2025_LITROS=".(float)$tot25."\n";
?>
