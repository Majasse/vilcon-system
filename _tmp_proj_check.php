<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
echo "[COLUNAS]\n";
$st=$pdo->query('SHOW COLUMNS FROM transporte_projectos');
foreach(($st?$st->fetchAll(PDO::FETCH_ASSOC):[]) as $r){echo $r['Field'],"\n";}
echo "\n[ATUAIS]\n";
$st2=$pdo->query('SELECT id,codigo,nome,status,cliente,localizacao FROM transporte_projectos ORDER BY id DESC LIMIT 30');
foreach(($st2?$st2->fetchAll(PDO::FETCH_ASSOC):[]) as $r){echo json_encode($r,JSON_UNESCAPED_UNICODE),"\n";}
?>
