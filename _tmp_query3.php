<?php
require 'c:/wamp64/www/vilcon-systemon/public/app/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM transporte_requisicoes");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("SHOW COLUMNS FROM logistica_requisicoes");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
