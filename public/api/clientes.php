<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(80, 200);
$like = '%' . $search . '%';

$items = [];
$seen = [];

try {
    $sql = "
        SELECT id, TRIM(COALESCE(nome, '')) AS nome, TRIM(COALESCE(contacto, '')) AS contacto, TRIM(COALESCE(email, '')) AS email
        FROM transporte_aluguer_clientes
        WHERE TRIM(COALESCE(nome, '')) <> ''
          AND (:search = '' OR nome LIKE :like OR contacto LIKE :like OR email LIKE :like)
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
        $email = trim((string)($r['email'] ?? ''));
        $items[] = [
            'id' => 'cli:' . (int)($r['id'] ?? 0),
            'nome' => $nome,
            'contacto' => $contacto,
            'email' => $email,
            'label' => $nome . ($contacto !== '' ? (' - ' . $contacto) : ''),
        ];
    }
} catch (Throwable $e) {}

$left = max(0, $limit - count($items));
if ($left > 0) {
    try {
        $sql2 = "
            SELECT DISTINCT TRIM(empresa_cliente) AS nome
            FROM transporte_guias
            WHERE TRIM(COALESCE(empresa_cliente, '')) <> ''
              AND (:search = '' OR empresa_cliente LIKE :like)
            ORDER BY empresa_cliente ASC
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
            $items[] = [
                'id' => 'cli_hist:' . md5($nome),
                'nome' => $nome,
                'contacto' => '',
                'email' => '',
                'label' => $nome,
            ];
        }
    } catch (Throwable $e) {}
}

api_json(['ok' => true, 'items' => $items]);

