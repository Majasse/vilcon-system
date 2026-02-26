<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$st=$pdo->query("SELECT codigo,nome,status FROM transporte_projectos ORDER BY id DESC LIMIT 12");
foreach(($st?$st->fetchAll(PDO::FETCH_ASSOC):[]) as $r){echo $r['codigo'],' | ',$r['nome'],' | ',$r['status'],"\n";}
?>
