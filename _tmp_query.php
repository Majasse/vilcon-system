<?php
require 'c:/wamp64/www/vilcon-systemon/public/app/config/db.php';
$stmt = $pdo->query("SELECT DISTINCT origem_modulo, area_solicitante FROM logistica_requisicoes");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
