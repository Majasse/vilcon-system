<?php
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$tables = ['transporte_frota_ativos','activos','usuarios','transporte_reservas','transporte_guias','transporte_planos_manutencao','transporte_pedidos_reparacao','transporte_projectos'];
foreach($tables as $t){
  $exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}'")->fetchColumn()>0;
  echo "\n$t ".($exists?'exists':'missing')."\n";
  if($exists){
    $cols=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(', ', $cols)."\n";
  }
}
?>
