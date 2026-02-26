<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$q=$pdo->query("SELECT data_movimento,viatura_id,litros,documento_ref,motorista,fornecedor,projeto,observacoes FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='MURRO DA VILCON' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31' ORDER BY data_movimento ASC,id ASC");
$rows=$q->fetchAll(PDO::FETCH_ASSOC);
echo 'ROWS='.count($rows)."\n";
foreach($rows as $r){
  echo implode(' | ',[$r['data_movimento'],$r['viatura_id'],$r['litros'],$r['documento_ref'],$r['motorista'],$r['fornecedor'],$r['projeto'],$r['observacoes']])."\n";
}
?>
