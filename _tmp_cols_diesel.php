<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$st=$pdo->query('SHOW COLUMNS FROM transporte_mapa_diesel');
foreach(($st?$st->fetchAll(PDO::FETCH_ASSOC):[]) as $r){echo $r['Field'],' | ',$r['Type'],' | ',$r['Null'],' | ',$r['Default'],"\n";}
?>
