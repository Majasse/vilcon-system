<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

/* =========================
   CONTROLES DO MÃ“DULO OFICINA
========================= */
$tab = $_GET['tab'] ?? 'oficina';
$view = $_GET['view'] ?? 'ordens_servico';
$mode = $_GET['mode'] ?? 'list';

$proximo_os = "OS-OF-" . date('Y') . "-0001";

$ordens_servico = [];
$pedidos_reparacao = [];
$manutencoes = [];
$avarias = [];
$relatorio_resumo = [];
$relatorio_historico = [];
$relatorio_tendencia_mensal = [];
$relatorio_top_avarias = [];
$relatorio_filtros = [
    'matricula' => '',
    'data_inicio' => '',
    'data_fim' => '',
];

$erro_os = null;
$erro_pedidos = null;
$erro_manutencao = null;
$erro_avarias = null;
$erro_relatorios = null;

$msg_os = null;
$msg_pedidos = null;
$msg_manutencao = null;
$msg_avarias = null;
$msg_assiduidade = null;

$erro_assiduidade = null;
$data_assiduidade = trim((string)($_GET['data_assiduidade'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_assiduidade)) {
    $data_assiduidade = date('Y-m-d');
}
$colaboradores_oficina = [];
$presencas_oficina = [];
$presencas_por_colaborador = [];

function normalizarStatusPedido($valor) {
    $v = strtolower(trim((string)$valor));
    if ($v === '' || $v === 'pendente' || $v === 'aberto') return 'pendente';
    if ($v === 'aceito' || $v === 'aceite') return 'aceito';
    if ($v === 'em andamento' || $v === 'em progresso' || $v === 'andamento') return 'em_andamento';
    if ($v === 'resolvido' || $v === 'fechado' || $v === 'concluido') return 'resolvido';
    if ($v === 'aguardando logistica externa' || $v === 'encaminhado logistica' || $v === 'externo') return 'logistica_externa';
    return 'pendente';
}

function statusPedidoLabel($statusNormalizado) {
    if ($statusNormalizado === 'aceito') return 'Aceito';
    if ($statusNormalizado === 'em_andamento') return 'Em andamento';
    if ($statusNormalizado === 'resolvido') return 'Resolvido';
    if ($statusNormalizado === 'logistica_externa') return 'Aguardando Logistica Externa';
    return 'Pendente';
}

function encontrarPrimeiraColuna(array $colunas, array $candidatas) {
    foreach ($candidatas as $nome) {
        if (isset($colunas[$nome])) {
            return $nome;
        }
    }
    return null;
}
function garantirEstruturasOficina(PDO $pdo): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oficina_pedidos_reparacao (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ativo_matricula VARCHAR(50) NOT NULL,
                tipo_equipamento VARCHAR(150) NOT NULL,
                descricao_avaria TEXT NOT NULL,
                localizacao VARCHAR(150) NULL,
                solicitante VARCHAR(150) NULL,
                data_pedido DATE NOT NULL,
                prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',
                status VARCHAR(30) NOT NULL DEFAULT 'Pendente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oficina_manutencoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ativo_matricula VARCHAR(50) NOT NULL,
                tipo_equipamento VARCHAR(150) NOT NULL,
                tipo_manutencao VARCHAR(80) NOT NULL,
                descricao_servico TEXT NULL,
                solicitante VARCHAR(150) NULL,
                data_manutencao DATE NOT NULL,
                prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',
                status VARCHAR(30) NOT NULL DEFAULT 'Pendente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oficina_ordens_servico (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo_os VARCHAR(40) UNIQUE,
                origem_tipo VARCHAR(40) NOT NULL DEFAULT 'MANUAL',
                origem_id INT NULL,
                ativo_matricula VARCHAR(50) NOT NULL,
                tipo_equipamento VARCHAR(150) NOT NULL,
                descricao_servico TEXT NOT NULL,
                data_abertura DATETIME NOT NULL,
                prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',
                status_os VARCHAR(30) NOT NULL DEFAULT 'Aberto',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oficina_historico_avarias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ativo_matricula VARCHAR(50) NOT NULL,
                tipo_equipamento VARCHAR(150) NOT NULL,
                tipo_registo VARCHAR(30) NOT NULL,
                descricao TEXT NOT NULL,
                data_evento DATE NOT NULL,
                origem_tipo VARCHAR(40) NULL,
                origem_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oficina_presencas_rh (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data_presenca DATE NOT NULL,
                pessoal_id INT NOT NULL,
                status_presenca ENUM('Presente','Atraso','Falta','Dispensa') NOT NULL DEFAULT 'Presente',
                observacoes VARCHAR(255) NULL,
                enviado_rh TINYINT(1) NOT NULL DEFAULT 0,
                enviado_em DATETIME NULL,
                criado_por INT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_data (data_presenca),
                INDEX idx_pessoal (pessoal_id),
                INDEX idx_enviado (enviado_rh)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (PDOException $e) {
        throw new RuntimeException('Nao foi possivel preparar as tabelas da oficina: ' . $e->getMessage());
    }

    // Compatibilidade com bases antigas: adiciona colunas faltantes sem recriar tabelas.
    $colunaExiste = static function (PDO $pdo, string $tabela, string $coluna): bool {
        $sql = "
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabela
              AND COLUMN_NAME = :coluna
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tabela' => $tabela, 'coluna' => $coluna]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $garantirColuna = static function (PDO $pdo, string $tabela, string $coluna, string $definicao) use ($colunaExiste): void {
        if (!$colunaExiste($pdo, $tabela, $coluna)) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
        }
    };

    try {
        $garantirColuna($pdo, 'oficina_ordens_servico', 'codigo_os', 'VARCHAR(40) NULL');
        $garantirColuna($pdo, 'oficina_ordens_servico', 'origem_tipo', "VARCHAR(40) NOT NULL DEFAULT 'MANUAL'");
        $garantirColuna($pdo, 'oficina_ordens_servico', 'origem_id', 'INT NULL');
        $garantirColuna($pdo, 'oficina_ordens_servico', 'ativo_matricula', "VARCHAR(50) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_ordens_servico', 'tipo_equipamento', "VARCHAR(150) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_ordens_servico', 'descricao_servico', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_ordens_servico', 'data_abertura', 'DATETIME NULL');
        $garantirColuna($pdo, 'oficina_ordens_servico', 'prioridade', "VARCHAR(20) NOT NULL DEFAULT 'Normal'");
        $garantirColuna($pdo, 'oficina_ordens_servico', 'status_os', "VARCHAR(30) NOT NULL DEFAULT 'Aberto'");

        $garantirColuna($pdo, 'oficina_historico_avarias', 'ativo_matricula', "VARCHAR(50) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_historico_avarias', 'tipo_equipamento', "VARCHAR(150) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_historico_avarias', 'tipo_registo', "VARCHAR(30) NOT NULL DEFAULT 'AVARIA'");
        $garantirColuna($pdo, 'oficina_historico_avarias', 'descricao', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_historico_avarias', 'data_evento', 'DATE NULL');
        $garantirColuna($pdo, 'oficina_historico_avarias', 'origem_tipo', 'VARCHAR(40) NULL');
        $garantirColuna($pdo, 'oficina_historico_avarias', 'origem_id', 'INT NULL');
    } catch (PDOException $e) {
        throw new RuntimeException('Nao foi possivel atualizar a estrutura legada da oficina.');
    }
}

function criarOrdemServicoAutomatica(PDO $pdo, array $dados): array {
    $stmt = $pdo->prepare("
        INSERT INTO oficina_ordens_servico
            (origem_tipo, origem_id, ativo_matricula, tipo_equipamento, descricao_servico, data_abertura, prioridade, status_os)
        VALUES
            (:origem_tipo, :origem_id, :ativo_matricula, :tipo_equipamento, :descricao_servico, :data_abertura, :prioridade, :status_os)
    ");
    $stmt->execute([
        'origem_tipo' => $dados['origem_tipo'],
        'origem_id' => $dados['origem_id'],
        'ativo_matricula' => $dados['ativo_matricula'],
        'tipo_equipamento' => $dados['tipo_equipamento'],
        'descricao_servico' => $dados['descricao_servico'],
        'data_abertura' => $dados['data_abertura'],
        'prioridade' => $dados['prioridade'] ?? 'Normal',
        'status_os' => $dados['status_os'] ?? 'Aberto',
    ]);

    $id = (int)$pdo->lastInsertId();
    $codigo = sprintf('OS-OF-%s-%04d', date('Y'), $id);
    $up = $pdo->prepare("UPDATE oficina_ordens_servico SET codigo_os = :codigo WHERE id = :id");
    $up->execute(['codigo' => $codigo, 'id' => $id]);
    return ['id' => $id, 'codigo' => $codigo];
}

function statusOsPorPedido(string $statusPedido): string {
    $status = normalizarStatusPedido($statusPedido);
    if ($status === 'resolvido') return 'Fechado';
    if ($status === 'aceito' || $status === 'em_andamento') return 'Em andamento';
    return 'Aberto';
}

function sincronizarPedidosReparacaoExistentes(PDO $pdo): void {
    $stmt = $pdo->query("
        SELECT
            p.id,
            p.ativo_matricula,
            p.tipo_equipamento,
            p.descricao_avaria,
            p.data_pedido,
            p.prioridade,
            p.status,
            (
                SELECT COUNT(*)
                FROM oficina_ordens_servico os
                WHERE os.origem_tipo = 'PEDIDO_REPARACAO' AND os.origem_id = p.id
            ) AS total_os,
            (
                SELECT COUNT(*)
                FROM oficina_historico_avarias h
                WHERE h.origem_tipo = 'PEDIDO_REPARACAO' AND h.origem_id = p.id AND h.tipo_registo = 'AVARIA'
            ) AS total_hist
        FROM oficina_pedidos_reparacao p
        ORDER BY p.id ASC
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($pedidos) === 0) {
        return;
    }

    $stmtHist = $pdo->prepare("
        INSERT INTO oficina_historico_avarias
            (ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo, origem_id)
        VALUES
            (:ativo_matricula, :tipo_equipamento, 'AVARIA', :descricao, :data_evento, 'PEDIDO_REPARACAO', :origem_id)
    ");

    $pdo->beginTransaction();
    try {
        foreach ($pedidos as $p) {
            $totalOs = (int)($p['total_os'] ?? 0);
            $totalHist = (int)($p['total_hist'] ?? 0);

            if ($totalOs === 0) {
                criarOrdemServicoAutomatica($pdo, [
                    'origem_tipo' => 'PEDIDO_REPARACAO',
                    'origem_id' => (int)$p['id'],
                    'ativo_matricula' => (string)$p['ativo_matricula'],
                    'tipo_equipamento' => (string)$p['tipo_equipamento'],
                    'descricao_servico' => (string)$p['descricao_avaria'],
                    'data_abertura' => (string)$p['data_pedido'] . ' 08:00:00',
                    'prioridade' => (string)($p['prioridade'] ?? 'Normal'),
                    'status_os' => statusOsPorPedido((string)($p['status'] ?? 'Pendente')),
                ]);
            }

            if ($totalHist === 0) {
                $stmtHist->execute([
                    'ativo_matricula' => (string)$p['ativo_matricula'],
                    'tipo_equipamento' => (string)$p['tipo_equipamento'],
                    'descricao' => (string)$p['descricao_avaria'],
                    'data_evento' => (string)$p['data_pedido'],
                    'origem_id' => (int)$p['id'],
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

try {
    garantirEstruturasOficina($pdo);
    sincronizarPedidosReparacaoExistentes($pdo);
} catch (Throwable $e) {
    $erro_os = $e->getMessage();
    $erro_pedidos = $e->getMessage();
    $erro_manutencao = $e->getMessage();
    $erro_avarias = $e->getMessage();
    $erro_relatorios = $e->getMessage();
    $erro_assiduidade = $e->getMessage();
}

if ($view === 'ordens_servico' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_os_manual') {
    try {
        $matricula = trim((string)($_POST['matricula'] ?? ''));
        $equipamento = trim((string)($_POST['equipamento'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
        $dataEntrada = trim((string)($_POST['data_entrada'] ?? ''));

        if ($matricula === '' || $equipamento === '' || $descricao === '' || $dataEntrada === '') {
            throw new RuntimeException('Preencha os campos obrigatorios para abrir a OS.');
        }

        criarOrdemServicoAutomatica($pdo, [
            'origem_tipo' => 'MANUAL',
            'origem_id' => null,
            'ativo_matricula' => $matricula,
            'tipo_equipamento' => $equipamento,
            'descricao_servico' => $descricao,
            'data_abertura' => str_replace('T', ' ', $dataEntrada) . ':00',
            'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
            'status_os' => 'Aberto',
        ]);
        header("Location: ?tab={$tab}&view=ordens_servico&mode=list&saved_os=1");
        exit;
    } catch (Throwable $e) {
        $erro_os = "Nao foi possivel criar a ordem de servico: " . $e->getMessage();
    }
}

if ($view === 'pedidos_reparacao') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
        $acao = trim((string)$_POST['acao']);

        try {
            if ($acao === 'criar_pedido') {
                $ativo_matricula = trim((string)($_POST['ativo_matricula'] ?? ''));
                $tipo_equipamento = trim((string)($_POST['tipo_equipamento'] ?? ''));
                $descricao_avaria = trim((string)($_POST['descricao_avaria'] ?? ''));
                $localizacao = trim((string)($_POST['localizacao'] ?? ''));
                $solicitante = trim((string)($_POST['solicitante'] ?? ''));
                $data_pedido = trim((string)($_POST['data_pedido'] ?? ''));
                $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
                $status = trim((string)($_POST['status'] ?? 'Pendente'));

                if ($ativo_matricula === '' || $tipo_equipamento === '' || $descricao_avaria === '' || $data_pedido === '') {
                    throw new RuntimeException('Preencha os campos obrigatorios do pedido de reparacao.');
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO oficina_pedidos_reparacao
                        (ativo_matricula, tipo_equipamento, descricao_avaria, localizacao, solicitante, data_pedido, prioridade, status)
                    VALUES
                        (:ativo_matricula, :tipo_equipamento, :descricao_avaria, :localizacao, :solicitante, :data_pedido, :prioridade, :status)
                ");
                $stmt->execute([
                    'ativo_matricula' => $ativo_matricula,
                    'tipo_equipamento' => $tipo_equipamento,
                    'descricao_avaria' => $descricao_avaria,
                    'localizacao' => $localizacao !== '' ? $localizacao : null,
                    'solicitante' => $solicitante !== '' ? $solicitante : null,
                    'data_pedido' => $data_pedido,
                    'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                    'status' => $status !== '' ? $status : 'Pendente',
                ]);
                $pedidoId = (int)$pdo->lastInsertId();

                $novaOs = criarOrdemServicoAutomatica($pdo, [
                    'origem_tipo' => 'PEDIDO_REPARACAO',
                    'origem_id' => $pedidoId,
                    'ativo_matricula' => $ativo_matricula,
                    'tipo_equipamento' => $tipo_equipamento,
                    'descricao_servico' => $descricao_avaria,
                    'data_abertura' => $data_pedido . ' 08:00:00',
                    'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                    'status_os' => 'Aberto',
                ]);

                $stmtHist = $pdo->prepare("
                    INSERT INTO oficina_historico_avarias
                        (ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo, origem_id)
                    VALUES
                        (:ativo_matricula, :tipo_equipamento, 'AVARIA', :descricao, :data_evento, 'PEDIDO_REPARACAO', :origem_id)
                ");
                $stmtHist->execute([
                    'ativo_matricula' => $ativo_matricula,
                    'tipo_equipamento' => $tipo_equipamento,
                    'descricao' => $descricao_avaria,
                    'data_evento' => $data_pedido,
                    'origem_id' => $pedidoId,
                ]);
                $pdo->commit();

                if (function_exists('registrarAuditoria')) {
                    registrarAuditoria($pdo, 'Inseriu pedido de reparacao', 'oficina_pedidos_reparacao');
                }

                header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=list&saved=1&os={$novaOs['codigo']}");
                exit;
            }

            if (in_array($acao, ['aceitar', 'andamento', 'resolver'], true) && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                if ($acao === 'aceitar') {
                    $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Aceito' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Em andamento' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $id]);
                    $msg_pedidos = "Pedido #{$id} aceito.";
                } elseif ($acao === 'andamento') {
                    $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Em andamento' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Em andamento' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $id]);
                    $msg_pedidos = "Pedido #{$id} colocado em andamento.";
                } else {
                    $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Resolvido' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Fechado' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $id]);
                    $msg_pedidos = "Pedido #{$id} marcado como resolvido.";
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro_pedidos = "Nao foi possivel processar o pedido de reparacao: " . $e->getMessage();
        }
    }

    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $osCriada = trim((string)($_GET['os'] ?? ''));
        $msg_pedidos = $osCriada !== ''
            ? "Pedido de reparacao guardado com sucesso. Ordem de servico {$osCriada} criada automaticamente."
            : "Pedido de reparacao guardado com sucesso.";
    }

    try {
        $stmt = $pdo->query("
            SELECT id, ativo_matricula, tipo_equipamento, descricao_avaria, localizacao, solicitante, data_pedido, prioridade, status
            FROM oficina_pedidos_reparacao
            ORDER BY id DESC
        ");
        $pedidos_reparacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_pedidos = "Nao foi possivel carregar pedidos de reparacao.";
    }
}

if ($view === 'manutencao') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_manutencao') {
        try {
            $ativo_matricula = trim((string)($_POST['ativo_matricula'] ?? ''));
            $tipo_equipamento = trim((string)($_POST['tipo_equipamento'] ?? ''));
            $tipo_manutencao = trim((string)($_POST['tipo_manutencao'] ?? ''));
            $descricao_servico = trim((string)($_POST['descricao_servico'] ?? ''));
            $solicitante = trim((string)($_POST['solicitante'] ?? ''));
            $data_manutencao = trim((string)($_POST['data_manutencao'] ?? ''));
            $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
            $status = trim((string)($_POST['status'] ?? 'Pendente'));

            if ($ativo_matricula === '' || $tipo_equipamento === '' || $tipo_manutencao === '' || $data_manutencao === '') {
                throw new RuntimeException('Preencha os campos obrigatorios da manutencao.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO oficina_manutencoes
                    (ativo_matricula, tipo_equipamento, tipo_manutencao, descricao_servico, solicitante, data_manutencao, prioridade, status)
                VALUES
                    (:ativo_matricula, :tipo_equipamento, :tipo_manutencao, :descricao_servico, :solicitante, :data_manutencao, :prioridade, :status)
            ");
            $stmt->execute([
                'ativo_matricula' => $ativo_matricula,
                'tipo_equipamento' => $tipo_equipamento,
                'tipo_manutencao' => $tipo_manutencao,
                'descricao_servico' => $descricao_servico !== '' ? $descricao_servico : null,
                'solicitante' => $solicitante !== '' ? $solicitante : null,
                'data_manutencao' => $data_manutencao,
                'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                'status' => $status !== '' ? $status : 'Pendente',
            ]);
            $manutencaoId = (int)$pdo->lastInsertId();

            $textoServico = $descricao_servico !== '' ? $descricao_servico : ('Manutencao ' . $tipo_manutencao);
            $novaOs = criarOrdemServicoAutomatica($pdo, [
                'origem_tipo' => 'MANUTENCAO',
                'origem_id' => $manutencaoId,
                'ativo_matricula' => $ativo_matricula,
                'tipo_equipamento' => $tipo_equipamento,
                'descricao_servico' => $textoServico,
                'data_abertura' => $data_manutencao . ' 08:00:00',
                'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                'status_os' => 'Aberto',
            ]);

            $stmtHist = $pdo->prepare("
                INSERT INTO oficina_historico_avarias
                    (ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo, origem_id)
                VALUES
                    (:ativo_matricula, :tipo_equipamento, 'MANUTENCAO', :descricao, :data_evento, 'MANUTENCAO', :origem_id)
            ");
            $stmtHist->execute([
                'ativo_matricula' => $ativo_matricula,
                'tipo_equipamento' => $tipo_equipamento,
                'descricao' => $textoServico,
                'data_evento' => $data_manutencao,
                'origem_id' => $manutencaoId,
            ]);
            $pdo->commit();

            header("Location: ?tab={$tab}&view=manutencao&mode=list&saved=1&os={$novaOs['codigo']}");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro_manutencao = "Nao foi possivel gravar manutencao: " . $e->getMessage();
        }
    }

    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $osCriada = trim((string)($_GET['os'] ?? ''));
        $msg_manutencao = $osCriada !== ''
            ? "Manutencao guardada com sucesso. Ordem de servico {$osCriada} criada automaticamente."
            : 'Manutencao guardada com sucesso.';
    }

    try {
        $stmt = $pdo->query("
            SELECT id, ativo_matricula, tipo_equipamento, tipo_manutencao, descricao_servico, solicitante, data_manutencao, prioridade, status
            FROM oficina_manutencoes
            ORDER BY id DESC
        ");
        $manutencoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_manutencao = "Nao foi possivel carregar manutencoes.";
    }
}

if ($view === 'avarias') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_avaria') {
        try {
            $ativo_matricula = trim((string)($_POST['ativo_matricula'] ?? ''));
            $tipo_equipamento = trim((string)($_POST['tipo_equipamento'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $data_evento = trim((string)($_POST['data_evento'] ?? ''));
            $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
            $criar_os = (string)($_POST['criar_os'] ?? '1') === '1';

            if ($ativo_matricula === '' || $tipo_equipamento === '' || $descricao === '' || $data_evento === '') {
                throw new RuntimeException('Preencha os campos obrigatorios da avaria.');
            }

            $pdo->beginTransaction();
            $stmtHist = $pdo->prepare("
                INSERT INTO oficina_historico_avarias
                    (ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo, origem_id)
                VALUES
                    (:ativo_matricula, :tipo_equipamento, 'AVARIA', :descricao, :data_evento, 'REGISTO_AVARIA', NULL)
            ");
            $stmtHist->execute([
                'ativo_matricula' => $ativo_matricula,
                'tipo_equipamento' => $tipo_equipamento,
                'descricao' => $descricao,
                'data_evento' => $data_evento,
            ]);

            $codigoOs = '';
            if ($criar_os) {
                $novaOs = criarOrdemServicoAutomatica($pdo, [
                    'origem_tipo' => 'REGISTO_AVARIA',
                    'origem_id' => null,
                    'ativo_matricula' => $ativo_matricula,
                    'tipo_equipamento' => $tipo_equipamento,
                    'descricao_servico' => $descricao,
                    'data_abertura' => $data_evento . ' 08:00:00',
                    'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                    'status_os' => 'Aberto',
                ]);
                $codigoOs = (string)$novaOs['codigo'];
            }
            $pdo->commit();

            header("Location: ?tab={$tab}&view=avarias&mode=list&saved=1&os={$codigoOs}");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro_avarias = "Nao foi possivel registar avaria: " . $e->getMessage();
        }
    }

    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $osCriada = trim((string)($_GET['os'] ?? ''));
        $msg_avarias = $osCriada !== ''
            ? "Avaria registada com sucesso. Ordem de servico {$osCriada} criada."
            : "Avaria registada com sucesso.";
    }

    try {
        $stmt = $pdo->query("
            SELECT id, ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo
            FROM oficina_historico_avarias
            WHERE tipo_registo = 'AVARIA'
            ORDER BY data_evento DESC, id DESC
        ");
        $avarias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_avarias = "Nao foi possivel carregar avarias.";
    }
}

if ($view === 'ordens_servico') {
    if (isset($_GET['saved_os']) && $_GET['saved_os'] === '1') {
        $msg_os = 'Ordem de servico criada com sucesso.';
    }

    try {
        $stmt = $pdo->query("
            SELECT id, codigo_os, origem_tipo, origem_id, ativo_matricula, tipo_equipamento, descricao_servico, data_abertura, prioridade, status_os
            FROM oficina_ordens_servico
            ORDER BY id DESC
        ");
        $ordens_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_os = "Nao foi possivel carregar ordens de servico.";
    }
}

if ($view === 'assiduidade') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
        $acao = trim((string)$_POST['acao']);

        try {
            if ($acao === 'marcar_presenca_lote') {
                $dataPresenca = trim((string)($_POST['data_presenca'] ?? date('Y-m-d')));
                $statusLote = $_POST['status_lote'] ?? [];
                $obsLote = $_POST['obs_lote'] ?? [];

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPresenca)) {
                    throw new RuntimeException('Data de presenca invalida.');
                }
                if (!is_array($statusLote) || count($statusLote) === 0) {
                    throw new RuntimeException('Nenhum colaborador foi enviado para marcacao.');
                }

                $statusValidos = ['Presente', 'Atraso', 'Falta', 'Dispensa'];
                $stmtBusca = $pdo->prepare("
                    SELECT id
                    FROM oficina_presencas_rh
                    WHERE data_presenca = :data_presenca
                      AND pessoal_id = :pessoal_id
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmtInsert = $pdo->prepare("
                    INSERT INTO oficina_presencas_rh
                        (data_presenca, pessoal_id, status_presenca, observacoes, criado_por)
                    VALUES
                        (:data_presenca, :pessoal_id, :status_presenca, :observacoes, :criado_por)
                ");
                $stmtUpdate = $pdo->prepare("
                    UPDATE oficina_presencas_rh
                    SET status_presenca = :status_presenca,
                        observacoes = :observacoes
                    WHERE id = :id
                ");

                foreach ($statusLote as $pessoalIdRaw => $statusRaw) {
                    $pessoalId = (int)$pessoalIdRaw;
                    if ($pessoalId <= 0) {
                        continue;
                    }

                    $statusPresenca = trim((string)$statusRaw);
                    if (!in_array($statusPresenca, $statusValidos, true)) {
                        $statusPresenca = 'Presente';
                    }
                    $observacoes = '';
                    if (is_array($obsLote) && array_key_exists((string)$pessoalIdRaw, $obsLote)) {
                        $observacoes = trim((string)$obsLote[(string)$pessoalIdRaw]);
                    }

                    $stmtBusca->execute([
                        'data_presenca' => $dataPresenca,
                        'pessoal_id' => $pessoalId,
                    ]);
                    $existenteId = (int)($stmtBusca->fetchColumn() ?: 0);

                    if ($existenteId > 0) {
                        $stmtUpdate->execute([
                            'status_presenca' => $statusPresenca,
                            'observacoes' => $observacoes !== '' ? $observacoes : null,
                            'id' => $existenteId,
                        ]);
                    } else {
                        $stmtInsert->execute([
                            'data_presenca' => $dataPresenca,
                            'pessoal_id' => $pessoalId,
                            'status_presenca' => $statusPresenca,
                            'observacoes' => $observacoes !== '' ? $observacoes : null,
                            'criado_por' => (int)($_SESSION['usuario_id'] ?? 0),
                        ]);
                    }
                }

                header("Location: ?tab={$tab}&view=assiduidade&mode=list&data_assiduidade=" . urlencode($dataPresenca) . "&saved_assiduidade_lote=1");
                exit;
            }

            if ($acao === 'marcar_presenca') {
                $pessoalId = (int)($_POST['pessoal_id'] ?? 0);
                $dataPresenca = trim((string)($_POST['data_presenca'] ?? date('Y-m-d')));
                $statusPresenca = trim((string)($_POST['status_presenca'] ?? 'Presente'));
                $observacoes = trim((string)($_POST['observacoes'] ?? ''));

                if ($pessoalId <= 0) {
                    throw new RuntimeException('Selecione o funcionario da oficina.');
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPresenca)) {
                    throw new RuntimeException('Data de presenca invalida.');
                }
                if (!in_array($statusPresenca, ['Presente', 'Atraso', 'Falta', 'Dispensa'], true)) {
                    $statusPresenca = 'Presente';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO oficina_presencas_rh
                        (data_presenca, pessoal_id, status_presenca, observacoes, criado_por)
                    VALUES
                        (:data_presenca, :pessoal_id, :status_presenca, :observacoes, :criado_por)
                ");
                $stmt->execute([
                    'data_presenca' => $dataPresenca,
                    'pessoal_id' => $pessoalId,
                    'status_presenca' => $statusPresenca,
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                    'criado_por' => (int)($_SESSION['usuario_id'] ?? 0),
                ]);

                header("Location: ?tab={$tab}&view=assiduidade&mode=list&data_assiduidade=" . urlencode($dataPresenca) . "&saved_assiduidade=1");
                exit;
            }

            if ($acao === 'enviar_rh') {
                $dataEnvio = trim((string)($_POST['data_presenca'] ?? $data_assiduidade));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataEnvio)) {
                    throw new RuntimeException('Data de envio invalida.');
                }

                $stmt = $pdo->prepare("
                    UPDATE oficina_presencas_rh
                    SET enviado_rh = 1,
                        enviado_em = NOW()
                    WHERE data_presenca = :data_presenca
                      AND enviado_rh = 0
                ");
                $stmt->execute(['data_presenca' => $dataEnvio]);

                header("Location: ?tab={$tab}&view=assiduidade&mode=list&data_assiduidade=" . urlencode($dataEnvio) . "&sent_rh=1");
                exit;
            }
        } catch (Throwable $e) {
            $erro_assiduidade = "Nao foi possivel processar a assiduidade: " . $e->getMessage();
        }
    }

    if (isset($_GET['saved_assiduidade']) && $_GET['saved_assiduidade'] === '1') {
        $msg_assiduidade = 'Presenca registada com sucesso.';
    }
    if (isset($_GET['saved_assiduidade_lote']) && $_GET['saved_assiduidade_lote'] === '1') {
        $msg_assiduidade = 'Lista de presenca atualizada com sucesso.';
    }
    if (isset($_GET['sent_rh']) && $_GET['sent_rh'] === '1') {
        $msg_assiduidade = 'Lista de presenca enviada para o RH com sucesso.';
    }

    try {
        $stmtCol = $pdo->query("
            SELECT p.id, p.numero, p.nome, c.nome AS cargo_nome
            FROM pessoal p
            LEFT JOIN cargos c ON c.id = p.cargo_id
            WHERE p.estado = 'Activo'
            ORDER BY p.nome ASC
        ");
        $colaboradores_oficina = $stmtCol->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmtAss = $pdo->prepare("
            SELECT
                apr.id,
                apr.pessoal_id,
                apr.data_presenca,
                apr.status_presenca,
                apr.observacoes,
                apr.enviado_rh,
                apr.enviado_em,
                p.numero,
                p.nome,
                c.nome AS cargo_nome
            FROM oficina_presencas_rh apr
            INNER JOIN pessoal p ON p.id = apr.pessoal_id
            LEFT JOIN cargos c ON c.id = p.cargo_id
            WHERE apr.data_presenca = :data_presenca
            ORDER BY p.nome ASC, apr.id DESC
        ");
        $stmtAss->execute(['data_presenca' => $data_assiduidade]);
        $presencas_oficina = $stmtAss->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $presencas_por_colaborador = [];
        foreach ($presencas_oficina as $pr) {
            $pid = (int)($pr['pessoal_id'] ?? 0);
            if ($pid <= 0 || isset($presencas_por_colaborador[$pid])) {
                continue;
            }
            $presencas_por_colaborador[$pid] = [
                'status_presenca' => (string)($pr['status_presenca'] ?? 'Presente'),
                'observacoes' => (string)($pr['observacoes'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $erro_assiduidade = "Nao foi possivel carregar a assiduidade da oficina.";
    }
}

if ($view === 'relatorios') {
    try {
        $relatorio_filtros['matricula'] = strtoupper(trim((string)($_GET['rf_matricula'] ?? '')));
        $relatorio_filtros['data_inicio'] = trim((string)($_GET['rf_data_inicio'] ?? ''));
        $relatorio_filtros['data_fim'] = trim((string)($_GET['rf_data_fim'] ?? ''));

        if ($relatorio_filtros['data_inicio'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $relatorio_filtros['data_inicio'])) {
            $relatorio_filtros['data_inicio'] = '';
        }
        if ($relatorio_filtros['data_fim'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $relatorio_filtros['data_fim'])) {
            $relatorio_filtros['data_fim'] = '';
        }

        $where = [];
        $params = [];
        $osWhere = [];
        $osParams = [];

        if ($relatorio_filtros['matricula'] !== '') {
            $where[] = "h.ativo_matricula COLLATE utf8mb4_general_ci = :matricula COLLATE utf8mb4_general_ci";
            $params['matricula'] = $relatorio_filtros['matricula'];
        }
        if ($relatorio_filtros['data_inicio'] !== '') {
            $where[] = "h.data_evento >= :data_inicio";
            $params['data_inicio'] = $relatorio_filtros['data_inicio'];
            $osWhere[] = "DATE(os.data_abertura) >= :os_data_inicio";
            $osParams['os_data_inicio'] = $relatorio_filtros['data_inicio'];
        }
        if ($relatorio_filtros['data_fim'] !== '') {
            $where[] = "h.data_evento <= :data_fim";
            $params['data_fim'] = $relatorio_filtros['data_fim'];
            $osWhere[] = "DATE(os.data_abertura) <= :os_data_fim";
            $osParams['os_data_fim'] = $relatorio_filtros['data_fim'];
        }

        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $osWhereSql = count($osWhere) ? (' AND ' . implode(' AND ', $osWhere)) : '';

        $sqlResumo = "
            SELECT
                h.ativo_matricula,
                MAX(h.tipo_equipamento) AS tipo_equipamento,
                SUM(CASE WHEN h.tipo_registo = 'AVARIA' THEN 1 ELSE 0 END) AS total_avarias,
                SUM(CASE WHEN h.tipo_registo = 'MANUTENCAO' THEN 1 ELSE 0 END) AS total_manutencoes,
                (
                    SELECT COUNT(*)
                    FROM oficina_ordens_servico os
                    WHERE os.ativo_matricula COLLATE utf8mb4_general_ci = h.ativo_matricula COLLATE utf8mb4_general_ci
                    {$osWhereSql}
                ) AS total_idas_oficina,
                MAX(h.data_evento) AS ultima_ocorrencia
            FROM oficina_historico_avarias h
            {$whereSql}
            GROUP BY h.ativo_matricula
            ORDER BY total_avarias DESC, total_idas_oficina DESC, h.ativo_matricula ASC
        ";
        $stmtResumo = $pdo->prepare($sqlResumo);
        $stmtResumo->execute(array_merge($params, $osParams));
        $relatorio_resumo = $stmtResumo->fetchAll(PDO::FETCH_ASSOC);

        $sqlHist = "
            SELECT id, ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo, origem_id
            FROM oficina_historico_avarias h
            {$whereSql}
            ORDER BY data_evento DESC, id DESC
        ";
        $stmtHist = $pdo->prepare($sqlHist);
        $stmtHist->execute($params);
        $relatorio_historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

        $sqlTendencia = "
            SELECT
                DATE_FORMAT(h.data_evento, '%Y-%m') AS mes_ref,
                SUM(CASE WHEN h.tipo_registo = 'AVARIA' THEN 1 ELSE 0 END) AS total_avarias,
                SUM(CASE WHEN h.tipo_registo = 'MANUTENCAO' THEN 1 ELSE 0 END) AS total_manutencoes
            FROM oficina_historico_avarias h
            {$whereSql}
            GROUP BY DATE_FORMAT(h.data_evento, '%Y-%m')
            ORDER BY mes_ref DESC
        ";
        $stmtTendencia = $pdo->prepare($sqlTendencia);
        $stmtTendencia->execute($params);
        $relatorio_tendencia_mensal = $stmtTendencia->fetchAll(PDO::FETCH_ASSOC);

        $whereAvarias = $where;
        $whereAvarias[] = "h.tipo_registo = 'AVARIA'";
        $whereAvariasSql = 'WHERE ' . implode(' AND ', $whereAvarias);
        $sqlTopAvarias = "
            SELECT
                TRIM(SUBSTRING(h.descricao, 1, 100)) AS descricao_curta,
                COUNT(*) AS total
            FROM oficina_historico_avarias h
            {$whereAvariasSql}
            GROUP BY descricao_curta
            ORDER BY total DESC, descricao_curta ASC
            LIMIT 10
        ";
        $stmtTopAvarias = $pdo->prepare($sqlTopAvarias);
        $stmtTopAvarias->execute($params);
        $relatorio_top_avarias = $stmtTopAvarias->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_relatorios = "Nao foi possivel carregar os relatorios de oficina.";
    }
}
function campo($row, $keys, $default = '-') {
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return $default;
}

function badgeClassePrioridade($valor) {
    $v = strtolower(trim((string)$valor));
    if ($v === 'urgente') return 'warn';
    if ($v === 'alta') return 'warn';
    if ($v === 'normal') return 'ok';
    return 'info';
}

function badgeClasseStatus($valor) {
    $v = strtolower(trim((string)$valor));
    if ($v === 'resolvido' || $v === 'fechado' || $v === 'concluida' || $v === 'concluido') return 'ok';
    if ($v === 'aceito') return 'info';
    if ($v === 'em andamento' || $v === 'em progresso') return 'info';
    if ($v === 'pendente' || $v === 'aberto') return 'warn';
    return 'info';
}
?>
<?php include 'includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/tabs.php'; ?>

    <div class="container">
        <style>
            .export-tools { display:flex; gap:8px; }
            .btn-export {
                border:1px solid #d1d5db;
                background:#ffffff;
                color:#111827;
                padding:6px 10px;
                border-radius:20px;
                font-size:11px;
                font-weight:700;
                cursor:pointer;
            }
            .btn-export i { margin-right:6px; }
            .pedidos-table-wrap {
                width: 100%;
                overflow-x: auto;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #fff;
            }
            .pedidos-table-wrap .table {
                min-width: 1300px;
                margin: 0;
            }
            .pedidos-table-wrap .table th,
            .pedidos-table-wrap .table td {
                white-space: nowrap;
                padding: 10px 8px;
                font-size: 12px;
            }
            .pedido-desc {
                max-width: 280px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                display: inline-block;
                vertical-align: bottom;
            }
            .pedido-acoes {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }
            .btn-acao {
                border: none;
                color: #fff;
                padding: 6px 10px;
                border-radius: 7px;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
            }
            .kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(160px, 1fr));
                gap: 12px;
                margin: 10px 0 16px 0;
            }
            .kpi-card {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #ffffff;
                padding: 12px;
            }
            .kpi-card.kpi-blue { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-color: #bfdbfe; }
            .kpi-card.kpi-red { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-color: #fecaca; }
            .kpi-card.kpi-amber { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-color: #fde68a; }
            .kpi-card.kpi-green { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-color: #a7f3d0; }
            .kpi-card.kpi-slate { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-color: #cbd5e1; }
            .kpi-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }
            .kpi-icon {
                width: 26px;
                height: 26px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                color: #fff;
            }
            .kpi-icon.blue { background: #2563eb; }
            .kpi-icon.red { background: #dc2626; }
            .kpi-icon.amber { background: #d97706; }
            .kpi-icon.green { background: #16a34a; }
            .kpi-icon.slate { background: #475569; }
            .kpi-card .kpi-label {
                font-size: 11px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: .2px;
            }
            .kpi-card .kpi-value {
                margin-top: 6px;
                font-size: 22px;
                font-weight: 800;
                color: #111827;
            }
            .bar-track {
                width: 120px;
                height: 8px;
                border-radius: 10px;
                background: #e5e7eb;
                overflow: hidden;
                display: inline-block;
                vertical-align: middle;
            }
            .bar-fill {
                height: 100%;
                background: #ef4444;
            }
            .relatorio-tabs {
                display: flex;
                gap: 8px;
                margin: 8px 0 12px 0;
            }
            .relatorio-tab-btn {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
                padding: 7px 12px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
            }
            .relatorio-tab-btn.active {
                background: #111827;
                color: #f9fafb;
                border-color: #111827;
            }
            .relatorio-tab-btn i { margin-right: 6px; }
            .relatorio-pane { display: none; }
            .relatorio-pane.active { display: block; }
            .screen-title-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                margin-bottom: 8px;
            }
            .screen-subtitle {
                font-size: 12px;
                color: #6b7280;
                margin: 4px 0 0 0;
            }
            .section-card {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #ffffff;
                padding: 12px;
                margin-bottom: 12px;
            }
            .section-title {
                font-size: 12px;
                color: #111827;
                font-weight: 800;
                letter-spacing: .2px;
                text-transform: uppercase;
                margin-bottom: 8px;
            }
            .insight-banner {
                border: 1px solid #dbeafe;
                background: linear-gradient(90deg, #eff6ff 0%, #ffffff 100%);
                border-radius: 12px;
                padding: 10px 12px;
                font-size: 12px;
                color: #1e3a8a;
                margin-bottom: 10px;
            }
            .insight-banner i { margin-right: 6px; }
            .relatorio-grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .tag-soft {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border: 1px solid #e5e7eb;
                border-radius: 999px;
                padding: 6px 10px;
                font-size: 11px;
                color: #374151;
                background: #f9fafb;
                font-weight: 700;
            }
            .trend-up { color: #16a34a; font-weight: 700; }
            .trend-down { color: #dc2626; font-weight: 700; }
            .trend-flat { color: #6b7280; font-weight: 700; }
            .relatorio-filtros {
                display: grid;
                grid-template-columns: 1.2fr 1fr 1fr auto auto;
                gap: 8px;
                align-items: end;
                margin: 4px 0 12px 0;
            }
            .relatorio-filtros input {
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
            }
            .btn-filter {
                border: 1px solid #111827;
                background: #111827;
                color: #fff;
                border-radius: 8px;
                padding: 8px 12px;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
            }
            .btn-filter.secondary {
                border-color: #d1d5db;
                background: #fff;
                color: #111827;
            }
            @media (max-width: 980px) {
                .kpi-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
                .relatorio-filtros { grid-template-columns: 1fr 1fr; }
                .relatorio-grid-2 { grid-template-columns: 1fr; }
                .screen-title-row { flex-direction: column; align-items: flex-start; }
            }
        </style>
        <div class="white-card">
            <div class="inner-nav">
                <div class="mode-selector">
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=list" class="btn-mode <?= $mode == 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i> Ver Lista</a>
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=form" class="btn-mode <?= $mode == 'form' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Adicionar Novo</a>
                </div>
                <?php if ($mode == 'list'): ?>
                <div class="list-tools">
                    <div class="search-group">
                        <i class="fas fa-search"></i>
                        <input class="search-input" type="text" placeholder="Pesquisar...">
                    </div>
                    <select class="filter-select">
                        <?php if ($view == 'pedidos_reparacao'): ?>
                            <option value="">Filtrar por status</option>
                            <option>Pendente</option>
                            <option>Aceito</option>
                            <option>Em andamento</option>
                            <option>Resolvido</option>
                        <?php elseif ($view == 'manutencao'): ?>
                            <option value="">Filtrar por status</option>
                            <option>Pendente</option>
                            <option>Em andamento</option>
                            <option>Concluida</option>
                        <?php elseif ($view == 'ordens_servico'): ?>
                            <option value="">Filtrar por status</option>
                            <option>Aberto</option>
                            <option>Em andamento</option>
                            <option>Fechado</option>
                        <?php elseif ($view == 'assiduidade'): ?>
                            <option value="">Filtrar por status</option>
                            <option>Presente</option>
                            <option>Atraso</option>
                            <option>Falta</option>
                            <option>Dispensa</option>
                        <?php else: ?>
                            <option value="">Sem filtro de status</option>
                        <?php endif; ?>
                    </select>
                    <div class="export-tools">
                        <button type="button" class="btn-export" data-export-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn-export" data-export-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($mode == 'form'): ?>
                <?php if ($view == 'ordens_servico'): ?>
                    <h3>Nova Ordem de Servico</h3>
                    <p style="font-size:11px;color:var(--info);">Proxima referencia: <?= htmlspecialchars($proximo_os) ?></p>

                    <?php if ($erro_os): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_os) ?></p>
                    <?php endif; ?>

                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=ordens_servico&mode=form">
                        <input type="hidden" name="acao" value="criar_os_manual">
                        <div class="section-title">Equipamento</div>

                        <div class="form-group">
                            <label>matricula</label>
                            <input type="text" name="matricula" required>
                        </div>

                        <div class="form-group">
                            <label>tipo_equipamento</label>
                            <input type="text" name="equipamento" required>
                        </div>

                        <div class="form-group">
                            <label>prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>data_entrada</label>
                            <input type="datetime-local" name="data_entrada" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>

                        <div class="form-group" style="grid-column:span 4;">
                            <label>descricao_servico</label>
                            <textarea name="descricao" rows="4" required></textarea>
                        </div>

                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:var(--vilcon-black);width:100%;">Abrir OS</button>
                        </div>
                    </form>
                <?php elseif ($view == 'pedidos_reparacao'): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <div>
                            <h3>Pedido de ReparaÃ§Ã£o</h3>
                            <p style="font-size:12px; color:#6b7280; margin-top:4px;">Registe o pedido com prioridade, local e sintomas para acelerar o atendimento.</p>
                        </div>
                        <div class="pill warn">Nova solicitaÃ§Ã£o</div>
                    </div>

                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=form">
                        <input type="hidden" name="acao" value="criar_pedido">
                        <div class="section-title">Dados Obrigatorios do Pedido</div>

                        <div class="form-group">
                            <label>ativo_matricula</label>
                            <input type="text" name="ativo_matricula" placeholder="Ex: AHH-532-MP" required>
                        </div>

                        <div class="form-group">
                            <label>tipo_equipamento</label>
                            <input type="text" name="tipo_equipamento" placeholder="Ex: Escavadora" required>
                        </div>

                        <div class="form-group">
                            <label>solicitante</label>
                            <input type="text" name="solicitante" placeholder="Ex: Joao Mendes">
                        </div>

                        <div class="form-group">
                            <label>data_pedido</label>
                            <input type="date" name="data_pedido" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>localizacao</label>
                            <input type="text" name="localizacao" placeholder="Ex: Estaleiro Vilankulos">
                        </div>

                        <div class="form-group">
                            <label>prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>status</label>
                            <select name="status">
                                <option>Pendente</option>
                                <option>Aceito</option>
                                <option>Em andamento</option>
                                <option>Resolvido</option>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column:span 4;">
                            <label>descricao_avaria</label>
                            <textarea name="descricao_avaria" rows="4" placeholder="Descreva a avaria..." required></textarea>
                        </div>

                        <div style="grid-column:span 4; display:flex; gap:10px;">
                            <button class="btn-save" type="submit" style="background:var(--danger); flex:1;">Guardar Pedido</button>
                            <button type="reset" class="btn-save" style="background:#9ca3af; width:180px;">Limpar</button>
                        </div>
                    </form>
                <?php elseif ($view == 'manutencao'): ?>
                    <h3>Registo de Manutencao</h3>
                    <p style="font-size:12px; color:#6b7280;">Ao guardar, o sistema cria automaticamente uma ordem de servico e regista no historico do veiculo.</p>
                    <?php if ($erro_manutencao): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_manutencao) ?></p>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=manutencao&mode=form">
                        <input type="hidden" name="acao" value="criar_manutencao">

                        <div class="form-group">
                            <label>ativo_matricula</label>
                            <input type="text" name="ativo_matricula" required>
                        </div>
                        <div class="form-group">
                            <label>tipo_equipamento</label>
                            <input type="text" name="tipo_equipamento" required>
                        </div>
                        <div class="form-group">
                            <label>tipo_manutencao</label>
                            <select name="tipo_manutencao">
                                <option>Preventiva</option>
                                <option>Corretiva</option>
                                <option>Inspecao</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>data_manutencao</label>
                            <input type="date" name="data_manutencao" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>solicitante</label>
                            <input type="text" name="solicitante">
                        </div>
                        <div class="form-group">
                            <label>prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>status</label>
                            <select name="status">
                                <option>Pendente</option>
                                <option>Em andamento</option>
                                <option>Concluida</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:span 4;">
                            <label>descricao_servico</label>
                            <textarea name="descricao_servico" rows="4" placeholder="Ex: Troca de oleo e filtros"></textarea>
                        </div>

                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:var(--danger);width:100%;">Guardar Manutencao</button>
                        </div>
                    </form>
                <?php elseif ($view == 'assiduidade'): ?>
                    <h3>Marcacao de Presenca da Oficina</h3>
                    <p style="font-size:12px; color:#6b7280;">Registe a presenca diaria e, no fim do dia, envie a lista ao RH.</p>
                    <?php if ($erro_assiduidade): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_assiduidade) ?></p>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=assiduidade&mode=form&data_assiduidade=<?= urlencode((string)$data_assiduidade) ?>">
                        <input type="hidden" name="acao" value="marcar_presenca">
                        <div class="form-group">
                            <label>Data</label>
                            <input type="date" name="data_presenca" value="<?= htmlspecialchars((string)$data_assiduidade) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Funcionario da Oficina</label>
                            <select name="pessoal_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($colaboradores_oficina as $col): ?>
                                    <option value="<?= (int)$col['id'] ?>">
                                        <?= htmlspecialchars((string)$col['nome']) ?> (<?= htmlspecialchars((string)($col['cargo_nome'] ?? '-')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status de Presenca</label>
                            <select name="status_presenca">
                                <option>Presente</option>
                                <option>Atraso</option>
                                <option>Falta</option>
                                <option>Dispensa</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Observacoes</label>
                            <input type="text" name="observacoes" placeholder="Opcional">
                        </div>
                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:#111827;width:100%;">Registar Presenca</button>
                        </div>
                    </form>
                <?php elseif ($view == 'avarias'): ?>
                    <h3>Registo de Avaria</h3>
                    <p style="font-size:12px; color:#6b7280;">Registe uma nova avaria/incidente. Pode gerar OS automaticamente no mesmo passo.</p>
                    <?php if ($erro_avarias): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_avarias) ?></p>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=avarias&mode=form">
                        <input type="hidden" name="acao" value="criar_avaria">
                        <div class="form-group">
                            <label>ativo_matricula</label>
                            <input type="text" name="ativo_matricula" required>
                        </div>
                        <div class="form-group">
                            <label>tipo_equipamento</label>
                            <input type="text" name="tipo_equipamento" required>
                        </div>
                        <div class="form-group">
                            <label>data_evento</label>
                            <input type="date" name="data_evento" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>gerar_os</label>
                            <select name="criar_os">
                                <option value="1">Sim</option>
                                <option value="0">Nao</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:span 4;">
                            <label>descricao</label>
                            <textarea name="descricao" rows="4" required></textarea>
                        </div>
                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:#dc2626;width:100%;">Registar Avaria</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h3>Relatorios de Oficina</h3>
                    <p style="font-size:12px; color:#6b7280;">Use o modo "Ver Lista" para consultar as metricas e historico de avarias por veiculo.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($view == 'pedidos_reparacao'): ?>
                    <h3>Lista de Pedidos de ReparaÃ§Ã£o</h3>
                    <?php if ($erro_pedidos): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_pedidos) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_pedidos): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_pedidos) ?></p>
                    <?php endif; ?>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>descricao_avaria</th>
                                <th>localizacao</th>
                                <th>solicitante</th>
                                <th>data_pedido</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pedidos_reparacao) === 0): ?>
                                <tr>
                                    <td colspan="10" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pedidos_reparacao as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($p, ['id'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['tipo_equipamento'])) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($p, ['descricao_avaria'])) ?>"><?= htmlspecialchars((string)campo($p, ['descricao_avaria'])) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['localizacao'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['solicitante'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['data_pedido'])) ?></td>
                                        <?php
                                            $prioridade = trim((string)campo($p, ['prioridade'], 'Normal'));
                                            if ($prioridade === '') $prioridade = 'Normal';
                                            $status = trim((string)campo($p, ['status'], 'Pendente'));
                                            if ($status === '') $status = 'Pendente';
                                            $statusNormalizado = normalizarStatusPedido($status);
                                            $statusLabel = statusPedidoLabel($statusNormalizado);
                                        ?>
                                        <td><span class="pill <?= badgeClassePrioridade($prioridade) ?>"><?= htmlspecialchars((string)$prioridade) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus($statusLabel) ?>"><?= htmlspecialchars((string)$statusLabel) ?></span></td>
                                        <td>
                                            <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=list" class="pedido-acoes">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars((string)campo($p, ['id'])) ?>">
                                                <?php if ($statusNormalizado === 'pendente'): ?>
                                                    <button type="submit" name="acao" value="aceitar" class="btn-acao" style="background:#2563eb;">Aceitar</button>
                                                <?php endif; ?>
                                                <?php if ($statusNormalizado === 'pendente' || $statusNormalizado === 'aceito'): ?>
                                                    <button type="submit" name="acao" value="andamento" class="btn-acao" style="background:#0891b2;">Em andamento</button>
                                                <?php endif; ?>
                                                <?php if ($statusNormalizado !== 'resolvido'): ?>
                                                    <button type="submit" name="acao" value="resolver" class="btn-acao" style="background:#16a34a;">Resolver</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'ordens_servico'): ?>
                    <h3>Ordens de Servico</h3>
                    <?php if ($erro_os): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_os) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_os): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_os) ?></p>
                    <?php endif; ?>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>codigo_os</th>
                                <th>origem</th>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>descricao_servico</th>
                                <th>data_abertura</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ordens_servico) === 0): ?>
                                <tr><td colspan="9" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ordens_servico as $os): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($os, ['id'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($os, ['codigo_os'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($os, ['origem_tipo'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($os, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($os, ['tipo_equipamento'])) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($os, ['descricao_servico'])) ?>"><?= htmlspecialchars((string)campo($os, ['descricao_servico'])) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($os, ['data_abertura'])) ?></td>
                                        <td><span class="pill <?= badgeClassePrioridade(campo($os, ['prioridade'], 'Normal')) ?>"><?= htmlspecialchars((string)campo($os, ['prioridade'], 'Normal')) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus(campo($os, ['status_os'], 'Aberto')) ?>"><?= htmlspecialchars((string)campo($os, ['status_os'], 'Aberto')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'manutencao'): ?>
                    <h3>Lista de Manutencoes</h3>
                    <?php if ($erro_manutencao): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_manutencao) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_manutencao): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_manutencao) ?></p>
                    <?php endif; ?>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>tipo_manutencao</th>
                                <th>descricao_servico</th>
                                <th>solicitante</th>
                                <th>data_manutencao</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($manutencoes) === 0): ?>
                                <tr><td colspan="9" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($manutencoes as $m): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($m, ['id'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($m, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($m, ['tipo_equipamento'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($m, ['tipo_manutencao'])) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($m, ['descricao_servico'])) ?>"><?= htmlspecialchars((string)campo($m, ['descricao_servico'])) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($m, ['solicitante'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($m, ['data_manutencao'])) ?></td>
                                        <td><span class="pill <?= badgeClassePrioridade(campo($m, ['prioridade'], 'Normal')) ?>"><?= htmlspecialchars((string)campo($m, ['prioridade'], 'Normal')) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus(campo($m, ['status'], 'Pendente')) ?>"><?= htmlspecialchars((string)campo($m, ['status'], 'Pendente')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'avarias'): ?>
                    <div class="screen-title-row">
                        <div>
                            <h3>Lista de Avarias</h3>
                            <p class="screen-subtitle">Ocorrencias registadas para apoiar diagnostico rapido e priorizacao.</p>
                        </div>
                        <div class="tag-soft"><i class="fa-solid fa-triangle-exclamation"></i> Total: <?= count($avarias) ?></div>
                    </div>
                    <?php if ($erro_avarias): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_avarias) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_avarias): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_avarias) ?></p>
                    <?php endif; ?>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>descricao</th>
                                <th>data_evento</th>
                                <th>origem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($avarias) === 0): ?>
                                <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($avarias as $a): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($a, ['id'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($a, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($a, ['tipo_equipamento'])) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($a, ['descricao'])) ?>"><?= htmlspecialchars((string)campo($a, ['descricao'])) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($a, ['data_evento'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($a, ['origem_tipo'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'assiduidade'): ?>
                    <div class="screen-title-row">
                        <div>
                            <h3>Lista de Presenca - Oficina</h3>
                            <p class="screen-subtitle">Controle diario de presencas para envio ao RH.</p>
                        </div>
                        <div class="tag-soft"><i class="fa-solid fa-user-check"></i> Data: <?= htmlspecialchars((string)$data_assiduidade) ?></div>
                    </div>
                    <?php if ($erro_assiduidade): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_assiduidade) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_assiduidade): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_assiduidade) ?></p>
                    <?php endif; ?>

                    <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=assiduidade&mode=list&data_assiduidade=<?= urlencode((string)$data_assiduidade) ?>" style="margin-bottom:12px;">
                        <input type="hidden" name="acao" value="marcar_presenca_lote">
                        <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string)$data_assiduidade) ?>">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                            <div style="font-size:12px; color:#374151; font-weight:700;">
                                Lista de funcionarios para marcacao diaria de presenca
                            </div>
                            <button type="submit" class="btn-save" style="background:#111827;">
                                <i class="fa-solid fa-check-double"></i> Guardar Marcacoes da Lista
                            </button>
                        </div>
                        <div class="pedidos-table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Numero</th>
                                        <th>Nome</th>
                                        <th>Cargo</th>
                                        <th>Status</th>
                                        <th>Observacoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($colaboradores_oficina) === 0): ?>
                                        <tr><td colspan="5" style="text-align:center;color:#6b7280;padding:12px;">Sem colaboradores da oficina para marcacao.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($colaboradores_oficina as $col): ?>
                                            <?php
                                                $pid = (int)($col['id'] ?? 0);
                                                $atual = $presencas_por_colaborador[$pid] ?? null;
                                                $statusAtual = (string)($atual['status_presenca'] ?? 'Presente');
                                                $obsAtual = (string)($atual['observacoes'] ?? '');
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)($col['numero'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($col['nome'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($col['cargo_nome'] ?? '-')) ?></td>
                                                <td>
                                                    <select name="status_lote[<?= $pid ?>]" style="min-width:120px;">
                                                        <option value="Presente" <?= $statusAtual === 'Presente' ? 'selected' : '' ?>>Presente</option>
                                                        <option value="Atraso" <?= $statusAtual === 'Atraso' ? 'selected' : '' ?>>Atraso</option>
                                                        <option value="Falta" <?= $statusAtual === 'Falta' ? 'selected' : '' ?>>Falta</option>
                                                        <option value="Dispensa" <?= $statusAtual === 'Dispensa' ? 'selected' : '' ?>>Dispensa</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="obs_lote[<?= $pid ?>]" value="<?= htmlspecialchars($obsAtual) ?>" placeholder="Opcional" style="min-width:180px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <form method="GET" action="" style="display:flex; gap:8px; align-items:flex-end; margin-bottom:10px; flex-wrap:wrap;">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars((string)$tab) ?>">
                        <input type="hidden" name="view" value="assiduidade">
                        <input type="hidden" name="mode" value="list">
                        <div class="form-group" style="margin:0;">
                            <label>Data</label>
                            <input type="date" name="data_assiduidade" value="<?= htmlspecialchars((string)$data_assiduidade) ?>">
                        </div>
                        <button type="submit" class="btn-save" style="background:#111827;">Carregar</button>
                    </form>

                    <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=assiduidade&mode=list&data_assiduidade=<?= urlencode((string)$data_assiduidade) ?>" style="margin-bottom:10px;">
                        <input type="hidden" name="acao" value="enviar_rh">
                        <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string)$data_assiduidade) ?>">
                        <button type="submit" class="btn-save" style="background:#ea580c;">
                            <i class="fa-solid fa-paper-plane"></i> Enviar Lista do Dia para RH
                        </button>
                    </form>

                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Numero</th>
                                <th>Nome</th>
                                <th>Cargo</th>
                                <th>Status</th>
                                <th>Observacoes</th>
                                <th>Enviado RH</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($presencas_oficina) === 0): ?>
                                <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:12px;">Sem registos de presenca para a data selecionada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($presencas_oficina as $pr): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($pr['id'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($pr['numero'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($pr['nome'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($pr['cargo_nome'] ?? '-')) ?></td>
                                        <td><span class="pill <?= badgeClasseStatus((string)($pr['status_presenca'] ?? '')) ?>"><?= htmlspecialchars((string)($pr['status_presenca'] ?? '-')) ?></span></td>
                                        <td><?= htmlspecialchars((string)($pr['observacoes'] ?? '-')) ?></td>
                                        <td>
                                            <?php if ((int)($pr['enviado_rh'] ?? 0) === 1): ?>
                                                <span class="pill ok">Sim</span>
                                            <?php else: ?>
                                                <span class="pill warn">Nao</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'relatorios'): ?>
                    <div class="screen-title-row">
                        <div>
                            <h3>Metricas de Avarias e Oficina</h3>
                            <p class="screen-subtitle">Analise de desempenho da frota, reincidencia de falhas e carga da oficina.</p>
                        </div>
                        <div class="tag-soft"><i class="fa-solid fa-chart-line"></i> BI Oficina</div>
                    </div>
                    <?php if ($erro_relatorios): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_relatorios) ?></p>
                    <?php endif; ?>
                    <form class="relatorio-filtros" method="GET" action="">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars((string)$tab) ?>">
                        <input type="hidden" name="view" value="relatorios">
                        <input type="hidden" name="mode" value="list">
                        <input type="text" name="rf_matricula" placeholder="Filtrar por matricula" value="<?= htmlspecialchars((string)$relatorio_filtros['matricula']) ?>">
                        <input type="date" name="rf_data_inicio" value="<?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?>">
                        <input type="date" name="rf_data_fim" value="<?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?>">
                        <button type="submit" class="btn-filter">Aplicar filtros</button>
                        <a href="?tab=<?= urlencode((string)$tab) ?>&view=relatorios&mode=list" class="btn-filter secondary" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">Limpar</a>
                    </form>
                    <?php
                        $total_viaturas = count($relatorio_resumo);
                        $total_avarias = 0;
                        $total_manutencoes = 0;
                        $total_idas = 0;
                        $maiorCarga = 0;
                        $veiculoCritico = '-';
                        $maiorCargaMatricula = 0;
                        foreach ($relatorio_resumo as $r) {
                            $a = (int)($r['total_avarias'] ?? 0);
                            $m = (int)($r['total_manutencoes'] ?? 0);
                            $o = (int)($r['total_idas_oficina'] ?? 0);
                            $total_avarias += $a;
                            $total_manutencoes += $m;
                            $total_idas += $o;
                            if ($a > $maiorCarga) $maiorCarga = $a;
                            if ($a > $maiorCargaMatricula) {
                                $maiorCargaMatricula = $a;
                                $veiculoCritico = (string)($r['ativo_matricula'] ?? '-');
                            }
                        }
                        $mediaAvarias = $total_viaturas > 0 ? round($total_avarias / $total_viaturas, 2) : 0;
                    ?>
                    <div class="insight-banner">
                        <i class="fa-solid fa-bullseye"></i>Veiculo critico atual: <strong><?= htmlspecialchars($veiculoCritico) ?></strong> |
                        Media de avarias por veiculo: <strong><?= htmlspecialchars((string)$mediaAvarias) ?></strong>
                    </div>
                    <div class="section-card">
                    <div class="section-title">Indicadores Principais</div>
                    <div class="kpi-grid">
                        <div class="kpi-card kpi-blue">
                            <div class="kpi-head">
                                <div class="kpi-label">Veiculos monitorados</div>
                                <span class="kpi-icon blue"><i class="fa-solid fa-car-side"></i></span>
                            </div>
                            <div class="kpi-value"><?= (int)$total_viaturas ?></div>
                        </div>
                        <div class="kpi-card kpi-red">
                            <div class="kpi-head">
                                <div class="kpi-label">Avarias registadas</div>
                                <span class="kpi-icon red"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                            </div>
                            <div class="kpi-value"><?= (int)$total_avarias ?></div>
                        </div>
                        <div class="kpi-card kpi-amber">
                            <div class="kpi-head">
                                <div class="kpi-label">Manutencoes</div>
                                <span class="kpi-icon amber"><i class="fa-solid fa-toolbox"></i></span>
                            </div>
                            <div class="kpi-value"><?= (int)$total_manutencoes ?></div>
                        </div>
                        <div class="kpi-card kpi-green">
                            <div class="kpi-head">
                                <div class="kpi-label">Idas a oficina</div>
                                <span class="kpi-icon green"><i class="fa-solid fa-warehouse"></i></span>
                            </div>
                            <div class="kpi-value"><?= (int)$total_idas ?></div>
                        </div>
                        <div class="kpi-card kpi-slate">
                            <div class="kpi-head">
                                <div class="kpi-label">Media avarias/veiculo</div>
                                <span class="kpi-icon slate"><i class="fa-solid fa-wave-square"></i></span>
                            </div>
                            <div class="kpi-value"><?= htmlspecialchars((string)$mediaAvarias) ?></div>
                        </div>
                        <div class="kpi-card kpi-red">
                            <div class="kpi-head">
                                <div class="kpi-label">Veiculo mais critico</div>
                                <span class="kpi-icon red"><i class="fa-solid fa-biohazard"></i></span>
                            </div>
                            <div class="kpi-value" style="font-size:16px;"><?= htmlspecialchars($veiculoCritico) ?></div>
                        </div>
                    </div>
                    </div>

                    <div class="section-card">
                    <div class="section-title">Navegacao de Analise</div>
                    <div class="relatorio-tabs" data-relatorio-tabs>
                        <button type="button" class="relatorio-tab-btn active" data-relatorio-target="ranking"><i class="fa-solid fa-ranking-star"></i>Ranking de Veiculos com Mais Avarias</button>
                        <button type="button" class="relatorio-tab-btn" data-relatorio-target="historico"><i class="fa-solid fa-clock-rotate-left"></i>Historico de Ocorrencias</button>
                        <button type="button" class="relatorio-tab-btn" data-relatorio-target="tendencia"><i class="fa-solid fa-arrow-trend-up"></i>Tendencia & Causas</button>
                    </div>
                    </div>

                    <div class="relatorio-pane active" data-relatorio-pane="ranking">
                    <h3 style="margin-top:6px;">Ranking de Veiculos com Mais Avarias</h3>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>total_avarias</th>
                                <th>indice_critico</th>
                                <th>total_manutencoes</th>
                                <th>total_idas_oficina</th>
                                <th>ultima_ocorrencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($relatorio_resumo) === 0): ?>
                                <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($relatorio_resumo as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($r, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($r, ['tipo_equipamento'])) ?></td>
                                        <?php
                                            $carga = (int)campo($r, ['total_avarias'], 0);
                                            $perc = $maiorCarga > 0 ? (int)round(($carga / $maiorCarga) * 100) : 0;
                                        ?>
                                        <td><?= $carga ?></td>
                                        <td>
                                            <span class="bar-track"><span class="bar-fill" style="width:<?= $perc ?>%;"></span></span>
                                            <span style="font-size:11px;color:#6b7280;"><?= $perc ?>%</span>
                                        </td>
                                        <td><?= (int)campo($r, ['total_manutencoes'], 0) ?></td>
                                        <td><?= (int)campo($r, ['total_idas_oficina'], 0) ?></td>
                                        <td><?= htmlspecialchars((string)campo($r, ['ultima_ocorrencia'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>

                    <div class="relatorio-pane" data-relatorio-pane="historico">
                    <h3 style="margin-top:16px;">Historico de Ocorrencias</h3>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>tipo_registo</th>
                                <th>descricao</th>
                                <th>data_evento</th>
                                <th>origem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($relatorio_historico) === 0): ?>
                                <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($relatorio_historico as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($h, ['id'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($h, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($h, ['tipo_equipamento'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($h, ['tipo_registo'])) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($h, ['descricao'])) ?>"><?= htmlspecialchars((string)campo($h, ['descricao'])) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($h, ['data_evento'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($h, ['origem_tipo'])) ?> #<?= htmlspecialchars((string)campo($h, ['origem_id'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>

                    <div class="relatorio-pane" data-relatorio-pane="tendencia">
                    <div class="relatorio-grid-2">
                    <div>
                    <h3 style="margin-top:16px;">Tendencia Mensal</h3>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>mes_ref</th>
                                <th>total_avarias</th>
                                <th>total_manutencoes</th>
                                <th>tendencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($relatorio_tendencia_mensal) === 0): ?>
                                <tr><td colspan="4" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php for ($i = 0; $i < count($relatorio_tendencia_mensal); $i++): ?>
                                    <?php
                                        $tm = $relatorio_tendencia_mensal[$i];
                                        $curAvarias = (int)campo($tm, ['total_avarias'], 0);
                                        $prevAvarias = isset($relatorio_tendencia_mensal[$i + 1])
                                            ? (int)campo($relatorio_tendencia_mensal[$i + 1], ['total_avarias'], 0)
                                            : null;
                                        $trendHtml = '<span class="trend-flat"><i class="fa-solid fa-minus"></i> neutro</span>';
                                        if ($prevAvarias !== null) {
                                            if ($curAvarias > $prevAvarias) {
                                                $trendHtml = '<span class="trend-up"><i class="fa-solid fa-arrow-up"></i> subida</span>';
                                            } elseif ($curAvarias < $prevAvarias) {
                                                $trendHtml = '<span class="trend-down"><i class="fa-solid fa-arrow-down"></i> descida</span>';
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($tm, ['mes_ref'])) ?></td>
                                        <td><?= (int)campo($tm, ['total_avarias'], 0) ?></td>
                                        <td><?= (int)campo($tm, ['total_manutencoes'], 0) ?></td>
                                        <td><?= $trendHtml ?></td>
                                    </tr>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>

                    <div>
                    <h3 style="margin-top:16px;">Top 10 Causas de Avaria</h3>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>descricao_avaria</th>
                                <th>ocorrencias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($relatorio_top_avarias) === 0): ?>
                                <tr><td colspan="2" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($relatorio_top_avarias as $ta): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($ta, ['descricao_curta'])) ?></td>
                                        <td><?= (int)campo($ta, ['total'], 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>
                    </div>
                    </div>
                <?php else: ?>
                    <h3>Lista de Registos</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th><th>Equipamento</th><th>Data</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function tabelaVisivelOficina(root) {
    var tabelas = root.querySelectorAll('table');
    for (var i = 0; i < tabelas.length; i++) {
        var t = tabelas[i];
        if (t.offsetParent !== null) return t;
    }
    return null;
}

function normalizarTextoOficina(valor) {
    return String(valor || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function indiceColunaStatusOficina(tabela) {
    var colunas = tabela.querySelectorAll('thead th');
    for (var i = 0; i < colunas.length; i++) {
        if (normalizarTextoOficina(colunas[i].textContent) === 'status') {
            return i;
        }
    }
    return -1;
}

function aplicarFiltroOficina(card) {
    var tabela = tabelaVisivelOficina(card);
    if (!tabela) return;

    var campoBusca = card.querySelector('.search-input');
    var filtroStatus = card.querySelector('.filter-select');
    var corpo = tabela.querySelector('tbody');
    if (!corpo) return;

    var termo = normalizarTextoOficina(campoBusca ? campoBusca.value : '');
    var statusSelecionado = normalizarTextoOficina(filtroStatus ? filtroStatus.value : '');
    var indiceStatus = indiceColunaStatusOficina(tabela);
    var linhas = Array.prototype.slice.call(corpo.querySelectorAll('tr'));
    var linhasDados = linhas.filter(function(linha) {
        var celulas = linha.querySelectorAll('td');
        return celulas.length > 0 && !linha.classList.contains('js-filter-empty') &&
            normalizarTextoOficina(linha.textContent).indexOf('sem registos para mostrar') === -1;
    });

    if (linhasDados.length === 0) return;

    var visiveis = 0;
    linhasDados.forEach(function(linha) {
        var textoLinha = normalizarTextoOficina(linha.textContent);
        var celulas = linha.querySelectorAll('td');
        var statusLinha = '';

        if (indiceStatus >= 0 && celulas[indiceStatus]) {
            statusLinha = normalizarTextoOficina(celulas[indiceStatus].textContent);
        }

        var okBusca = !termo || textoLinha.indexOf(termo) !== -1;
        var okStatus = !statusSelecionado || statusLinha.indexOf(statusSelecionado) !== -1;
        var mostrar = okBusca && okStatus;

        linha.style.display = mostrar ? '' : 'none';
        if (mostrar) visiveis++;
    });

    var linhaSemResultado = corpo.querySelector('tr.js-filter-empty');
    if (!linhaSemResultado && visiveis === 0) {
        linhaSemResultado = document.createElement('tr');
        linhaSemResultado.className = 'js-filter-empty';
        var td = document.createElement('td');
        var totalColunas = tabela.querySelectorAll('thead th').length || 1;
        td.colSpan = totalColunas;
        td.style.textAlign = 'center';
        td.style.color = '#6b7280';
        td.style.padding = '12px';
        td.textContent = 'Nenhum registo corresponde ao filtro.';
        linhaSemResultado.appendChild(td);
        corpo.appendChild(linhaSemResultado);
    }

    if (linhaSemResultado && visiveis > 0) {
        linhaSemResultado.remove();
    }
}

function inicializarFiltrosOficina() {
    document.querySelectorAll('.white-card').forEach(function(card) {
        var campoBusca = card.querySelector('.search-input');
        var filtroStatus = card.querySelector('.filter-select');

        if (campoBusca) {
            campoBusca.addEventListener('input', function() {
                aplicarFiltroOficina(card);
            });
        }

        if (filtroStatus) {
            filtroStatus.addEventListener('change', function() {
                aplicarFiltroOficina(card);
            });
        }
    });
}

function inicializarAbasRelatorioOficina() {
    document.querySelectorAll('[data-relatorio-tabs]').forEach(function(wrapper) {
        var card = wrapper.closest('.white-card');
        if (!card) return;

        var botoes = wrapper.querySelectorAll('.relatorio-tab-btn');
        var panes = card.querySelectorAll('[data-relatorio-pane]');
        if (!botoes.length || !panes.length) return;

        botoes.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var alvo = btn.getAttribute('data-relatorio-target');
                botoes.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');

                panes.forEach(function(p) {
                    p.classList.toggle('active', p.getAttribute('data-relatorio-pane') === alvo);
                });

                aplicarFiltroOficina(card);
            });
        });
    });
}

function nomeArquivoOficina(base, ext) {
    var data = new Date();
    var y = data.getFullYear();
    var m = String(data.getMonth() + 1).padStart(2, '0');
    var d = String(data.getDate()).padStart(2, '0');
    return base + '_' + y + m + d + '.' + ext;
}

function exportarExcelOficina(tabela, base) {
    var html = '<html><head><meta charset="UTF-8"></head><body>' + tabela.outerHTML + '</body></html>';
    var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = nomeArquivoOficina(base, 'xls');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function tabelaPdfSemAcoesOficina(tabela) {
    var clone = tabela.cloneNode(true);
    var headerCells = clone.querySelectorAll('thead th');
    var indicesAcoes = [];

    headerCells.forEach(function(th, idx) {
        var texto = String(th.textContent || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
        if (texto === 'acoes' || texto === 'acao') {
            indicesAcoes.push(idx);
        }
    });

    indicesAcoes.reverse().forEach(function(colIdx) {
        clone.querySelectorAll('tr').forEach(function(tr) {
            if (tr.children[colIdx]) {
                tr.removeChild(tr.children[colIdx]);
            }
        });
    });

    return clone.outerHTML;
}

function exportarPdfOficina(tabela, titulo) {
    var janela = window.open('', '_blank');
    if (!janela) return;
    var logoUrl = window.location.origin + '/vilcon-systemon/public/assets/img/logo-vilcon.png';
    var dataAtual = new Date().toLocaleString('pt-PT');
    var tabelaHtml = tabelaPdfSemAcoesOficina(tabela);
    var html = `
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${titulo}</title>
            <style>
                @page { margin: 18mm 12mm; }
                body { font-family: Arial, sans-serif; color: #111111; }
                .pdf-wrap { width: 100%; }
                .pdf-header { border: 2px solid #111111; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
                .pdf-strip { height: 10px; background: #f4b400; }
                .pdf-head-content { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #ffffff; }
                .pdf-brand { display: flex; align-items: center; gap: 12px; }
                .pdf-brand img { width: 130px; height: auto; object-fit: contain; }
                .pdf-brand h1 { margin: 0; font-size: 18px; color: #111111; letter-spacing: 0.4px; }
                .pdf-meta { text-align: right; font-size: 11px; color: #333333; }
                .pdf-meta strong { display: block; color: #111111; margin-bottom: 4px; }
                h2 { margin: 0 0 10px 0; color: #111111; font-size: 14px; text-transform: uppercase; }
                table { width: 100%; border-collapse: collapse; }
                thead th {
                    background: #111111 !important;
                    color: #f4b400 !important;
                    border: 1px solid #111111;
                    padding: 8px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
                }
                tbody td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; color: #111111; }
                tbody tr:nth-child(even) td { background: #fff8e1; }
            </style>
        </head>
        <body>
            <div class="pdf-wrap">
                <div class="pdf-header">
                    <div class="pdf-strip"></div>
                    <div class="pdf-head-content">
                        <div class="pdf-brand">
                            <img src="${logoUrl}" alt="Vilcon">
                            <h1>VILCON</h1>
                        </div>
                        <div class="pdf-meta">
                            <strong>${titulo}</strong>
                            <span>Emitido em: ${dataAtual}</span>
                        </div>
                    </div>
                </div>
                <h2>Relatorio</h2>
                ${tabelaHtml}
            </div>
        </body>
        </html>
    `;
    janela.document.write(html);
    janela.document.close();
    janela.focus();
    janela.print();
}

document.querySelectorAll('.btn-export').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var card = btn.closest('.white-card');
        if (!card) return;
        var tabela = tabelaVisivelOficina(card);
        if (!tabela) {
            alert('Nao ha lista visivel para exportar.');
            return;
        }
        var viewAtual = '<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>';
        var base = 'oficina_' + (viewAtual || 'lista').toLowerCase().replace(/\s+/g, '_');
        if (btn.getAttribute('data-export-format') === 'excel') {
            exportarExcelOficina(tabela, base);
        } else {
            exportarPdfOficina(tabela, base.toUpperCase());
        }
    });
});

inicializarFiltrosOficina();
inicializarAbasRelatorioOficina();
</script>

<?php include 'includes/footer.php'; ?>
