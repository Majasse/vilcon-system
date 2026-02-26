<?php
$pdo = new PDO('mysql:host=localhost;dbname=vilcon_vrp;charset=utf8mb4','root','');
$sql = "SELECT p.nome, p.numero, c.nome AS funcao FROM pessoal p LEFT JOIN cargos c ON c.id=p.cargo_id WHERE LOWER(COALESCE(p.estado,'activo')) IN ('activo','ativo','1') AND (LOWER(COALESCE(c.nome,'')) LIKE '%motorista%' OR LOWER(COALESCE(c.nome,'')) LIKE '%condutor%' OR LOWER(COALESCE(c.nome,'')) LIKE '%operador%') ORDER BY p.nome ASC LIMIT 30";
$st=$pdo->query($sql);
foreach(($st?$st->fetchAll(PDO::FETCH_ASSOC):[]) as $r){
  echo $r['nome'],' | Nº ',$r['numero'],' | ',$r['funcao'],"\n";
}
?>
