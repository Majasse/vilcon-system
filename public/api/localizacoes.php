<?php
require_once __DIR__ . '/_bootstrap.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS localizacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    provincia VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_localizacao_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $provincia = trim((string)($_POST['provincia'] ?? ''));
    if ($nome === '') {
        api_json(['ok' => false, 'message' => 'Nome da localizacao e obrigatorio.'], 422);
    }

    $ins = $pdo->prepare("INSERT INTO localizacoes (nome, provincia, status) VALUES (:n, :p, 'Ativo') ON DUPLICATE KEY UPDATE provincia = IFNULL(NULLIF(VALUES(provincia), ''), provincia), status='Ativo'");
    $ins->execute(['n' => $nome, 'p' => $provincia !== '' ? $provincia : null]);

    $st = $pdo->prepare("SELECT id, nome, provincia FROM localizacoes WHERE nome = :n LIMIT 1");
    $st->execute(['n' => $nome]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    api_json(['ok' => true, 'item' => [
        'id' => 'loc:' . (int)($row['id'] ?? 0),
        'nome' => (string)($row['nome'] ?? $nome),
        'provincia' => (string)($row['provincia'] ?? ''),
        'label' => (string)($row['nome'] ?? $nome) . ' - ' . ((string)($row['provincia'] ?? '') !== '' ? (string)$row['provincia'] : 'Sem provincia'),
    ]]);
}

$seedSources = [
    "SELECT DISTINCT TRIM(localizacao) AS nome FROM transporte_projectos WHERE TRIM(COALESCE(localizacao, '')) <> ''",
    "SELECT DISTINCT TRIM(localizacao) AS nome FROM oficina_pedidos_reparacao WHERE TRIM(COALESCE(localizacao, '')) <> ''",
    "SELECT DISTINCT TRIM(local_saida) AS nome FROM transporte_guias WHERE TRIM(COALESCE(local_saida, '')) <> ''",
    "SELECT DISTINCT TRIM(destino) AS nome FROM transporte_guias WHERE TRIM(COALESCE(destino, '')) <> ''",
];
foreach ($seedSources as $sqlSeed) {
    foreach (($pdo->query($sqlSeed)->fetchAll(PDO::FETCH_COLUMN) ?: []) as $locNome) {
        $locNome = trim((string)$locNome);
        if ($locNome === '') continue;
        $stmtSeed = $pdo->prepare("INSERT IGNORE INTO localizacoes (nome, provincia, status) VALUES (:n, NULL, 'Ativo')");
        $stmtSeed->execute(['n' => $locNome]);
    }
}

$search = api_search_term();
$limit = api_limit(80, 200);
$like = '%' . $search . '%';

$sql = "
    SELECT id, nome, COALESCE(provincia, '') AS provincia
    FROM localizacoes
    WHERE LOWER(COALESCE(status, '')) IN ('ativo','1')
      AND (:search = '' OR nome LIKE :like OR provincia LIKE :like)
    ORDER BY nome ASC
    LIMIT {$limit}
";
$st = $pdo->prepare($sql);
$st->execute(['search' => $search, 'like' => $like]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$items = array_map(static function (array $r): array {
    $prov = trim((string)($r['provincia'] ?? ''));
    $nome = trim((string)($r['nome'] ?? ''));
    return [
        'id' => 'loc:' . (int)$r['id'],
        'nome' => $nome,
        'provincia' => $prov,
        'label' => $nome . ' - ' . ($prov !== '' ? $prov : 'Sem provincia'),
    ];
}, $rows);

api_json(['ok' => true, 'items' => $items]);
