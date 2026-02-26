<?php
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
echo "[TABLES]\n";
$st = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name");
foreach(($st ? $st->fetchAll(PDO::FETCH_COLUMN) : []) as $t){ echo $t, "\n"; }
echo "\n[COLUMNS]\n";
$sql = "SELECT table_name,column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND (LOWER(column_name) LIKE '%motor%' OR LOWER(column_name) LIKE '%condut%' OR LOWER(column_name) LIKE '%carta%' OR LOWER(column_name) LIKE '%funcion%' OR LOWER(column_name) LIKE '%colabor%') ORDER BY table_name,ordinal_position";
$st2 = $pdo->query($sql);
foreach(($st2 ? $st2->fetchAll(PDO::FETCH_ASSOC) : []) as $r){ echo $r['table_name'],'.',$r['column_name'],"\n"; }
echo "\n[USUARIOS]\n";
$st3 = $pdo->query("SELECT id,nome,username,perfil,status FROM usuarios ORDER BY nome ASC");
foreach(($st3 ? $st3->fetchAll(PDO::FETCH_ASSOC) : []) as $r){ echo $r['id'],'|',$r['nome'],'|',$r['username'],'|',$r['perfil'],'|',$r['status'],"\n"; }
?>
