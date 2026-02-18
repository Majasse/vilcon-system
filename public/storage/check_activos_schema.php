<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$rows = $pdo->query('DESCRIBE activos')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Field'] . "\t" . $r['Type'] . "\n";
}
?>
