<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(60, 200);
$like = '%' . $search . '%';

$items = [];
$seen = [];

$sqlPessoal = "
    SELECT
        p.id,
        TRIM(COALESCE(p.nome, '')) AS nome,
        TRIM(COALESCE(p.numero, '')) AS numero_funcionario,
        TRIM(COALESCE(c.nome, '')) AS funcao,
        TRIM(COALESCE(p.estado, 'Activo')) AS estado
    FROM pessoal p
    LEFT JOIN cargos c ON c.id = p.cargo_id
    WHERE LOWER(COALESCE(p.estado, 'activo')) IN ('activo','ativo','1')
      AND (
          LOWER(COALESCE(c.nome, '')) LIKE '%motorista%'
          OR LOWER(COALESCE(c.nome, '')) LIKE '%condutor%'
          OR LOWER(COALESCE(c.nome, '')) LIKE '%operador%'
      )
      AND (:search = '' OR p.nome LIKE :like OR CAST(p.numero AS CHAR) LIKE :like OR c.nome LIKE :like)
    ORDER BY p.nome ASC
    LIMIT {$limit}
";
$st = $pdo->prepare($sqlPessoal);
$st->execute(['search' => $search, 'like' => $like]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($rows as $row) {
    $nome = trim((string)($row['nome'] ?? ''));
    if ($nome === '') continue;
    $key = mb_strtoupper($nome, 'UTF-8');
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    $numero = trim((string)($row['numero_funcionario'] ?? ''));
    $funcao = trim((string)($row['funcao'] ?? 'Motorista'));
    $items[] = [
        'id' => 'pessoal:' . (int)$row['id'],
        'nome' => $nome,
        'carta_conducao' => $numero,
        'categoria' => $funcao !== '' ? $funcao : 'Motorista',
        'status' => 'Ativo',
        'label' => $nome . ' - Carta: ' . ($numero !== '' ? $numero : 'n/d'),
    ];
}

$left = max(0, $limit - count($items));
if ($left > 0) {
    $sqlHistorico = "
        SELECT DISTINCT
            TRIM(colaborador) AS nome,
            TRIM(funcao) AS funcao
        FROM transporte_presencas
        WHERE TRIM(COALESCE(colaborador, '')) <> ''
          AND (
              LOWER(COALESCE(funcao, '')) LIKE '%motorista%'
              OR LOWER(COALESCE(funcao, '')) LIKE '%condutor%'
              OR LOWER(COALESCE(funcao, '')) LIKE '%operador%'
          )
          AND (:search = '' OR colaborador LIKE :like OR funcao LIKE :like)
        ORDER BY colaborador ASC
        LIMIT {$left}
    ";
    $sh = $pdo->prepare($sqlHistorico);
    $sh->execute(['search' => $search, 'like' => $like]);
    $histRows = $sh->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($histRows as $row) {
        $nome = trim((string)($row['nome'] ?? ''));
        if ($nome === '') continue;
        $key = mb_strtoupper($nome, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $items[] = [
            'id' => 'hist:' . md5($nome),
            'nome' => $nome,
            'carta_conducao' => '',
            'categoria' => trim((string)($row['funcao'] ?? 'Funcionario')),
            'status' => 'Ativo',
            'label' => $nome . ' - Carta: n/d',
        ];
    }
}

api_json(['ok' => true, 'items' => $items]);
