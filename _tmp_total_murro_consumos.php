<?php
$pdo=new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$tot=$pdo->query("SELECT COALESCE(SUM(litros),0) FROM transporte_mapa_diesel WHERE UPPER(TRIM(projeto))='MURRO DA VILCON' AND data_movimento BETWEEN '2026-01-01' AND '2026-01-31'")->fetchColumn();
echo 'TOTAL_JAN_2026_LITROS='.(float)$tot."\n";
?>
