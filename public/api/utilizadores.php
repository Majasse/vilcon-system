<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(80, 200);
$like = '%' . $search . '%';

$sql = "
    SELECT id, nome, COALESCE(NULLIF(TRIM(perfil), ''), 'Sem departamento') AS departamento
    FROM usuarios
    WHERE LOWER(COALESCE(status, '')) IN ('ativo','1')
      AND (:search = '' OR nome LIKE :like OR username LIKE :like OR email LIKE :like OR perfil LIKE :like)
    ORDER BY nome ASC
    LIMIT {$limit}
";
$st = $pdo->prepare($sql);
$st->execute(['search' => $search, 'like' => $like]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$items = [];
foreach ($rows as $row) {
    $nome = trim((string)($row['nome'] ?? ''));
    if ($nome === '') continue;
    $dep = trim((string)($row['departamento'] ?? ''));
    $items[] = [
        'id' => 'user:' . (int)$row['id'],
        'nome' => $nome,
        'departamento' => $dep,
        'status' => 'Ativo',
        'label' => $nome . ' - ' . ($dep !== '' ? $dep : 'Sem departamento'),
    ];
}

api_json(['ok' => true, 'items' => $items]);
