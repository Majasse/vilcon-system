<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows=[
 ['2026-01-05','ADG 322 MP',20,'37311','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','TRANSPORTE DE ENG.EMILIO A MANGUNGUMETE'],
 ['2026-01-06','ADG 322 MP',12,'37316','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','ASSISTENCIA EM MANGUNGUMETE'],
 ['2026-01-08','ADG 322 MP',12,'37322','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','TRANSPORTE DO ENG.EMILIO A MANGUNGUMETE'],
 ['2026-01-13','ADG 322 MP',20,'37394','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','ASSISTENCIA EM MANGUNGUMETE E LEVAR AGUA'],
 ['2026-01-22','AEW 976 MC',12,'37553','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','ASSISTENCIA EM MANGUNGUMETE'],
 ['2026-01-23','AEW 976 MC',20,'37561','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','ASSISTENCIA EM MANGUNGUMETE E BUSCAR AGUA'],
 ['2026-01-26','AIF 584 MP',80,'37632','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','TRANSPORTE DE COLABORADORES A MANGUNGUMETE'],
 ['2026-01-29','AFL 509 MC',20,'37617','ENG.EMILIO','B. VILCON','HOSPITAL DE MANGUNGUMETE','TRABALHOS EM MANGUNGUMETE'],
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
  $codigo='DIE-2026-'.str_pad((string)$maxId,4,'0',STR_PAD_LEFT);
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

$tot=$pdo->prepare("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='HOSPITAL DE MANGUNGUMETE' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31'");
$tot->execute();
echo "TOTAL_JAN_2026_LITROS=".(float)$tot->fetchColumn()."\n";
?>
