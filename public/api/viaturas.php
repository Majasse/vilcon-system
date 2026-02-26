<?php
require_once __DIR__ . '/_bootstrap.php';

$search = api_search_term();
$limit = api_limit(60, 200);
$like = '%' . $search . '%';

$items = [];
$seenMatriculas = [];

$sqlFrota = "
    SELECT id, COALESCE(NULLIF(TRIM(descricao), ''), 'Viatura') AS nome,
           COALESCE(NULLIF(TRIM(matricula), ''), CONCAT('SEM-', id)) AS matricula,
           COALESCE(NULLIF(TRIM(tipo_ativo), ''), 'Viatura') AS categoria,
           COALESCE(NULLIF(TRIM(status_operacional), ''), 'Ativo') AS status
    FROM transporte_frota_ativos
    WHERE (COALESCE(ativo, 1) = 1)
      AND LOWER(COALESCE(status_operacional, 'ativo')) NOT IN ('inativo','baixado','abate','vendido')
      AND (:search = '' OR matricula LIKE :like OR descricao LIKE :like OR tipo_ativo LIKE :like)
    ORDER BY matricula ASC
    LIMIT {$limit}
";

$stFrota = $pdo->prepare($sqlFrota);
$stFrota->execute(['search' => $search, 'like' => $like]);
$frotaRows = $stFrota->fetchAll(PDO::FETCH_ASSOC) ?: [];

$lastGuideByMatricula = [];
if ($frotaRows) {
    $matriculas = array_values(array_filter(array_map(static fn($r) => trim((string)($r['matricula'] ?? '')), $frotaRows)));
    if ($matriculas) {
        $ph = implode(',', array_fill(0, count($matriculas), '?'));
        $sqlGuia = "
            SELECT g.matricula, g.condutor, g.km_chegada, g.km_saida
            FROM transporte_guias g
            INNER JOIN (
                SELECT matricula, MAX(id) AS max_id
                FROM transporte_guias
                WHERE matricula IN ({$ph})
                GROUP BY matricula
            ) x ON x.max_id = g.id
        ";
        $sg = $pdo->prepare($sqlGuia);
        $sg->execute($matriculas);
        foreach (($sg->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $mk = trim((string)($row['matricula'] ?? ''));
            if ($mk === '') continue;
            $lastGuideByMatricula[$mk] = [
                'km_atual' => (float)($row['km_chegada'] ?: $row['km_saida'] ?: 0),
                'condutor_padrao' => trim((string)($row['condutor'] ?? '')),
            ];
        }
    }
}

foreach ($frotaRows as $row) {
    $matricula = trim((string)($row['matricula'] ?? ''));
    if ($matricula === '') continue;
    $seenMatriculas[strtoupper($matricula)] = true;
    $meta = $lastGuideByMatricula[$matricula] ?? ['km_atual' => 0, 'condutor_padrao' => ''];

    $items[] = [
        'id' => 'frota:' . (int)$row['id'],
        'nome' => (string)$row['nome'],
        'matricula' => $matricula,
        'categoria' => (string)$row['categoria'],
        'status' => 'Ativo',
        'label' => '[' . $matricula . '] - ' . (string)$row['nome'] . ' - ' . (string)$row['categoria'],
        'km_atual' => (float)$meta['km_atual'],
        'condutor_padrao' => (string)$meta['condutor_padrao'],
    ];
}

$restante = max(0, $limit - count($items));
if ($restante > 0) {
    $sqlAct = "
        SELECT id, COALESCE(NULLIF(TRIM(equipamento), ''), 'Equipamento') AS nome,
               COALESCE(NULLIF(TRIM(matricula), ''), CONCAT('ACT-', id)) AS matricula,
               'Equipamento' AS categoria,
               COALESCE(NULLIF(TRIM(estado), ''), 'Ativo') AS status
        FROM activos
        WHERE LOWER(COALESCE(estado, 'ativo')) NOT IN ('vendido','inativo')
          AND (:search = '' OR matricula LIKE :like OR equipamento LIKE :like)
        ORDER BY matricula ASC
        LIMIT {$restante}
    ";
    $stAct = $pdo->prepare($sqlAct);
    $stAct->execute(['search' => $search, 'like' => $like]);
    $actRows = $stAct->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($actRows as $row) {
        $matricula = trim((string)($row['matricula'] ?? ''));
        if ($matricula === '') continue;
        if (isset($seenMatriculas[strtoupper($matricula)])) continue;
        $items[] = [
            'id' => 'activos:' . (int)$row['id'],
            'nome' => (string)$row['nome'],
            'matricula' => $matricula,
            'categoria' => (string)$row['categoria'],
            'status' => 'Ativo',
            'label' => '[' . $matricula . '] - ' . (string)$row['nome'] . ' - Equipamento',
            'km_atual' => 0,
            'condutor_padrao' => '',
        ];
    }
}

api_json(['ok' => true, 'items' => $items]);
