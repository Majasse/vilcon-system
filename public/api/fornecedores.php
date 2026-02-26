<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(80, 200);
$like = '%' . $search . '%';

$items = [];
$seen = [];

try {
    $sql = "
        SELECT id, TRIM(COALESCE(nome, '')) AS nome, TRIM(COALESCE(contacto, '')) AS contacto
        FROM transporte_fornecedores
        WHERE TRIM(COALESCE(nome, '')) <> ''
          AND (:search = '' OR nome LIKE :like OR contacto LIKE :like)
        ORDER BY nome ASC
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
        $contacto = trim((string)($r['contacto'] ?? ''));
        $items[] = [
            'id' => 'tf:' . (int)($r['id'] ?? 0),
            'nome' => $nome,
            'contacto' => $contacto,
            'label' => $nome . ($contacto !== '' ? (' - ' . $contacto) : ''),
        ];
    }
} catch (Throwable $e) {}

$left = max(0, $limit - count($items));
if ($left > 0) {
    try {
        $sql2 = "
            SELECT id, TRIM(COALESCE(nome, '')) AS nome, TRIM(COALESCE(contacto, '')) AS contacto
            FROM logistica_fornecedores
            WHERE TRIM(COALESCE(nome, '')) <> ''
              AND (:search = '' OR nome LIKE :like OR contacto LIKE :like)
            ORDER BY nome ASC
            LIMIT {$left}
        ";
        $st2 = $pdo->prepare($sql2);
        $st2->execute(['search' => $search, 'like' => $like]);
        foreach (($st2->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $nome = trim((string)($r['nome'] ?? ''));
            if ($nome === '') continue;
            $key = mb_strtoupper($nome, 'UTF-8');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $contacto = trim((string)($r['contacto'] ?? ''));
            $items[] = [
                'id' => 'lf:' . (int)($r['id'] ?? 0),
                'nome' => $nome,
                'contacto' => $contacto,
                'label' => $nome . ($contacto !== '' ? (' - ' . $contacto) : ''),
            ];
        }
    } catch (Throwable $e) {}
}

api_json(['ok' => true, 'items' => $items]);

