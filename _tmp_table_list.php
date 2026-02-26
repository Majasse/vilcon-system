<?php
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$st=$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name");
$rows=$st->fetchAll(PDO::FETCH_COLUMN);
foreach($rows as $t){
  if (preg_match('/(viat|motor|condut|local|util|usuario|transporte|oficina)/i',$t)) {
    echo $t."\n";
  }
}
?>
