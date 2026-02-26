<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$sql="SELECT data_movimento,viatura_id,litros,documento_ref,motorista,fornecedor FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='OFICINA MECANICA 2026' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31' ORDER BY data_movimento ASC,id ASC";
$st=$pdo->query($sql);
foreach(($st?$st->fetchAll(PDO::FETCH_ASSOC):[]) as $r){echo $r['data_movimento'],' | ',$r['viatura_id'],' | ',$r['litros'],'L | Doc:',$r['documento_ref'],' | ',$r['motorista'],"\n";}
?>
