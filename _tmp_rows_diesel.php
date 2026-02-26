<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$sql="SELECT id,codigo,data_movimento,projeto,tipo_movimento,documento_ref,viatura_id,motorista,litros,preco_unitario,valor_total,fornecedor,responsavel,observacoes FROM transporte_mapa_diesel ORDER BY id DESC LIMIT 12";
$st=$pdo->query($sql);
foreach(($st?$st->fetchAll(PDO::FETCH_ASSOC):[]) as $r){echo json_encode($r,JSON_UNESCAPED_UNICODE),"\n";}
?>
