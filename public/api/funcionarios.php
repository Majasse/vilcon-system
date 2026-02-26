<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(100, 200);
$like = '%' . $search . '%';

$items = [];
$seen = [];

try {
    $sql = "
        SELECT
            p.id,
            TRIM(COALESCE(p.nome, '')) AS nome,
            TRIM(COALESCE(p.numero, '')) AS numero,
            TRIM(COALESCE(c.nome, '')) AS funcao,
            TRIM(COALESCE(p.estado, 'Activo')) AS estado
        FROM pessoal p
        LEFT JOIN cargos c ON c.id = p.cargo_id
        WHERE LOWER(COALESCE(p.estado, 'activo')) IN ('activo','ativo','1')
          AND (:search = '' OR p.nome LIKE :like OR CAST(p.numero AS CHAR) LIKE :like OR c.nome LIKE :like)
        ORDER BY p.nome ASC
        LIMIT {$limit}
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['search' => $search, 'like' => $like]);
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $nome = trim((string)($r['nome'] ?? ''));
        if ($nome === '') continue;
        $key = mb_strtoupper($nome, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $numero = trim((string)($r['numero'] ?? ''));
        $funcao = trim((string)($r['funcao'] ?? ''));
        $items[] = [
            'id' => 'pes:' . (int)($r['id'] ?? 0),
            'nome' => $nome,
            'numero' => $numero,
            'funcao' => $funcao,
            'label' => $nome . ($funcao !== '' ? (' - ' . $funcao) : ''),
        ];
    }
} catch (Throwable $e) {}

api_json(['ok' => true, 'items' => $items]);

