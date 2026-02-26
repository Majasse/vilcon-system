<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows=[
 ['2026-01-05','AFE 641 MC',20,'37312','PEREIRA','B.VILCON','OFICINA MECANICA 2026','DIESEL PARA SR PEREIRA'],
 ['2026-01-09','COMPACTADORA',2,'37325','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA'],
 ['2026-01-12','AEM 513 MP',3,'37344','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA'],
 ['2026-01-12','AFE 641 MC',20,'37329','PEREIRA','B.VILCON','OFICINA MECANICA 2026','DIESEL PARA SR.PEREIRA'],
 ['2026-01-13','PA JCB',20,'37395','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA'],
 ['2026-01-14','ESCAVETA 320',5,'37403','PEREIRA','B.VILCON','OFICINA MECANICA 2026','LAVAR PECAS'],
 ['2026-01-15','GRUA GROVEL',25,'37405','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA E 5 PARA LAVAR PECAS'],
 ['2026-01-16','GRUA GROVEL',40,'37408','PEREIRA','B.VILCON','OFICINA MECANICA 2026','ACRESCENTAR PARA EXPERIENCIA'],
 ['2026-01-20','GERADOR',10,'37477','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA PARA CLIENTE'],
 ['2026-01-20','MAQUINA DE FURO',15,'37477','PEREIRA','B.VILCON','OFICINA MECANICA 2026','MANUNTENCAO'],
 ['2026-01-21','COMPACTADORAS',10,'37550','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA NA OFICINA'],
 ['2026-01-22','GERADOR',15,'37556','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA NO CLIENTE'],
 ['2026-01-23','MMM 4655',20,'37559','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA'],
 ['2026-01-26','AFE 641 MC',20,'37631','PEREIRA','B.VILCON','OFICINA MECANICA 2026','DIESEL PARA SR PEREIRA'],
 ['2026-01-26','ESCAVETA 330D',20,'37641','PEREIRA','B.VILCON','OFICINA MECANICA 2026','EXPERIENCIA'],
 ['2026-01-30','AFE 641 MC',10,'37650','PEREIRA','B.VILCON','OFICINA MECANICA 2026','ASSISTENCIA NA OFICINA'],
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

$tot=$pdo->prepare("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='OFICINA MECANICA 2026' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31'");
$tot->execute();
echo "TOTAL_JAN_2026_LITROS=".(float)$tot->fetchColumn()."\n";
?>
