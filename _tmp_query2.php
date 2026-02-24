<?php
require 'c:/wamp64/www/vilcon-systemon/public/app/config/db.php';
$stmt = $pdo->query("SHOW TABLES LIKE '%requisicoes%'");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
$stmt2 = $pdo->query("SHOW TABLES LIKE 'transporte_%'");
print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));
