<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows=[
 ['2026-01-13','AJY 062 MP',75,'37398','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','DIESEL PRA SP HOWARD'],
 ['2026-01-17','ADG 322 MP',74,'37412','EMMANUEL','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AKD 117 MP',264,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','ALW 656 MC',100,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AAO 084 SF',360,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AIP 138 MP',134,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AHV 648 MP',50,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AAO 083 SF',370,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AEU 530 MC',500,'','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-17','AEU 530 MC',50,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','AIQ 375 MP',200,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-17','CILINDRO HAMM',250,'37412','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-19','TANQUE PEQUENO',1000,'37471','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-19','AAA 954 TT',180,'37471','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-19','AJG 539 MP',339,'37471','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-21','AEU 530 MC',860,'37543','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-22','CILINDRO HAMM TY',234,'37554','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-22','AIU 841 MC',210,'37555','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-22','AAZ 272 SF',345,'37556','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-22','AEU 530 MC',1000,'','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-23','AAM 724 SF',250,'37558','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','REALIZACAO DE ACTIVIDADES'],
 ['2026-01-23','AEU 530 MC',850,'37558','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-26','AEU 530 MC',940,'37630','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-27','AEU 530 MC',920,'37634','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
 ['2026-01-28','AJY 062 MP',76,'37640','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','DIESEL PRA SP HOWARD'],
 ['2026-01-30','AEU 530 MC',1000,'37651','HAWARD','BOMBA DA VILCON','GRAVEL ROAD T22','FORNECIMENTO DE DIESEL NO PROJECTO'],
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
$tot=$pdo->query("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='GRAVEL ROAD T22' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31'")->fetchColumn();
echo "TOTAL_JAN_2026_LITROS=".(float)$tot."\n";
?>
