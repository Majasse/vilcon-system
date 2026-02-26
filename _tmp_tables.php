<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
echo "connected\n";
$tables = ['viaturas','motoristas','localizacoes','utilizadores','usuarios','activos','transporte_viagens','oficina_pedidos_reparacao'];
foreach($tables as $t){
  $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
  $st->execute([$t]);
  $exists=(int)$st->fetchColumn()>0;
  echo "TABLE $t: ".($exists?'YES':'NO')."\n";
  if($exists){
    $c=$pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position");
    $c->execute([$t]);
    $cols=$c->fetchAll(PDO::FETCH_COLUMN);
    echo '  COLUMNS: '.implode(', ', $cols)."\n";
  }
}
