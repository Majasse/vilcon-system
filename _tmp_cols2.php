<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
foreach(['transporte_frota_eventos','transporte_guias'] as $t){
 echo "\n$t\n";
 $cols=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='$t' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
 echo implode(', ', $cols)."\n";
}
?>
