<?php
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$tables = ['pessoal','equipa','cargos','transporte_presencas','oficina_presencas_rh'];
foreach($tables as $t){
  echo "\n[$t COLUMNS]\n";
  $st=$pdo->query("SHOW COLUMNS FROM `$t`");
  if(!$st){ echo "(sem tabela)\n"; continue; }
  $cols=[];
  while($r=$st->fetch(PDO::FETCH_ASSOC)){ $cols[]=$r['Field']; }
  echo implode(', ', $cols),"\n";
  echo "[$t SAMPLE]\n";
  $st2=$pdo->query("SELECT * FROM `$t` LIMIT 20");
  if($st2){
    while($row=$st2->fetch(PDO::FETCH_ASSOC)){
      echo json_encode($row, JSON_UNESCAPED_UNICODE),"\n";
    }
  }
}
?>
