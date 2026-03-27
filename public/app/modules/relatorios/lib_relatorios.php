<?php

function relatorios_periodos(): array {
    return ['diario' => 'Diario', 'semanal' => 'Semanal', 'mensal' => 'Mensal'];
}

function relatorios_setores(): array {
    return [
        'documental' => 'Gestao Documental',
        'oficina' => 'Oficina',
        'hse' => 'HSE',
        'transporte' => 'Transporte',
        'gestao_frota' => 'Gestao de Frota',
        'aluguer' => 'Alugueres',
        'frentista' => 'Frentista',
        'rh' => 'RH',
        'seguranca' => 'Seguranca',
        'logistica' => 'Logistica',
        'aprovacoes' => 'Aprovacoes',
        'sistema' => 'Sistema',
    ];
}

function relatorios_h(string $valor): string {
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function relatorios_moeda(float $valor): string {
    return number_format($valor, 2, ',', '.') . ' MZN';
}

function relatorios_tab_existe(PDO $pdo, string $tabela): bool {
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $tabela]);
    return (int)$st->fetchColumn() > 0;
}

function relatorios_garantir_estrutura(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS relatorios_exec_config (
        id INT NOT NULL PRIMARY KEY,
        destinatarios VARCHAR(255) NOT NULL DEFAULT '',
        periodicidade ENUM('diario','semanal','mensal') NOT NULL DEFAULT 'semanal',
        hora_envio TIME NOT NULL DEFAULT '18:00:00',
        assunto_base VARCHAR(180) NOT NULL DEFAULT 'Relatorio Executivo Integrado',
        ativo TINYINT(1) NOT NULL DEFAULT 0,
        atualizado_por INT NULL,
        atualizado_em DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS relatorios_exec_envios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modo VARCHAR(20) NOT NULL DEFAULT 'manual',
        periodicidade VARCHAR(20) NOT NULL,
        referencia_inicio DATE NOT NULL,
        referencia_fim DATE NOT NULL,
        destinatarios VARCHAR(255) NOT NULL,
        destinatarios_hash CHAR(64) NOT NULL,
        assunto VARCHAR(255) NOT NULL,
        status ENUM('enviado','falhou') NOT NULL DEFAULT 'enviado',
        mensagem TEXT NULL,
        total_registos INT NOT NULL DEFAULT 0,
        enviado_em DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_envio (periodicidade, referencia_inicio, referencia_fim),
        INDEX idx_status (status, enviado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ((int)$pdo->query("SELECT COUNT(*) FROM relatorios_exec_config")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO relatorios_exec_config (id, destinatarios, periodicidade, hora_envio, assunto_base, ativo, atualizado_em)
                    VALUES (1, '', 'semanal', '18:00:00', 'Relatorio Executivo Integrado', 0, NOW())");
    }

    try {
        $pdo->exec("UPDATE relatorios_exec_config
                    SET periodicidade = 'semanal'
                    WHERE id = 1
                      AND periodicidade = 'diario'
                      AND COALESCE(destinatarios, '') = ''
                      AND ativo = 0");
    } catch (Throwable $e) {
        // Mantem compatibilidade se a estrutura ainda estiver a ser preparada.
    }
}

function relatorios_carregar_cfg(PDO $pdo): array {
    $row = $pdo->query("SELECT destinatarios, periodicidade, TIME_FORMAT(hora_envio,'%H:%i') AS hora_envio, assunto_base, ativo
                        FROM relatorios_exec_config WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'destinatarios' => trim((string)($row['destinatarios'] ?? '')),
        'periodicidade' => trim((string)($row['periodicidade'] ?? 'semanal')),
        'hora_envio' => trim((string)($row['hora_envio'] ?? '18:00')),
        'assunto_base' => trim((string)($row['assunto_base'] ?? 'Relatorio Executivo Integrado')),
        'ativo' => (int)($row['ativo'] ?? 0),
    ];
}

function relatorios_salvar_cfg(PDO $pdo, array $cfg, int $usuarioId = 0): void {
    $st = $pdo->prepare("UPDATE relatorios_exec_config
                         SET destinatarios = :d,
                             periodicidade = :p,
                             hora_envio = :h,
                             assunto_base = :a,
                             ativo = :at,
                             atualizado_por = :u,
                             atualizado_em = NOW()
                         WHERE id = 1");
    $st->execute([
        ':d' => $cfg['destinatarios'],
        ':p' => $cfg['periodicidade'],
        ':h' => $cfg['hora_envio'] . ':00',
        ':a' => $cfg['assunto_base'],
        ':at' => $cfg['ativo'],
        ':u' => $usuarioId,
    ]);
}

function relatorios_emails_validos(string $texto): array {
    $saida = [];
    foreach ((preg_split('/[;,]+/', $texto) ?: []) as $email) {
        $email = trim((string)$email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $saida[] = $email;
        }
    }
    return array_values(array_unique($saida));
}

function relatorios_periodo_resumo(string $periodo, string $referencia): array {
    $dt = DateTime::createFromFormat('Y-m-d', $referencia) ?: new DateTime('today');
    $inicio = clone $dt;
    $fim = clone $dt;
    $titulo = 'Relatorio Diario';
    if ($periodo === 'semanal') {
        $n = (int)$dt->format('N');
        $inicio = (clone $dt)->modify('-' . max(0, $n - 1) . ' day');
        $fim = (clone $inicio)->modify('+6 day');
        $titulo = 'Relatorio Semanal';
    } elseif ($periodo === 'mensal') {
        $inicio = new DateTime($dt->format('Y-m-01'));
        $fim = (clone $inicio)->modify('last day of this month');
        $titulo = 'Relatorio Mensal';
    }
    return [
        'periodo' => $periodo,
        'inicio' => $inicio->format('Y-m-d'),
        'fim' => $fim->format('Y-m-d'),
        'titulo' => $titulo . ' | ' . $inicio->format('d/m/Y') . ' a ' . $fim->format('d/m/Y'),
    ];
}

function relatorios_periodo_auto(string $periodo, string $hora): ?array {
    $agora = new DateTime();
    if ($agora->format('H:i') < $hora) {
        return null;
    }
    if ($periodo === 'semanal') {
        if ((int)$agora->format('N') !== 1) return null;
        return relatorios_periodo_resumo('semanal', (clone $agora)->modify('-7 day')->format('Y-m-d'));
    }
    if ($periodo === 'mensal') {
        if ($agora->format('d') !== '01') return null;
        return relatorios_periodo_resumo('mensal', (clone $agora)->modify('first day of last month')->format('Y-m-d'));
    }
    return relatorios_periodo_resumo('diario', (clone $agora)->modify('-1 day')->format('Y-m-d'));
}

function relatorios_modulo_por_path(string $path): string {
    $p = strtolower($path);
    if (strpos($p, '/documental/') !== false) return 'Documental';
    if (strpos($p, '/oficina/') !== false) return 'Oficina';
    if (strpos($p, '/seguranca/') !== false) return 'Seguranca';
    if (strpos($p, '/transporte/') !== false) return 'Transporte';
    if (strpos($p, '/rh/') !== false) return 'RH';
    if (strpos($p, '/logistica/') !== false) return 'Logistica';
    if (strpos($p, '/aprovacoes/') !== false) return 'Aprovacoes';
    if (strpos($p, '/relatorios/') !== false) return 'Relatorios';
    if (strpos($p, '/login.php') !== false) return 'Login';
    return 'Sistema';
}

function relatorios_tipo_acao(string $origem, string $formatada): string {
    $origem = strtolower(trim($origem));
    $formatada = strtolower(trim($formatada));
    if (strpos($origem, 'acesso:') === 0 || strpos($formatada, 'visualizou modulo') === 0) return 'Acesso';
    if (strpos($origem, 'login:') === 0 || strpos($origem, 'login falhou:') === 0 || strpos($formatada, 'login') === 0) return 'Sessao';
    return 'Operacao';
}

function relatorios_setor_chave(string $modulo, string $detalhe, string $acao, string $tabela, string $path): string {
    $txt = strtolower($modulo . ' ' . $detalhe . ' ' . $acao . ' ' . $tabela . ' ' . $path);
    if (strpos($txt, '/relatorios/') !== false || strpos($txt, 'relatorios_exec_') !== false || strpos($txt, 'dashboard & bi') !== false || strpos($txt, 'dashboard_bi') !== false) return 'sistema';
    if (strpos($txt, 'aprov') !== false) return 'aprovacoes';
    if (strpos($txt, 'gestao_frota') !== false || strpos($txt, 'gestao de frota') !== false || strpos($txt, 'frota') !== false) return 'gestao_frota';
    if (strpos($txt, 'aluguer') !== false || strpos($txt, 'venda') !== false) return 'aluguer';
    if (strpos($txt, 'frentista') !== false || strpos($txt, 'abastec') !== false || strpos($txt, 'combust') !== false || strpos($txt, 'diesel') !== false) return 'frentista';
    if (strpos($txt, 'hse') !== false || strpos($txt, 'checklist') !== false || strpos($txt, 'epi') !== false) return 'hse';
    if (strpos($txt, 'documental') !== false) return 'documental';
    if (strpos($txt, 'oficina') !== false) return 'oficina';
    if (strpos($txt, 'transporte') !== false || strpos($txt, 'motorista') !== false || strpos($txt, 'viagem') !== false) return 'transporte';
    if (strpos($txt, 'rh') !== false || strpos($txt, 'pessoal') !== false || strpos($txt, 'avaliacao') !== false || strpos($txt, 'presenca_facial') !== false) return 'rh';
    if (strpos($txt, 'seguranca') !== false || strpos($txt, 'visitante') !== false || strpos($txt, 'guarita') !== false || strpos($txt, 'ocorrencia') !== false) return 'seguranca';
    if (strpos($txt, 'logistica') !== false || strpos($txt, 'requisic') !== false || strpos($txt, 'cotac') !== false || strpos($txt, 'fornecedor') !== false) return 'logistica';
    return 'sistema';
}

function relatorios_interpretar_auditoria(array $row, array $setores): array {
    $orig = trim((string)($row['acao'] ?? ''));
    $tab = trim((string)($row['tabela_afetada'] ?? ''));
    $path = '';
    $modulo = 'Sistema';
    $detalhe = '';
    $acao = $orig !== '' ? $orig : '-';
    $contexto = $tab !== '' ? $tab : 'Sistema';
    $params = [];

    if (stripos($orig, 'Acesso:') === 0) {
        $rota = trim((string)substr($orig, 7));
        if (preg_match('/^(GET|POST|PUT|DELETE|OPTIONS)\s+(.+)$/i', $rota, $m)) $rota = trim((string)$m[2]);
        $path = (string)parse_url($rota, PHP_URL_PATH);
        $query = (string)parse_url($rota, PHP_URL_QUERY);
        if ($query !== '') parse_str($query, $params);
        $modulo = relatorios_modulo_por_path($path);
        $mix = strtolower(trim((string)($params['view'] ?? '') . ' ' . (string)($params['tab'] ?? '') . ' ' . $path));
        if (strpos($mix, 'gestao_frota') !== false || strpos($mix, 'frota') !== false) $detalhe = 'Gestao de Frota';
        elseif (strpos($mix, 'aluguer') !== false || strpos($mix, 'venda') !== false) $detalhe = 'Alugueres';
        elseif (strpos($mix, 'frentista') !== false || strpos($mix, 'combust') !== false) $detalhe = 'Frentista';
        elseif (strpos($mix, 'hse') !== false || strpos($mix, 'checklist') !== false) $detalhe = 'HSE';
        elseif (strpos($path, '/relatorios/') !== false) $detalhe = 'Dashboard & BI';
        elseif (trim((string)($params['view'] ?? '')) !== '') $detalhe = ucwords(str_replace(['_', '-'], ' ', (string)$params['view']));
        elseif (trim((string)($params['tab'] ?? '')) !== '') $detalhe = ucwords(str_replace(['_', '-'], ' ', (string)$params['tab']));
        if ($modulo === 'Documental' && $detalhe === '') $detalhe = 'Gestao Documental';
        $acao = 'Visualizou modulo';
        $contexto = $modulo . ($detalhe !== '' ? ' - ' . $detalhe : '');
    } elseif (stripos($orig, 'LOGIN:') === 0) {
        $modulo = 'Sistema';
        $acao = 'Login';
        $contexto = trim((string)substr($orig, 6)) ?: 'Sistema';
    } elseif (stripos($orig, 'LOGIN FALHOU:') === 0) {
        $modulo = 'Sistema';
        $acao = 'Login falhou';
        $contexto = trim((string)substr($orig, 14)) ?: 'Sistema';
    } elseif (stripos($orig, 'Inseriu ') === 0) {
        $acao = 'Adicionou registo';
        $contexto = trim((string)substr($orig, 8)) ?: ($tab !== '' ? $tab : 'Operacao do sistema');
        $modulo = ucwords(str_replace(['_', '-'], ' ', $tab !== '' ? $tab : 'Sistema'));
    } elseif (stripos($orig, 'Atualizou ') === 0) {
        $acao = 'Atualizou registo';
        $contexto = trim((string)substr($orig, 9)) ?: ($tab !== '' ? $tab : 'Operacao do sistema');
        $modulo = ucwords(str_replace(['_', '-'], ' ', $tab !== '' ? $tab : 'Sistema'));
    } elseif (stripos($orig, 'Eliminou ') === 0) {
        $acao = 'Eliminou registo';
        $contexto = trim((string)substr($orig, 9)) ?: ($tab !== '' ? $tab : 'Operacao do sistema');
        $modulo = ucwords(str_replace(['_', '-'], ' ', $tab !== '' ? $tab : 'Sistema'));
    }

    $tipo = relatorios_tipo_acao($orig, $acao);
    $setorKey = relatorios_setor_chave($modulo, $detalhe, $orig, $tab, $path);

    return [
        'usuario_id' => (int)($row['usuario_id'] ?? 0),
        'usuario_nome' => trim((string)($row['usuario_nome'] ?? '')) !== '' ? trim((string)$row['usuario_nome']) : 'Sistema',
        'data_hora' => trim((string)($row['data_hora'] ?? '')),
        'acao' => $acao,
        'contexto' => $contexto,
        'tipo' => $tipo,
        'setor_key' => $setorKey,
        'setor_label' => $setores[$setorKey] ?? 'Sistema',
    ];
}

function relatorios_linhas_auditoria(PDO $pdo, array $periodo, array $setores): array {
    if (!relatorios_tab_existe($pdo, 'auditoria')) return [];
    $sql = "SELECT a.usuario_id, COALESCE(u.nome,'Sistema') AS usuario_nome, a.acao, a.tabela_afetada, a.data_hora
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.data_hora >= :inicio
              AND a.data_hora < DATE_ADD(:fim, INTERVAL 1 DAY)
            ORDER BY a.data_hora DESC
            LIMIT 2500";
    $st = $pdo->prepare($sql);
    $st->execute([':inicio' => $periodo['inicio'], ':fim' => $periodo['fim']]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $saida = [];
    foreach ($rows as $row) $saida[] = relatorios_interpretar_auditoria($row, $setores);
    return $saida;
}

function relatorios_setor_financeiro(string $texto): string {
    $txt = strtolower(trim($texto));
    if ($txt === '') return 'logistica';
    if (strpos($txt, 'aprov') !== false) return 'aprovacoes';
    if (strpos($txt, 'gestao_frota') !== false || strpos($txt, 'frota') !== false) return 'gestao_frota';
    if (strpos($txt, 'aluguer') !== false || strpos($txt, 'estacionamento') !== false || strpos($txt, 'venda') !== false) return 'aluguer';
    if (strpos($txt, 'frentista') !== false || strpos($txt, 'combust') !== false || strpos($txt, 'diesel') !== false) return 'frentista';
    if (strpos($txt, 'hse') !== false || strpos($txt, 'epi') !== false) return 'hse';
    if (strpos($txt, 'documental') !== false || strpos($txt, 'document') !== false) return 'documental';
    if (strpos($txt, 'oficina') !== false || strpos($txt, 'reparacao') !== false) return 'oficina';
    if (strpos($txt, 'transporte') !== false || strpos($txt, 'motorista') !== false || strpos($txt, 'viatura') !== false) return 'transporte';
    if (strpos($txt, 'rh') !== false || strpos($txt, 'pessoal') !== false || strpos($txt, 'recursos humanos') !== false) return 'rh';
    if (strpos($txt, 'seguranca') !== false) return 'seguranca';
    if (strpos($txt, 'logistica') !== false || strpos($txt, 'compra') !== false || strpos($txt, 'budjet') !== false) return 'logistica';
    return 'logistica';
}

function relatorios_sum(PDO $pdo, string $tabela, string $dataExpr, string $valorExpr, array $periodo, string $where = '1=1', array $params = []): float {
    if (!relatorios_tab_existe($pdo, $tabela)) return 0.0;
    $sql = "SELECT COALESCE(SUM($valorExpr), 0) AS total
            FROM `$tabela`
            WHERE $dataExpr >= :inicio
              AND $dataExpr <= :fim
              AND ($where)";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([':inicio' => $periodo['inicio'], ':fim' => $periodo['fim']], $params));
    return (float)$st->fetchColumn();
}

function relatorios_group_sum(PDO $pdo, string $tabela, string $grupoExpr, string $dataExpr, string $valorExpr, array $periodo, string $where = '1=1', array $params = []): array {
    if (!relatorios_tab_existe($pdo, $tabela)) return [];
    $sql = "SELECT $grupoExpr AS grupo, COALESCE(SUM($valorExpr), 0) AS total
            FROM `$tabela`
            WHERE $dataExpr >= :inicio
              AND $dataExpr <= :fim
              AND ($where)
            GROUP BY $grupoExpr";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([':inicio' => $periodo['inicio'], ':fim' => $periodo['fim']], $params));
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function relatorios_adicionar_financeiro(array &$financeiro, string $setor, string $tipo, float $valor, string $fonte): void {
    $valor = round((float)$valor, 2);
    if (abs($valor) < 0.0001) return;
    if (!isset($financeiro[$setor])) $setor = 'sistema';
    $financeiro[$setor][$tipo] += $valor;
    $financeiro[$setor]['fontes'][$fonte] = round(($financeiro[$setor]['fontes'][$fonte] ?? 0) + abs($valor), 2);
}

function relatorios_resultado_setor(float $saldo, bool $temFinanceiro = true): array {
    if (!$temFinanceiro) {
        return ['label' => 'Operacional', 'class' => 'neutral'];
    }
    if ($saldo > 0.0001) {
        return ['label' => 'Lucro / Beneficio', 'class' => 'positive'];
    }
    if ($saldo < -0.0001) {
        return ['label' => 'Prejuizo / Pressao', 'class' => 'negative'];
    }
    return ['label' => 'Equilibrado', 'class' => 'neutral'];
}

function relatorios_exibir_valor_setor(float $valor, bool $temFinanceiro): string {
    if (!$temFinanceiro && abs($valor) < 0.0001) {
        return '-';
    }
    return relatorios_moeda($valor);
}

function relatorios_periodo_texto(string $periodo): string {
    if ($periodo === 'mensal') return 'mensal';
    if ($periodo === 'semanal') return 'semanal';
    return 'diario';
}

function relatorios_data_curta(string $dataHora): string {
    $dataHora = trim($dataHora);
    if ($dataHora === '') return '';
    try {
        return (new DateTime($dataHora))->format('d/m');
    } catch (Throwable $e) {
        return $dataHora;
    }
}

function relatorios_resumo_atividades(array $ultimas, int $limite = 2): string {
    $partes = [];
    foreach (array_slice($ultimas, 0, $limite) as $item) {
        $texto = trim((string)($item['texto'] ?? ''));
        if ($texto === '') continue;
        $data = relatorios_data_curta((string)($item['data_hora'] ?? ''));
        $partes[] = ($data !== '' ? ($data . ': ') : '') . $texto;
    }
    return implode('; ', $partes);
}

function relatorios_consumo_por_setor(PDO $pdo, array $periodo, array $setores): array {
    $baseGeral = ['litros_combustivel' => 0.0, 'custo_combustivel' => 0.0, 'movimentos_consumo' => 0, 'valor_consumo' => 0.0];
    $consumo = ['geral' => $baseGeral, 'setores' => []];
    foreach ($setores as $key => $label) {
        $consumo['setores'][$key] = $baseGeral;
    }

    if (relatorios_tab_existe($pdo, 'transporte_mapa_diesel')) {
        $sqlComb = "SELECT COUNT(*) AS movimentos, COALESCE(SUM(COALESCE(litros,0)),0) AS litros, COALESCE(SUM(COALESCE(valor_total,0)),0) AS valor
                    FROM transporte_mapa_diesel
                    WHERE data_movimento >= :inicio
                      AND data_movimento <= :fim
                      AND LOWER(COALESCE(tipo_movimento,'')) <> 'entrada'";
        $stComb = $pdo->prepare($sqlComb);
        $stComb->execute([':inicio' => $periodo['inicio'], ':fim' => $periodo['fim']]);
        $rowComb = $stComb->fetch(PDO::FETCH_ASSOC) ?: [];
        $movComb = (int)($rowComb['movimentos'] ?? 0);
        $litrosComb = (float)($rowComb['litros'] ?? 0);
        $valorComb = (float)($rowComb['valor'] ?? 0);
        $consumo['geral']['litros_combustivel'] += $litrosComb;
        $consumo['geral']['custo_combustivel'] += $valorComb;
        $consumo['geral']['movimentos_consumo'] += $movComb;
        $consumo['geral']['valor_consumo'] += $valorComb;
        $consumo['setores']['frentista']['litros_combustivel'] += $litrosComb;
        $consumo['setores']['frentista']['custo_combustivel'] += $valorComb;
        $consumo['setores']['frentista']['movimentos_consumo'] += $movComb;
        $consumo['setores']['frentista']['valor_consumo'] += $valorComb;
    }

    if (relatorios_tab_existe($pdo, 'transporte_stock_movimentos')) {
        $sqlStock = "SELECT LOWER(COALESCE(finalidade,'')) AS finalidade, COUNT(*) AS movimentos, COALESCE(SUM(COALESCE(valor_total,0)),0) AS valor
                     FROM transporte_stock_movimentos
                     WHERE data_movimento >= :inicio
                       AND data_movimento <= :fim
                       AND LOWER(COALESCE(tipo_movimento,'')) = 'saida'
                     GROUP BY LOWER(COALESCE(finalidade,''))";
        $stStock = $pdo->prepare($sqlStock);
        $stStock->execute([':inicio' => $periodo['inicio'], ':fim' => $periodo['fim']]);
        foreach ($stStock->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $finalidade = (string)($row['finalidade'] ?? '');
            if ($finalidade === 'venda') continue;
            $setor = 'gestao_frota';
            $mov = (int)($row['movimentos'] ?? 0);
            $valor = (float)($row['valor'] ?? 0);
            $consumo['geral']['movimentos_consumo'] += $mov;
            $consumo['geral']['valor_consumo'] += $valor;
            $consumo['setores'][$setor]['movimentos_consumo'] += $mov;
            $consumo['setores'][$setor]['valor_consumo'] += $valor;
        }
    }

    if (relatorios_tab_existe($pdo, 'logistica_movimentos_stock') && relatorios_tab_existe($pdo, 'logistica_pecas')) {
        $sqlLog = "SELECT COALESCE(NULLIF(p.area_aplicacao,''), 'oficina') AS area_ref,
                          COUNT(*) AS movimentos,
                          COALESCE(SUM(COALESCE(m.quantidade,0) * COALESCE(m.custo_unitario,0)),0) AS valor
                   FROM logistica_movimentos_stock m
                   LEFT JOIN logistica_pecas p ON p.id = m.peca_id
                   WHERE DATE(m.created_at) >= :inicio
                     AND DATE(m.created_at) <= :fim
                     AND LOWER(COALESCE(m.tipo_movimento,'')) = 'saida'
                   GROUP BY COALESCE(NULLIF(p.area_aplicacao,''), 'oficina')";
        $stLog = $pdo->prepare($sqlLog);
        $stLog->execute([':inicio' => $periodo['inicio'], ':fim' => $periodo['fim']]);
        foreach ($stLog->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $setor = relatorios_setor_financeiro((string)($row['area_ref'] ?? 'oficina'));
            $mov = (int)($row['movimentos'] ?? 0);
            $valor = (float)($row['valor'] ?? 0);
            if (!isset($consumo['setores'][$setor])) continue;
            $consumo['geral']['movimentos_consumo'] += $mov;
            $consumo['geral']['valor_consumo'] += $valor;
            $consumo['setores'][$setor]['movimentos_consumo'] += $mov;
            $consumo['setores'][$setor]['valor_consumo'] += $valor;
        }
    }

    return $consumo;
}

function relatorios_montar_resumo_setor(array $setor, string $periodo): string {
    $periodoTxt = relatorios_periodo_texto($periodo);
    $atividades = relatorios_resumo_atividades((array)($setor['ultimas'] ?? []), 2);
    $temFinanceiro = (bool)($setor['tem_financeiro'] ?? false);

    if ($temFinanceiro) {
        $texto = 'Resumo ' . $periodoTxt . ': custo ' . relatorios_moeda((float)($setor['custo'] ?? 0))
            . ', receita ' . relatorios_moeda((float)($setor['receita'] ?? 0))
            . ', saldo ' . relatorios_moeda((float)($setor['saldo'] ?? 0))
            . ' e comprometido de ' . relatorios_moeda((float)($setor['comprometido'] ?? 0)) . '.';
    } else {
        $texto = 'Resumo ' . $periodoTxt . ' operacional: ' . (int)($setor['operacoes'] ?? 0)
            . ' operacoes, ' . (int)($setor['acessos'] ?? 0) . ' acessos e '
            . (int)($setor['utilizadores_total'] ?? 0) . ' responsaveis ativos.';
    }

    if ((float)($setor['consumo_litros'] ?? 0) > 0) {
        $texto .= ' Consumo controlado: ' . number_format((float)$setor['consumo_litros'], 2, ',', '.') . ' L e '
            . relatorios_moeda((float)($setor['consumo_valor'] ?? 0)) . '.';
    } elseif ((int)($setor['consumo_movimentos'] ?? 0) > 0 && (float)($setor['consumo_valor'] ?? 0) > 0) {
        $texto .= ' Consumo controlado em ' . (int)$setor['consumo_movimentos'] . ' movimentos, total de '
            . relatorios_moeda((float)$setor['consumo_valor']) . '.';
    }

    if ($atividades !== '') {
        $texto .= ' Diarias resumidas: ' . $atividades . '.';
    }

    return $texto;
}

function relatorios_top_metricas(array $mapa, int $limite = 5): array {
    if (empty($mapa)) return [];
    arsort($mapa);
    $saida = [];
    foreach (array_slice($mapa, 0, $limite, true) as $nome => $valor) {
        $saida[] = ['nome' => trim((string)$nome), 'valor' => (float)$valor];
    }
    return $saida;
}

function relatorios_percentual(float $valor, float $base, int $casas = 1): string {
    if (abs($base) < 0.0001) return '-';
    return number_format(($valor / $base) * 100, $casas, ',', '.') . '%';
}

function relatorios_classificacao_setor(array $setor): string {
    $temOperacional = (int)($setor['registos'] ?? 0) > 0 || (int)($setor['operacoes'] ?? 0) > 0 || (int)($setor['acessos'] ?? 0) > 0;
    $temFinanceiro = (bool)($setor['tem_financeiro'] ?? false);
    if ($temFinanceiro && $temOperacional) return 'Financeiro e Operacional';
    if ($temFinanceiro) return 'Financeiro';
    if ($temOperacional) return 'Operacional';
    return 'Sem movimento';
}

function relatorios_narrativa_execucao_setor(array $setor, string $periodo): string {
    $periodoTxt = relatorios_periodo_texto($periodo);
    $partes = [];
    $partes[] = 'Durante o periodo ' . $periodoTxt . ', ' . (string)($setor['label'] ?? 'o setor')
        . ' registou ' . (int)($setor['operacoes'] ?? 0) . ' operacoes, '
        . (int)($setor['acessos'] ?? 0) . ' acessos e '
        . (int)($setor['utilizadores_total'] ?? 0) . ' responsaveis ativos.';

    if ((string)($setor['top_acao'] ?? '-') !== '-') {
        $partes[] = 'Na pratica, o maior volume de trabalho esteve concentrado em "' . (string)$setor['top_acao'] . '".';
    }

    if ((bool)($setor['tem_financeiro'] ?? false)) {
        $partes[] = 'No quadro financeiro, o setor apresentou custo de '
            . relatorios_moeda((float)($setor['custo'] ?? 0))
            . ', receita de ' . relatorios_moeda((float)($setor['receita'] ?? 0))
            . ', saldo de ' . relatorios_moeda((float)($setor['saldo'] ?? 0))
            . ' e valor comprometido de ' . relatorios_moeda((float)($setor['comprometido'] ?? 0)) . '.';
        if (!empty($setor['fontes_top'][0]['nome'])) {
            $partes[] = 'A principal base de evidencia financeira foi "' . (string)$setor['fontes_top'][0]['nome'] . '".';
        }
    } else {
        $partes[] = 'O comportamento do setor foi predominantemente operacional, com foco em execucao, controlo interno e acompanhamento das rotinas do periodo.';
    }

    if ((float)($setor['consumo_litros'] ?? 0) > 0) {
        $partes[] = 'O controlo de consumo associado ao setor fechou em '
            . number_format((float)$setor['consumo_litros'], 2, ',', '.') . ' L, equivalentes a '
            . relatorios_moeda((float)($setor['consumo_valor'] ?? 0)) . '.';
    } elseif ((int)($setor['consumo_movimentos'] ?? 0) > 0 && (float)($setor['consumo_valor'] ?? 0) > 0) {
        $partes[] = 'Foram registados ' . (int)$setor['consumo_movimentos']
            . ' movimentos de consumo, no valor consolidado de '
            . relatorios_moeda((float)$setor['consumo_valor']) . '.';
    }

    $atividades = relatorios_resumo_atividades((array)($setor['ultimas'] ?? []), 3);
    if ($atividades !== '') {
        $partes[] = 'Em termos práticos, destacaram-se as seguintes entregas e atividades: ' . $atividades . '.';
    }

    return implode(' ', $partes);
}

function relatorios_recomendacao_setor(array $setor): string {
    if ((bool)($setor['tem_financeiro'] ?? false) && (float)($setor['saldo'] ?? 0) < -0.0001) {
        return 'Rever a composicao de custos, reforcar disciplina de faturacao/recebimento e acompanhar rapidamente os pontos que estao a pressionar o saldo.';
    }
    if ((float)($setor['comprometido'] ?? 0) > 0.0001 && (float)($setor['receita'] ?? 0) <= 0.0001) {
        return 'Acompanhar o valor comprometido ate fecho, validando aprovacoes, entregas e impacto real no fluxo financeiro do setor.';
    }
    if ((int)($setor['operacoes'] ?? 0) > 0 && (int)($setor['utilizadores_total'] ?? 0) <= 1) {
        return 'Formalizar melhor a distribuicao de responsabilidades e evidencias operacionais, para reduzir dependencia de um unico responsavel.';
    }
    if ((int)($setor['registos'] ?? 0) === 0 && !(bool)($setor['tem_financeiro'] ?? false)) {
        return 'Confirmar se o setor realmente nao teve atividade no periodo ou se faltam registos operacionais a serem lancados no sistema.';
    }
    return 'Manter o ritmo de execucao, reforcar qualidade dos registos e continuar a ligar evidencia operacional aos resultados e custos do setor.';
}

function relatorios_financeiro_por_setor(PDO $pdo, array $periodo, array $setores): array {
    $financeiro = [];
    foreach ($setores as $key => $label) {
        $financeiro[$key] = ['custo' => 0.0, 'receita' => 0.0, 'comprometido' => 0.0, 'fontes' => []];
    }

    relatorios_adicionar_financeiro($financeiro, 'oficina', 'custo', relatorios_sum($pdo, 'oficina_ordens_servico', 'DATE(data_abertura)', 'COALESCE(custo_total,0)', $periodo), 'OS Oficina');
    relatorios_adicionar_financeiro($financeiro, 'oficina', 'custo', relatorios_sum($pdo, 'oficina_manutencoes', 'COALESCE(data_manutencao, DATE(created_at))', 'COALESCE(custo_total,0)', $periodo), 'Manutencoes Oficina');
    relatorios_adicionar_financeiro($financeiro, 'oficina', 'custo', relatorios_sum($pdo, 'oficina_pedidos_reparacao', 'COALESCE(data_pedido, DATE(created_at))', 'COALESCE(custo_estimado,0)', $periodo), 'Pedidos Reparacao');

    relatorios_adicionar_financeiro($financeiro, 'transporte', 'receita', relatorios_sum($pdo, 'transporte_timesheets', 'COALESCE(data_retorno, data_ida, DATE(criado_em))', 'COALESCE(NULLIF(total_financeiro,0), valor_pagamento, 0)', $periodo), 'Timesheets Transporte');
    relatorios_adicionar_financeiro($financeiro, 'transporte', 'custo', relatorios_sum($pdo, 'transporte_timesheets', 'COALESCE(data_retorno, data_ida, DATE(criado_em))', 'COALESCE(custo_combustivel,0) + COALESCE(custo_mobilizacao,0)', $periodo), 'Custos Transporte');
    relatorios_adicionar_financeiro($financeiro, 'gestao_frota', 'custo', relatorios_sum($pdo, 'transporte_frota_eventos', 'COALESCE(DATE(data_evento), DATE(criado_em))', 'COALESCE(custo_estimado,0)', $periodo), 'Eventos Frota');
    relatorios_adicionar_financeiro($financeiro, 'frentista', 'custo', relatorios_sum($pdo, 'transporte_mapa_diesel', 'data_movimento', 'COALESCE(valor_total,0)', $periodo, "LOWER(COALESCE(tipo_movimento,'')) <> 'entrada'"), 'Mapa Diesel');
    relatorios_adicionar_financeiro($financeiro, 'aluguer', 'receita', relatorios_sum($pdo, 'transporte_aluguer_pagamentos', 'COALESCE(data_pagamento, data_emissao, DATE(criado_em))', 'COALESCE(NULLIF(total,0), valor, 0)', $periodo), 'Pagamentos de Aluguer');
    relatorios_adicionar_financeiro($financeiro, 'gestao_frota', 'custo', relatorios_sum($pdo, 'transporte_stock_movimentos', 'data_movimento', 'COALESCE(valor_total,0)', $periodo, "LOWER(COALESCE(tipo_movimento,'')) = 'entrada'"), 'Reposicao Stock Frota');
    relatorios_adicionar_financeiro($financeiro, 'gestao_frota', 'custo', relatorios_sum($pdo, 'transporte_stock_movimentos', 'data_movimento', 'COALESCE(valor_total,0)', $periodo, "LOWER(COALESCE(tipo_movimento,'')) = 'saida' AND LOWER(COALESCE(finalidade,'')) IN ('projeto','interno') AND LOWER(COALESCE(origem,'')) <> 'abastecimento os'"), 'Consumo Stock Frota');
    relatorios_adicionar_financeiro($financeiro, 'aluguer', 'receita', relatorios_sum($pdo, 'transporte_stock_movimentos', 'data_movimento', 'COALESCE(valor_total,0)', $periodo, "LOWER(COALESCE(tipo_movimento,'')) = 'saida' AND LOWER(COALESCE(finalidade,'')) = 'venda'"), 'Aluguer Stock Frota');

    foreach (relatorios_group_sum($pdo, 'logistica_operacional_custos', "COALESCE(NULLIF(departamento,''), 'logistica')", 'data_lancamento', 'COALESCE(valor,0)', $periodo) as $row) {
        relatorios_adicionar_financeiro($financeiro, relatorios_setor_financeiro((string)($row['grupo'] ?? 'logistica')), 'custo', (float)($row['total'] ?? 0), 'Custos Logistica');
    }
    foreach (relatorios_group_sum($pdo, 'logistica_pecas_substituidas', "COALESCE(NULLIF(area_origem,''), 'oficina')", 'data_substituicao', 'COALESCE(quantidade,0) * COALESCE(custo_unitario,0)', $periodo) as $row) {
        relatorios_adicionar_financeiro($financeiro, relatorios_setor_financeiro((string)($row['grupo'] ?? 'oficina')), 'custo', (float)($row['total'] ?? 0), 'Pecas Substituidas');
    }
    foreach (relatorios_group_sum($pdo, 'logistica_ordens_compra', "COALESCE(NULLIF(departamento,''), 'logistica')", 'COALESCE(data_registo, DATE(created_at))', 'COALESCE(valor_total,0)', $periodo, "LOWER(COALESCE(status,'')) NOT IN ('cancelada','cancelado','anulada')") as $row) {
        relatorios_adicionar_financeiro($financeiro, relatorios_setor_financeiro((string)($row['grupo'] ?? 'logistica')), 'custo', (float)($row['total'] ?? 0), 'Ordens de Compra');
    }
    foreach (relatorios_group_sum($pdo, 'logistica_requisicoes', "COALESCE(NULLIF(area_solicitante,''), NULLIF(origem_modulo,''), 'logistica')", 'COALESCE(data_requisicao, DATE(created_at))', 'COALESCE(NULLIF(budjet_debito_valor,0), NULLIF(custo_total,0), NULLIF(valor_total,0), 0)', $periodo, "LOWER(COALESCE(status,'')) NOT IN ('negada','cancelada','cancelado','rejeitada')") as $row) {
        relatorios_adicionar_financeiro($financeiro, relatorios_setor_financeiro((string)($row['grupo'] ?? 'logistica')), 'comprometido', (float)($row['total'] ?? 0), 'Requisicoes');
    }
    foreach (relatorios_group_sum($pdo, 'aprovacoes_solicitacoes', "COALESCE(NULLIF(origem_modulo,''), NULLIF(projeto,''), NULLIF(tipo,''), 'aprovacoes')", 'COALESCE(DATE(aprovado_em), DATE(created_at))', 'COALESCE(NULLIF(valor_final_praticado,0), NULLIF(valor_estimado,0), 0)', $periodo, "LOWER(COALESCE(status,'')) NOT IN ('rejeitada','reprovada','cancelada','cancelado')") as $row) {
        relatorios_adicionar_financeiro($financeiro, relatorios_setor_financeiro((string)($row['grupo'] ?? 'aprovacoes')), 'comprometido', (float)($row['total'] ?? 0), 'Aprovacoes');
    }

    return $financeiro;
}

function relatorios_resumo_integrado(array $linhas, array $setores, array $periodo, array $financeiro, array $consumo = []): array {
    $sec = [];
    foreach ($setores as $key => $label) {
        $consumoSetor = $consumo['setores'][$key] ?? [];
        $sec[$key] = [
            'key' => $key,
            'label' => $label,
            'registos' => 0,
            'operacoes' => 0,
            'acessos' => 0,
            'sessoes' => 0,
            'users' => [],
            'users_count' => [],
            'acoes' => [],
            'top_acao' => '-',
            'ultima_atividade' => '-',
            'ultimas' => [],
            'linhas_setor' => [],
            'custo' => (float)($financeiro[$key]['custo'] ?? 0),
            'receita' => (float)($financeiro[$key]['receita'] ?? 0),
            'comprometido' => (float)($financeiro[$key]['comprometido'] ?? 0),
            'fontes' => $financeiro[$key]['fontes'] ?? [],
            'consumo_litros' => (float)($consumoSetor['litros_combustivel'] ?? 0),
            'consumo_valor' => (float)($consumoSetor['valor_consumo'] ?? 0),
            'consumo_movimentos' => (int)($consumoSetor['movimentos_consumo'] ?? 0),
        ];
    }

    $tipos = ['Operacao' => 0, 'Acesso' => 0, 'Sessao' => 0];
    $utilizadores = [];

    foreach ($linhas as $linha) {
        $key = isset($sec[$linha['setor_key']]) ? $linha['setor_key'] : 'sistema';
        $sec[$key]['registos']++;
        if ($linha['tipo'] === 'Operacao') {
            $sec[$key]['operacoes']++;
            $tipos['Operacao']++;
        } elseif ($linha['tipo'] === 'Acesso') {
            $sec[$key]['acessos']++;
            $tipos['Acesso']++;
        } else {
            $sec[$key]['sessoes']++;
            $tipos['Sessao']++;
        }
        $usuario = trim((string)$linha['usuario_nome']);
        if ($usuario !== '') {
            $sec[$key]['users'][$usuario] = true;
            $sec[$key]['users_count'][$usuario] = (int)($sec[$key]['users_count'][$usuario] ?? 0) + 1;
            $utilizadores[$usuario] = true;
        }
        $acao = trim((string)$linha['acao']);
        if ($acao !== '') $sec[$key]['acoes'][$acao] = (int)($sec[$key]['acoes'][$acao] ?? 0) + 1;
        if ($sec[$key]['ultima_atividade'] === '-') $sec[$key]['ultima_atividade'] = trim($linha['acao'] . ' | ' . $linha['contexto']);
        if (count($sec[$key]['ultimas']) < 4) {
            $sec[$key]['ultimas'][] = ['data_hora' => (string)$linha['data_hora'], 'texto' => trim($linha['acao'] . ' | ' . $linha['contexto'])];
        }
        if (count($sec[$key]['linhas_setor']) < 3) {
            $sec[$key]['linhas_setor'][] = $linha;
        }
    }

    $lista = [];
    $setoresAtivos = 0;
    $custoTotal = 0.0;
    $receitaTotal = 0.0;
    $comprometidoTotal = 0.0;
    foreach ($setores as $key => $label) {
        if (!empty($sec[$key]['acoes'])) {
            arsort($sec[$key]['acoes']);
            $sec[$key]['top_acao'] = (string)array_key_first($sec[$key]['acoes']);
        }
        if (!empty($sec[$key]['fontes'])) arsort($sec[$key]['fontes']);
        if (!empty($sec[$key]['users_count'])) arsort($sec[$key]['users_count']);
        $sec[$key]['fontes_resumo'] = implode(', ', array_slice(array_keys($sec[$key]['fontes']), 0, 3));
        $sec[$key]['utilizadores_total'] = count($sec[$key]['users']);
        $sec[$key]['responsaveis_resumo'] = implode(', ', array_slice(array_keys($sec[$key]['users_count']), 0, 3));
        $sec[$key]['acoes_top'] = relatorios_top_metricas((array)$sec[$key]['acoes'], 3);
        $sec[$key]['responsaveis_top'] = relatorios_top_metricas((array)$sec[$key]['users_count'], 3);
        $sec[$key]['fontes_top'] = relatorios_top_metricas((array)$sec[$key]['fontes'], 3);
        $sec[$key]['saldo'] = round($sec[$key]['receita'] - $sec[$key]['custo'], 2);
        $sec[$key]['tem_financeiro'] = abs((float)$sec[$key]['custo']) > 0.0001
            || abs((float)$sec[$key]['receita']) > 0.0001
            || abs((float)$sec[$key]['comprometido']) > 0.0001;
        $resultadoSetor = relatorios_resultado_setor((float)$sec[$key]['saldo'], (bool)$sec[$key]['tem_financeiro']);
        $sec[$key]['resultado_label'] = $resultadoSetor['label'];
        $sec[$key]['resultado_class'] = $resultadoSetor['class'];
        $sec[$key]['classificacao'] = relatorios_classificacao_setor($sec[$key]);
        $sec[$key]['resumo_periodo'] = relatorios_montar_resumo_setor($sec[$key], (string)$periodo['periodo']);
        $sec[$key]['narrativa_pratica'] = relatorios_narrativa_execucao_setor($sec[$key], (string)$periodo['periodo']);
        $sec[$key]['recomendacao'] = relatorios_recomendacao_setor($sec[$key]);
        if ($key !== 'sistema' && ($sec[$key]['registos'] > 0 || abs($sec[$key]['custo']) > 0.0001 || abs($sec[$key]['receita']) > 0.0001 || abs($sec[$key]['comprometido']) > 0.0001)) $setoresAtivos++;
        if ($key !== 'sistema') {
            $custoTotal += $sec[$key]['custo'];
            $receitaTotal += $sec[$key]['receita'];
            $comprometidoTotal += $sec[$key]['comprometido'];
        }
        unset($sec[$key]['users'], $sec[$key]['acoes'], $sec[$key]['users_count']);
        $lista[] = $sec[$key];
    }

    $porCusto = array_values(array_filter($lista, static fn(array $s): bool => $s['key'] !== 'sistema' && $s['custo'] > 0));
    usort($porCusto, static fn(array $a, array $b): int => $b['custo'] <=> $a['custo']);
    $porReceita = array_values(array_filter($lista, static fn(array $s): bool => $s['key'] !== 'sistema' && $s['receita'] > 0));
    usort($porReceita, static fn(array $a, array $b): int => $b['receita'] <=> $a['receita']);
    $porCompromisso = array_values(array_filter($lista, static fn(array $s): bool => $s['key'] !== 'sistema' && $s['comprometido'] > 0));
    usort($porCompromisso, static fn(array $a, array $b): int => $b['comprometido'] <=> $a['comprometido']);
    $porSaldo = array_values(array_filter($lista, static fn(array $s): bool => $s['key'] !== 'sistema' && (abs($s['saldo']) > 0.0001 || $s['receita'] > 0 || $s['custo'] > 0)));
    usort($porSaldo, static fn(array $a, array $b): int => $b['saldo'] <=> $a['saldo']);
    $porOperacao = array_values(array_filter($lista, static fn(array $s): bool => $s['key'] !== 'sistema' && ($s['operacoes'] > 0 || $s['registos'] > 0)));
    usort($porOperacao, static fn(array $a, array $b): int => $b['operacoes'] <=> $a['operacoes']);

    $insights = [];
    if (!empty($porCusto)) $insights[] = $porCusto[0]['label'] . ' lidera o gasto do periodo com ' . relatorios_moeda((float)$porCusto[0]['custo']) . '.';
    if (!empty($porReceita)) $insights[] = $porReceita[0]['label'] . ' lidera a receita do periodo com ' . relatorios_moeda((float)$porReceita[0]['receita']) . '.';
    if (!empty($porSaldo)) $insights[] = 'Maior beneficio operacional: ' . $porSaldo[0]['label'] . ' com saldo de ' . relatorios_moeda((float)$porSaldo[0]['saldo']) . '.';
    if (!empty($porSaldo) && (float)$porSaldo[count($porSaldo) - 1]['saldo'] < 0) $insights[] = 'Maior ponto de atencao: ' . $porSaldo[count($porSaldo) - 1]['label'] . ' com saldo de ' . relatorios_moeda(abs((float)$porSaldo[count($porSaldo) - 1]['saldo'])) . '.';
    if (!empty($porCompromisso)) $insights[] = $porCompromisso[0]['label'] . ' tem o maior valor comprometido, em ' . relatorios_moeda((float)$porCompromisso[0]['comprometido']) . '.';
    $saldoTotal = round($receitaTotal - $custoTotal, 2);
    $insights[] = ($saldoTotal >= 0 ? 'O saldo agregado do periodo esta positivo em ' : 'O saldo agregado do periodo esta negativo em ') . relatorios_moeda(abs($saldoTotal)) . '.';
    if (($periodo['periodo'] ?? '') === 'mensal') {
        $litrosMes = (float)($consumo['geral']['litros_combustivel'] ?? 0);
        $custoConsumoMes = (float)($consumo['geral']['custo_combustivel'] ?? 0);
        $movConsumoMes = (int)($consumo['geral']['movimentos_consumo'] ?? 0);
        $valorConsumoMes = (float)($consumo['geral']['valor_consumo'] ?? 0);
        if ($litrosMes > 0 || $custoConsumoMes > 0) {
            $insights[] = 'Controlo mensal de combustivel: ' . number_format($litrosMes, 2, ',', '.') . ' L e custo de ' . relatorios_moeda($custoConsumoMes) . '.';
        }
        if ($movConsumoMes > 0 && $valorConsumoMes > 0) {
            $insights[] = 'Consumo mensal consolidado: ' . $movConsumoMes . ' movimentos controlados, total de ' . relatorios_moeda($valorConsumoMes) . '.';
        }
    }

    return [
        'titulo' => $periodo['titulo'],
        'inicio' => $periodo['inicio'],
        'fim' => $periodo['fim'],
        'periodo' => $periodo['periodo'],
        'total' => count($linhas),
        'operacoes' => $tipos['Operacao'],
        'acessos' => $tipos['Acesso'],
        'sessoes' => $tipos['Sessao'],
        'setores_ativos' => $setoresAtivos,
        'utilizadores' => count($utilizadores),
        'tipos' => $tipos,
        'setores' => $lista,
        'linhas' => $linhas,
        'custo_total' => round($custoTotal, 2),
        'receita_total' => round($receitaTotal, 2),
        'comprometido_total' => round($comprometidoTotal, 2),
        'saldo_total' => $saldoTotal,
        'consumo' => $consumo['geral'] ?? ['litros_combustivel' => 0.0, 'custo_combustivel' => 0.0, 'movimentos_consumo' => 0, 'valor_consumo' => 0.0],
        'maior_custo' => $porCusto[0] ?? null,
        'maior_receita' => $porReceita[0] ?? null,
        'maior_compromisso' => $porCompromisso[0] ?? null,
        'melhor_saldo' => $porSaldo[0] ?? null,
        'pior_saldo' => !empty($porSaldo) ? $porSaldo[count($porSaldo) - 1] : null,
        'destaques' => array_slice($porOperacao, 0, 4),
        'insights' => $insights,
    ];
}

function relatorios_assunto(array $cfg, array $periodo): string {
    $base = trim((string)($cfg['assunto_base'] ?? 'Relatorio Executivo Integrado'));
    if ($base === '') $base = 'Relatorio Executivo Integrado';
    return $base . ' | ' . strtoupper((string)$periodo['periodo']) . ' | ' . $periodo['inicio'] . ' a ' . $periodo['fim'];
}

function relatorios_asset_data_uri(string $nome): string {
    $bases = [
        dirname(__DIR__, 3) . '/assets/img/' . $nome,
        dirname(__DIR__, 3) . '/assets/' . $nome,
        dirname(__DIR__, 4) . '/assets/' . $nome,
    ];
    foreach ($bases as $path) {
        if (!is_file($path)) continue;
        $bin = @file_get_contents($path);
        if ($bin === false) continue;
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($path) : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }
    return '';
}

function relatorios_html(array $resumo, array $cfg, int $limite = 50): string {
    $logo = relatorios_asset_data_uri('logo-vilcon.png');
    $cert = relatorios_asset_data_uri('innocertificate.png');
    $setoresExplicados = ['documental', 'oficina', 'transporte', 'gestao_frota', 'aluguer', 'rh', 'logistica'];
    $setoresRelatorio = array_values(array_filter(
        (array)($resumo['setores'] ?? []),
        static fn(array $setor): bool => in_array((string)($setor['key'] ?? ''), $setoresExplicados, true)
    ));
    $setoresCaderno = array_values(array_filter($setoresRelatorio, static fn(array $setor): bool => (int)($setor['registos'] ?? 0) > 0 || (bool)($setor['tem_financeiro'] ?? false) || (int)($setor['consumo_movimentos'] ?? 0) > 0));
    $linhasExplicadas = array_values(array_filter(
        (array)($resumo['linhas'] ?? []),
        static fn(array $linha): bool => in_array((string)($linha['setor_key'] ?? ''), $setoresExplicados, true)
    ));
    $detalhes = array_slice($linhasExplicadas, 0, max(36, $limite));
    $rankingCusto = array_values(array_filter($setoresRelatorio, static fn(array $setor): bool => (float)($setor['custo'] ?? 0) > 0.0001));
    usort($rankingCusto, static fn(array $a, array $b): int => ((float)($b['custo'] ?? 0)) <=> ((float)($a['custo'] ?? 0)));
    $rankingReceita = array_values(array_filter($setoresRelatorio, static fn(array $setor): bool => (float)($setor['receita'] ?? 0) > 0.0001));
    usort($rankingReceita, static fn(array $a, array $b): int => ((float)($b['receita'] ?? 0)) <=> ((float)($a['receita'] ?? 0)));
    $rankingSaldo = array_values(array_filter($setoresRelatorio, static fn(array $setor): bool => abs((float)($setor['saldo'] ?? 0)) > 0.0001 || (float)($setor['receita'] ?? 0) > 0.0001 || (float)($setor['custo'] ?? 0) > 0.0001));
    usort($rankingSaldo, static fn(array $a, array $b): int => ((float)($b['saldo'] ?? 0)) <=> ((float)($a['saldo'] ?? 0)));
    $rankingOperacional = array_values(array_filter($setoresRelatorio, static fn(array $setor): bool => (int)($setor['operacoes'] ?? 0) > 0 || (int)($setor['registos'] ?? 0) > 0));
    usort($rankingOperacional, static fn(array $a, array $b): int => ((int)($b['operacoes'] ?? 0)) <=> ((int)($a['operacoes'] ?? 0)));
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= relatorios_h($resumo['titulo']) ?></title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: Arial, sans-serif; color: #18212f; margin: 0; }
        .wrap { width: 100%; }
        .head { border: 1px solid #d1d5db; border-radius: 14px; overflow: hidden; margin-bottom: 14px; }
        .strip { height: 12px; background: #f4b400; }
        .bodyh { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 12px 14px; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img.logo { width: 120px; height: auto; }
        .brand h1 { margin: 0; font-size: 20px; color: #0f172a; }
        .brand .hero-sub { margin-top: 6px; font-size: 11px; line-height: 1.6; color: #475569; }
        .meta { text-align: right; font-size: 11px; color: #374151; }
        .meta strong { display: block; color: #111; margin-bottom: 4px; }
        .meta img.cert { width: 84px; height: auto; display: block; margin: 0 0 8px auto; background: transparent; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #111; color: #f4b400; font-size: 10px; text-transform: uppercase; padding: 8px; border: 1px solid #111; text-align: left; letter-spacing: .4px; }
        td { padding: 8px; border: 1px solid #d1d5db; font-size: 10.8px; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 0 12px 0; }
        .cards td { width: 25%; border: 1px solid #d1d5db; border-radius: 12px; padding: 12px; }
        .k { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .v { margin-top: 6px; font-size: 22px; font-weight: 800; }
        .s { margin-top: 4px; font-size: 11px; color: #475569; }
        h2 { margin: 14px 0 8px 0; font-size: 14px; text-transform: uppercase; color: #0f172a; }
        .note { margin-top: 10px; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #f8fafc; font-size: 11px; color: #334155; line-height: 1.65; }
        .sector-note { margin-top: 8px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; font-size: 11px; color: #334155; line-height: 1.65; }
        ul { margin: 6px 0 0 18px; padding: 0; }
        li { margin: 4px 0; font-size: 11px; }
        .small { color: #64748b; font-size: 10px; display: block; margin-top: 4px; }
        .section-title { margin: 16px 0 8px 0; padding: 9px 12px; border-radius: 10px; background: #111; color: #f4b400; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; }
        .sub-title { margin: 10px 0 8px 0; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #0f172a; }
        .matrix td { font-size: 10.4px; }
        .badge { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge.positive { background: #ecfdf5; color: #166534; border: 1px solid #86efac; }
        .badge.negative { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
        .badge.neutral { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        .grid2 { width: 100%; border-collapse: separate; border-spacing: 10px 0; }
        .grid2 td { width: 50%; border: none; padding: 0; vertical-align: top; }
        .mini-box { border: 1px solid #d1d5db; border-radius: 10px; padding: 10px; background: #fff; min-height: 72px; }
        .sector-card { margin-top: 12px; border: 1px solid #d1d5db; border-radius: 14px; overflow: hidden; page-break-inside: avoid; }
        .sector-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; padding: 12px 14px; background: linear-gradient(180deg, #fff 0%, #f8fafc 100%); border-bottom: 1px solid #e5e7eb; }
        .sector-head h3 { margin: 0 0 4px 0; font-size: 15px; color: #0f172a; }
        .sector-head p { margin: 0; font-size: 10.5px; color: #64748b; line-height: 1.55; }
        .sector-body { padding: 12px 14px 14px 14px; }
        .sector-text { margin: 0 0 10px 0; font-size: 11px; line-height: 1.7; color: #334155; }
        .recommendation { margin-top: 10px; padding: 10px; border: 1px solid #ead79c; border-radius: 10px; background: #fff8e1; font-size: 11px; line-height: 1.65; color: #5b4a00; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <div class="strip"></div>
            <div class="bodyh">
                <div class="brand">
                    <?php if ($logo !== ''): ?><img class="logo" src="<?= relatorios_h($logo) ?>" alt="Vilcon"><?php endif; ?>
                    <div>
                        <h1>Relatorio Executivo Financeiro e Operacional</h1>
                        <div class="hero-sub">Documento consolidado para a Direcao Geral, com leitura de custos, receitas, consumo, compromissos e execucao pratica por departamento.</div>
                    </div>
                </div>
                <div class="meta">
                    <div>
                        <?php if ($cert !== ''): ?><img class="cert" src="<?= relatorios_h($cert) ?>" alt="INNOQ"><?php endif; ?>
                        <strong><?= relatorios_h($resumo['titulo']) ?></strong>
                        <span>Periodo: <?= relatorios_h($resumo['inicio']) ?> a <?= relatorios_h($resumo['fim']) ?></span><br>
                        <span>Destino: <?= relatorios_h($cfg['destinatarios'] !== '' ? $cfg['destinatarios'] : 'Nao definido') ?></span><br>
                        <span>Periodicidade: <?= relatorios_h(ucfirst(relatorios_periodo_texto((string)($resumo['periodo'] ?? 'semanal')))) ?></span><br>
                        <span>Emitido em: <?= relatorios_h(date('d/m/Y H:i')) ?></span><br>
                        <span>Registos analisados: <?= (int)($resumo['total'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title">1. Sumario Executivo Consolidado</div>
        <div class="note">Este documento cruza auditoria do sistema, atividade operacional, custos, receitas, compromissos e consumos associados aos departamentos. O foco e explicar, de forma executiva, o que foi feito na pratica em cada area, quem esteve mais presente e qual foi o resultado financeiro ou operacional do periodo.</div>

        <table class="cards">
            <tr>
                <td><div class="k">Custo total</div><div class="v"><?= relatorios_h(relatorios_moeda((float)$resumo['custo_total'])) ?></div><div class="s">Gasto registado no periodo</div></td>
                <td><div class="k">Receita total</div><div class="v"><?= relatorios_h(relatorios_moeda((float)$resumo['receita_total'])) ?></div><div class="s">Entradas financeiras registadas</div></td>
                <td><div class="k">Saldo</div><div class="v"><?= relatorios_h(relatorios_moeda((float)$resumo['saldo_total'])) ?></div><div class="s">Receita menos custo</div></td>
                <td><div class="k">Comprometido</div><div class="v"><?= relatorios_h(relatorios_moeda((float)$resumo['comprometido_total'])) ?></div><div class="s">Valores em requisicao/aprovacao</div></td>
            </tr>
            <tr>
                <td><div class="k">Operacoes</div><div class="v"><?= (int)($resumo['operacoes'] ?? 0) ?></div><div class="s">Operacoes de negocio ou administrativas com evidencia no sistema.</div></td>
                <td><div class="k">Acessos</div><div class="v"><?= (int)($resumo['acessos'] ?? 0) ?></div><div class="s">Consultas e utilizacao funcional dos modulos.</div></td>
                <td><div class="k">Utilizadores ativos</div><div class="v"><?= (int)($resumo['utilizadores'] ?? 0) ?></div><div class="s">Responsaveis com atividade registada no periodo.</div></td>
                <td><div class="k">Setores ativos</div><div class="v"><?= (int)($resumo['setores_ativos'] ?? 0) ?></div><div class="s">Departamentos com movimento operacional e/ou financeiro.</div></td>
            </tr>
        </table>

        <h2>Leituras executivas</h2>
        <div class="note">
            <ul>
                <?php foreach (($resumo['insights'] ?? []) as $insight): ?>
                    <li><?= relatorios_h((string)$insight) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="section-title">2. Matriz Geral de Desempenho por Setor</div>
        <div class="note">Nesta matriz entram apenas os setores-chave pedidos no relatorio executivo integrado. Em cada linha, o resumo operacional representa o essencial do Relatorio Geral do respetivo modulo, reunindo as atividades executadas nas abas internas e o respetivo impacto financeiro quando existir.</div>
        <table class="matrix">
            <thead>
                <tr>
                    <th>Setor</th>
                    <th>Resumo do Relatorio Geral</th>
                    <th>Responsaveis</th>
                    <th>Custo</th>
                    <th>Receita</th>
                    <th>Saldo</th>
                    <th>Resultado</th>
                    <th>Comprometido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($setoresRelatorio as $setor): ?>
                    <tr>
                        <td>
                            <?= relatorios_h($setor['label']) ?>
                            <?php if ($setor['fontes_resumo'] !== ''): ?><span class="small">Fontes: <?= relatorios_h($setor['fontes_resumo']) ?></span><?php endif; ?>
                            <?php if (($setor['top_acao'] ?? '-') !== '-'): ?><span class="small">Acao dominante: <?= relatorios_h((string)$setor['top_acao']) ?></span><?php endif; ?>
                            <span class="small">Classificacao: <?= relatorios_h((string)($setor['classificacao'] ?? 'Sem movimento')) ?></span>
                        </td>
                        <td><?= relatorios_h((string)($setor['resumo_periodo'] ?? 'Sem resumo no periodo.')) ?></td>
                        <td><?= relatorios_h((string)($setor['responsaveis_resumo'] !== '' ? $setor['responsaveis_resumo'] : '-')) ?></td>
                        <td><?= relatorios_h(relatorios_exibir_valor_setor((float)$setor['custo'], (bool)($setor['tem_financeiro'] ?? false))) ?></td>
                        <td><?= relatorios_h(relatorios_exibir_valor_setor((float)$setor['receita'], (bool)($setor['tem_financeiro'] ?? false))) ?></td>
                        <td><?= relatorios_h(relatorios_exibir_valor_setor((float)$setor['saldo'], (bool)($setor['tem_financeiro'] ?? false))) ?></td>
                        <td><span class="badge <?= relatorios_h((string)($setor['resultado_class'] ?? 'neutral')) ?>"><?= relatorios_h((string)$setor['resultado_label']) ?></span></td>
                        <td><?= relatorios_h(relatorios_exibir_valor_setor((float)$setor['comprometido'], (bool)($setor['tem_financeiro'] ?? false))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-title">3. Rankings, Peso Financeiro e Leitura Operacional</div>
        <table class="grid2">
            <tr>
                <td>
                    <div class="sub-title">Ranking financeiro</div>
                    <table>
                        <thead><tr><th>Indicador</th><th>Setor</th><th>Valor</th><th>Peso</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Maior custo</td>
                                <td><?= relatorios_h((string)(isset($rankingCusto[0]) ? $rankingCusto[0]['label'] : '-')) ?></td>
                                <td><?= relatorios_h(isset($rankingCusto[0]) ? relatorios_moeda((float)$rankingCusto[0]['custo']) : '-') ?></td>
                                <td><?= relatorios_h(isset($rankingCusto[0]) ? relatorios_percentual((float)$rankingCusto[0]['custo'], (float)($resumo['custo_total'] ?? 0)) : '-') ?></td>
                            </tr>
                            <tr>
                                <td>Maior receita</td>
                                <td><?= relatorios_h((string)(isset($rankingReceita[0]) ? $rankingReceita[0]['label'] : '-')) ?></td>
                                <td><?= relatorios_h(isset($rankingReceita[0]) ? relatorios_moeda((float)$rankingReceita[0]['receita']) : '-') ?></td>
                                <td><?= relatorios_h(isset($rankingReceita[0]) ? relatorios_percentual((float)$rankingReceita[0]['receita'], (float)($resumo['receita_total'] ?? 0)) : '-') ?></td>
                            </tr>
                            <tr>
                                <td>Melhor saldo</td>
                                <td><?= relatorios_h((string)(isset($rankingSaldo[0]) ? $rankingSaldo[0]['label'] : '-')) ?></td>
                                <td><?= relatorios_h(isset($rankingSaldo[0]) ? relatorios_moeda((float)$rankingSaldo[0]['saldo']) : '-') ?></td>
                                <td><?= relatorios_h(isset($rankingSaldo[0]) ? relatorios_percentual(max(0, (float)$rankingSaldo[0]['saldo']), max(0.0001, (float)($resumo['receita_total'] ?? 0))) : '-') ?></td>
                            </tr>
                            <tr>
                                <td>Maior comprometido</td>
                                <td><?= relatorios_h((string)(isset($resumo['maior_compromisso']) ? $resumo['maior_compromisso']['label'] : '-')) ?></td>
                                <td><?= relatorios_h(isset($resumo['maior_compromisso']) ? relatorios_moeda((float)$resumo['maior_compromisso']['comprometido']) : '-') ?></td>
                                <td><?= relatorios_h(isset($resumo['maior_compromisso']) ? relatorios_percentual((float)$resumo['maior_compromisso']['comprometido'], (float)($resumo['comprometido_total'] ?? 0)) : '-') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <td>
                    <div class="sub-title">Ranking operacional</div>
                    <table>
                        <thead><tr><th>Pos.</th><th>Setor</th><th>Operacoes</th><th>Responsaveis</th></tr></thead>
                        <tbody>
                            <?php if (empty($rankingOperacional)): ?>
                                <tr><td colspan="4">Sem atividade operacional relevante no periodo.</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($rankingOperacional, 0, 6) as $idx => $setor): ?>
                                    <tr>
                                        <td><?= (int)($idx + 1) ?></td>
                                        <td><?= relatorios_h((string)$setor['label']) ?></td>
                                        <td><?= (int)($setor['operacoes'] ?? 0) ?></td>
                                        <td><?= relatorios_h((string)(($setor['responsaveis_resumo'] ?? '') !== '' ? $setor['responsaveis_resumo'] : '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <div class="sub-title">Resumo <?= relatorios_h(relatorios_periodo_texto((string)($resumo['periodo'] ?? 'semanal'))) ?> por setor</div>
        <?php foreach ($setoresRelatorio as $setor): ?>
            <div class="sector-note">
                <strong><?= relatorios_h((string)$setor['label']) ?>:</strong>
                <?= relatorios_h((string)($setor['resumo_periodo'] ?? 'Sem resumo no periodo.')) ?>
            </div>
        <?php endforeach; ?>

        <?php if (($resumo['periodo'] ?? '') === 'mensal'): ?>
            <h2>Controlo mensal de consumo</h2>
            <table class="cards">
                <tr>
                    <td><div class="k">Litros combustivel</div><div class="v"><?= relatorios_h(number_format((float)($resumo['consumo']['litros_combustivel'] ?? 0), 2, ',', '.') . ' L') ?></div><div class="s">Consumo mensal controlado</div></td>
                    <td><div class="k">Custo combustivel</div><div class="v"><?= relatorios_h(relatorios_moeda((float)($resumo['consumo']['custo_combustivel'] ?? 0))) ?></div><div class="s">Impacto financeiro do combustivel</div></td>
                    <td><div class="k">Movimentos consumo</div><div class="v"><?= (int)($resumo['consumo']['movimentos_consumo'] ?? 0) ?></div><div class="s">Movimentos controlados no mes</div></td>
                    <td><div class="k">Valor consumo</div><div class="v"><?= relatorios_h(relatorios_moeda((float)($resumo['consumo']['valor_consumo'] ?? 0))) ?></div><div class="s">Consumo consolidado no periodo</div></td>
                </tr>
            </table>
        <?php endif; ?>

        <div class="section-title">4. Caderno Analitico por Departamento</div>
        <?php foreach ($setoresCaderno as $setor): ?>
            <div class="sector-card">
                <div class="sector-head">
                    <div>
                        <h3><?= relatorios_h((string)$setor['label']) ?></h3>
                        <p>Classificacao: <?= relatorios_h((string)($setor['classificacao'] ?? 'Sem movimento')) ?> | Responsaveis: <?= relatorios_h((string)(($setor['responsaveis_resumo'] ?? '') !== '' ? $setor['responsaveis_resumo'] : '-')) ?> | Ultima atividade: <?= relatorios_h((string)($setor['ultima_atividade'] ?? '-')) ?></p>
                    </div>
                    <div><span class="badge <?= relatorios_h((string)($setor['resultado_class'] ?? 'neutral')) ?>"><?= relatorios_h((string)($setor['resultado_label'] ?? 'Equilibrado')) ?></span></div>
                </div>
                <div class="sector-body">
                    <p class="sector-text"><strong>Leitura pratica:</strong> <?= relatorios_h((string)($setor['narrativa_pratica'] ?? $setor['resumo_periodo'] ?? 'Sem leitura disponivel.')) ?></p>

                    <table>
                        <thead><tr><th>Indicador</th><th>Valor</th><th>Indicador</th><th>Valor</th></tr></thead>
                        <tbody>
                            <tr><td>Registos totais</td><td><?= (int)($setor['registos'] ?? 0) ?></td><td>Operacoes</td><td><?= (int)($setor['operacoes'] ?? 0) ?></td></tr>
                            <tr><td>Acessos</td><td><?= (int)($setor['acessos'] ?? 0) ?></td><td>Responsaveis ativos</td><td><?= (int)($setor['utilizadores_total'] ?? 0) ?></td></tr>
                            <tr><td>Custo</td><td><?= relatorios_h(relatorios_exibir_valor_setor((float)($setor['custo'] ?? 0), (bool)($setor['tem_financeiro'] ?? false))) ?></td><td>Receita</td><td><?= relatorios_h(relatorios_exibir_valor_setor((float)($setor['receita'] ?? 0), (bool)($setor['tem_financeiro'] ?? false))) ?></td></tr>
                            <tr><td>Saldo</td><td><?= relatorios_h(relatorios_exibir_valor_setor((float)($setor['saldo'] ?? 0), (bool)($setor['tem_financeiro'] ?? false))) ?></td><td>Comprometido</td><td><?= relatorios_h(relatorios_exibir_valor_setor((float)($setor['comprometido'] ?? 0), (bool)($setor['tem_financeiro'] ?? false))) ?></td></tr>
                            <tr><td>Consumo litros</td><td><?= relatorios_h((float)($setor['consumo_litros'] ?? 0) > 0 ? number_format((float)$setor['consumo_litros'], 2, ',', '.') . ' L' : '-') ?></td><td>Valor consumo</td><td><?= relatorios_h((float)($setor['consumo_valor'] ?? 0) > 0 ? relatorios_moeda((float)$setor['consumo_valor']) : '-') ?></td></tr>
                        </tbody>
                    </table>

                    <table class="grid2" style="margin-top:10px;">
                        <tr>
                            <td>
                                <div class="sub-title">Principais acoes executadas</div>
                                <div class="mini-box">
                                    <?php if (empty($setor['acoes_top'])): ?>
                                        Sem acoes dominantes identificadas para este setor no periodo.
                                    <?php else: ?>
                                        <ul>
                                            <?php foreach ((array)$setor['acoes_top'] as $acao): ?>
                                                <li><strong><?= relatorios_h((string)$acao['nome']) ?></strong> - <?= (int)round((float)$acao['valor']) ?> ocorrencias</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="sub-title">Base financeira e evidencias</div>
                                <div class="mini-box">
                                    <?php if (empty($setor['fontes_top'])): ?>
                                        Sem fontes financeiras relevantes ou sem impacto monetario apurado.
                                    <?php else: ?>
                                        <ul>
                                            <?php foreach ((array)$setor['fontes_top'] as $fonte): ?>
                                                <li><strong><?= relatorios_h((string)$fonte['nome']) ?></strong> - <?= relatorios_h(relatorios_moeda((float)$fonte['valor'])) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <table class="grid2" style="margin-top:10px;">
                        <tr>
                            <td>
                                <div class="sub-title">Responsaveis com mais participacao</div>
                                <div class="mini-box">
                                    <?php if (empty($setor['responsaveis_top'])): ?>
                                        Sem distribuicao suficiente para leitura comparativa.
                                    <?php else: ?>
                                        <ul>
                                            <?php foreach ((array)$setor['responsaveis_top'] as $resp): ?>
                                                <li><strong><?= relatorios_h((string)$resp['nome']) ?></strong> - <?= (int)round((float)$resp['valor']) ?> interacoes</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="sub-title">O que foi feito no periodo</div>
                                <div class="mini-box"><?= relatorios_h((string)($setor['resumo_periodo'] ?? 'Sem resumo do periodo.')) ?></div>
                            </td>
                        </tr>
                    </table>

                    <div class="sub-title">Registos recentes do departamento</div>
                    <table>
                        <thead><tr><th>Data/Hora</th><th>Tipo</th><th>Utilizador</th><th>Acao</th><th>Contexto</th></tr></thead>
                        <tbody>
                            <?php if (empty($setor['linhas_setor'])): ?>
                                <tr><td colspan="5">Sem registos detalhados do setor neste periodo.</td></tr>
                            <?php else: ?>
                                <?php foreach ((array)$setor['linhas_setor'] as $linha): ?>
                                    <tr>
                                        <td><?= relatorios_h((string)$linha['data_hora']) ?></td>
                                        <td><?= relatorios_h((string)$linha['tipo']) ?></td>
                                        <td><?= relatorios_h((string)$linha['usuario_nome']) ?></td>
                                        <td><?= relatorios_h((string)$linha['acao']) ?></td>
                                        <td><?= relatorios_h((string)$linha['contexto']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="recommendation"><strong>Recomendacao executiva:</strong> <?= relatorios_h((string)($setor['recomendacao'] ?? 'Sem recomendacao adicional.')) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="section-title">5. Registo Detalhado do Periodo</div>
        <div class="note">Esta seccao apresenta a trilha detalhada que sustenta a leitura executiva do documento. Ela permite verificar rapidamente as principais operacoes, acessos e evidencias por utilizador e por setor.</div>
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Setor</th>
                    <th>Tipo</th>
                    <th>Utilizador</th>
                    <th>Acao</th>
                    <th>Contexto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$detalhes): ?>
                    <tr><td colspan="6">Sem registos no periodo.</td></tr>
                <?php else: ?>
                    <?php foreach ($detalhes as $linha): ?>
                        <tr>
                            <td><?= relatorios_h((string)$linha['data_hora']) ?></td>
                            <td><?= relatorios_h((string)$linha['setor_label']) ?></td>
                            <td><?= relatorios_h((string)$linha['tipo']) ?></td>
                            <td><?= relatorios_h((string)$linha['usuario_nome']) ?></td>
                            <td><?= relatorios_h((string)$linha['acao']) ?></td>
                            <td><?= relatorios_h((string)$linha['contexto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="note"><strong>Observacao metodologica:</strong> este relatorio foi gerado automaticamente a partir da auditoria do sistema, registos financeiros, consumo controlado e evidencias operacionais por setor. Onde nao existe custo, a leitura foi mantida em chave operacional para mostrar com clareza o que foi executado na pratica por cada departamento.</div>
    </div>
</body>
</html>
<?php
    return (string)ob_get_clean();
}

function relatorios_enviar(array $destinatarios, string $assunto, string $html): array {
    if (!$destinatarios) return [false, 'Configure pelo menos um email valido para o director geral.'];
    if (!function_exists('mail')) return [false, 'A funcao mail() nao esta disponivel neste servidor.'];
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Vilcon Relatorios <no-reply@vilcon.local>',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    $ok = @mail(implode(',', $destinatarios), $assunto, $html, implode("\r\n", $headers));
    return $ok ? [true, 'Email enviado para: ' . implode(', ', $destinatarios)] : [false, 'Falha ao enviar email. Verifique a configuracao SMTP/mail() do servidor.'];
}

function relatorios_log_envio(PDO $pdo, string $modo, array $periodo, string $destinatarios, string $assunto, string $status, string $mensagem, int $totalRegistos): void {
    $st = $pdo->prepare("INSERT INTO relatorios_exec_envios
        (modo, periodicidade, referencia_inicio, referencia_fim, destinatarios, destinatarios_hash, assunto, status, mensagem, total_registos, enviado_em)
        VALUES (:m, :p, :i, :f, :d, :h, :a, :s, :msg, :t, NOW())");
    $st->execute([
        ':m' => $modo,
        ':p' => $periodo['periodo'],
        ':i' => $periodo['inicio'],
        ':f' => $periodo['fim'],
        ':d' => $destinatarios,
        ':h' => hash('sha256', strtolower($destinatarios)),
        ':a' => $assunto,
        ':s' => $status,
        ':msg' => $mensagem,
        ':t' => $totalRegistos,
    ]);
}

function relatorios_auto_enviado(PDO $pdo, array $periodo, string $destinatarios): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM relatorios_exec_envios
                         WHERE modo = 'automatico'
                           AND status = 'enviado'
                           AND periodicidade = :p
                           AND referencia_inicio = :i
                           AND referencia_fim = :f
                           AND destinatarios_hash = :h");
    $st->execute([
        ':p' => $periodo['periodo'],
        ':i' => $periodo['inicio'],
        ':f' => $periodo['fim'],
        ':h' => hash('sha256', strtolower($destinatarios)),
    ]);
    return (int)$st->fetchColumn() > 0;
}

function relatorios_hist_envios(PDO $pdo, int $limite = 10): array {
    $st = $pdo->prepare("SELECT modo, periodicidade, referencia_inicio, referencia_fim, destinatarios, assunto, status, mensagem, total_registos, enviado_em
                         FROM relatorios_exec_envios
                         ORDER BY id DESC
                         LIMIT :lim");
    $st->bindValue(':lim', $limite, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
