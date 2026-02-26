<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows=[
 ['2026-01-05','AGM 257 MP',70,'37313','D.F','B.VILCON','GESTAO 2026','DIESEL PARA DONA SONIA'],
 ['2026-01-06','AFP 824 MC',120,'37318','D.G','B.VILCON','GESTAO 2026','DIESEL PARA BOSS'],
 ['2026-01-07','AGJ 990 MP',20,'37318','S.WILLIAM','B.VILCON','GESTAO 2026','DIESEL PARA SR.WILLIAM'],
 ['2026-01-08','GERADOR',40,'37324','NETO','B.VILCON','GESTAO 2026','TRABILHOS INTERNOS'],
 ['2026-01-10','GERADOR',40,'37326','ASSLAM','B.VILCON','GESTAO 2026','TIRAR AGUA ENCASA DA MAE DE BOSS'],
 ['2026-01-12','ADG 322 MP',10,'37326','ASSLAM','B.VILCON','GESTAO 2026','ASSISTENCIA ENCASA DA MAE DE BOSS'],
 ['2026-01-13','AGJ 990 MP',20,'37400','S.WILLIAM','B.VILCON','GESTAO 2026','DIESEL PARA SR WILLIAM'],
 ['2026-01-15','AGJ 990 MP',20,'37375','S.WILLIAM','B.VILCON','GESTAO 2026','DIESEL PARA SR.WILLIAM'],
 ['2026-01-16','AGJ 990 MP',20,'37409','S.WILLIAM','B.VILCON','GESTAO 2026','DIESEL PARA SR.WILLIAM'],
 ['2026-01-20','AFP 824 MC',117,'37489','SR.NADEEM','B.VILCON','GESTAO 2026','DIESEL PARA DR.GERAL'],
 ['2026-01-21','AFL 509 MC',20,'37551','ENG.EMILIO','B.VILCON','GESTAO 2026','DIESEL SEMANAL'],
 ['2026-01-22','AFE 641 MC',10,'37555','PEREIRA','B.VILCON','GESTAO 2026','MONTAGEM DE PORTAO ENCASA DE BOSS E TRABALHOS ESCOLAR'],
 ['2026-01-22','AAD 740 IB',20,'37560','D.F','B.VILCON','GESTAO 2026','CARREGAMENTO DE MATERIAL ENCASA DA DONA SONIA PARA VILCON'],
 ['2026-01-23','ALW 920 SF',15000,'37569','D.G','B.VILCON','GESTAO 2026','FORNECER A SASOL'],
 ['2026-01-23','AFE 641 MC',5,'37560','D.F','B.VILCON','GESTAO 2026','TRANSPORTE ESCOLAR'],
 ['2026-01-24','AFE 641 MC',5,'37531','DF','B.VILCON','GESTAO 2026','TRABALHOS ENCASA DE BOOS E ASSISTENCIA ENCASA DA DONA SONIA'],
 ['2026-01-28','AFL 509 MC',20,'37639','ENG. EMILIO','B.VILCON','GESTAO 2026','DIESEL SEMANAL'],
 ['2026-01-29','AGM 257 MP',70,'37648','D.F','B.VILCON','GESTAO 2026','DIESEL PARA DONA SONIA'],
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

$tot=$pdo->prepare("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='GESTAO 2026' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31'");
$tot->execute();
echo "TOTAL_JAN_2026_LITROS=".(float)$tot->fetchColumn()."\n";
?>
