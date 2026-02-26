<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$st=$pdo->query('SELECT COUNT(*) AS total FROM transporte_projectos');
$row=$st->fetch(PDO::FETCH_ASSOC);
echo 'TOTAL=',(int)($row['total']??0),"\n";
?>
