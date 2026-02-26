<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(80, 200);
$like = '%' . $search . '%';

$items = [];
$seen = [];

try {
    $sql = "
        SELECT id, TRIM(COALESCE(codigo, '')) AS codigo, TRIM(COALESCE(nome, '')) AS nome
        FROM transporte_projectos
        WHERE COALESCE(NULLIF(TRIM(codigo), ''), NULLIF(TRIM(nome), '')) IS NOT NULL
          AND (:search = '' OR codigo LIKE :like OR nome LIKE :like)
        ORDER BY nome ASC, codigo ASC
        LIMIT {$limit}
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['search' => $search, 'like' => $like]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $codigo = trim((string)($r['codigo'] ?? ''));
        $nome = trim((string)($r['nome'] ?? ''));
        $val = $codigo !== '' ? $codigo : $nome;
        if ($val === '') continue;
        $key = mb_strtoupper($val, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $items[] = [
            'id' => 'proj:' . (int)($r['id'] ?? 0),
            'codigo' => $codigo,
            'nome' => $nome,
            'label' => $codigo !== '' && $nome !== '' ? ($codigo . ' - ' . $nome) : ($val),
        ];
    }
} catch (Throwable $e) {}

$left = max(0, $limit - count($items));
if ($left > 0) {
    try {
        $sql2 = "
            SELECT DISTINCT TRIM(projeto) AS projeto
            FROM transporte_guias
            WHERE TRIM(COALESCE(projeto, '')) <> ''
              AND (:search = '' OR projeto LIKE :like)
            ORDER BY projeto ASC
            LIMIT {$left}
        ";
        $st2 = $pdo->prepare($sql2);
        $st2->execute(['search' => $search, 'like' => $like]);
        foreach (($st2->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $nome = trim((string)($r['projeto'] ?? ''));
            if ($nome === '') continue;
            $key = mb_strtoupper($nome, 'UTF-8');
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $items[] = [
                'id' => 'proj_hist:' . md5($nome),
                'codigo' => '',
                'nome' => $nome,
                'label' => $nome,
            ];
        }
    } catch (Throwable $e) {}
}

api_json(['ok' => true, 'items' => $items]);

