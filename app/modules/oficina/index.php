<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

/* =========================
   CONTROLES DO MÓDULO OFICINA
========================= */
$tab = $_GET['tab'] ?? 'oficina';
$view = $_GET['view'] ?? 'ordens_servico';
$mode = $_GET['mode'] ?? 'home';
if ($view === 'assiduidade') {
    $view = 'presencas';
}
if (!in_array($mode, ['home', 'list', 'form', 'detalhe'], true)) {
    $mode = 'home';
}
if ($view === 'relatorios') {
    $mode = 'list';
}
$aplicar_lista = (isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1') || in_array($view, ['relatorios', 'pedidos_reparacao'], true);

$proximo_os = "OS-OF-" . date('Y') . "-0001";

$ordens_servico = [];
$pedidos_reparacao = [];
$requisicoes_oficina = [];
$manutencoes = [];
$avarias = [];
$pedido_reparacao_detalhe = null;
$materiais_pedido_detalhe = [];
$requisicoes_pedido_detalhe = [];
$detalhe_lista = 'ambos';
$relatorio_resumo = [];
$relatorio_historico = [];
$relatorio_tendencia_mensal = [];
$relatorio_top_avarias = [];
$relatorio_resumo_periodo = [];
$relatorio_atividades_periodo = [];
$relatorio_filtros = [
    'matricula' => '',
    'data_inicio' => '',
    'data_fim' => '',
    'periodo' => 'mensal',
    'data_referencia' => date('Y-m-d'),
    'completo' => false,
];

$erro_os = null;
$erro_pedidos = null;
$erro_requisicoes = null;
$erro_manutencao = null;
$erro_avarias = null;
$erro_relatorios = null;

$msg_os = null;
$msg_pedidos = null;
$msg_requisicoes = null;
$msg_manutencao = null;
$msg_avarias = null;
$msg_presencas = null;

$erro_presencas = null;
$filtro_pedidos_datas = ['inicio' => '', 'fim' => ''];
$filtro_os_datas = ['inicio' => '', 'fim' => ''];
$hojeOficina = new DateTimeImmutable('today');
$diaSemanaOficina = (int)$hojeOficina->format('N');
$inicioSemanaOficina = $hojeOficina->modify('-' . ($diaSemanaOficina - 1) . ' days')->format('Y-m-d');
$fimSemanaOficina = $hojeOficina->format('Y-m-d');
$data_assiduidade = trim((string)($_GET['data_assiduidade'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_assiduidade)) {
    $data_assiduidade = date('Y-m-d');
}
$colaboradores_oficina = [];
$presencas_oficina = [];
$presencas_por_colaborador = [];
$lista_presenca_enviada_rh = false;
$lista_presencas_historico = [];
$listas_presenca_dias = [];
$hist_data_oficina = trim((string)($_GET['hist_data'] ?? ''));

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

function normalizarStatusRequisicaoOficina(string $valor): string {
    $v = strtolower(trim($valor));
    if ($v === '' || $v === 'pendente') return 'pendente';
    if ($v === 'aprovada' || $v === 'aprovado') return 'aprovada';
    if ($v === 'negada' || $v === 'negado' || $v === 'recusada') return 'negada';
    if ($v === 'cancelada') return 'cancelada';
    return 'pendente';
}

function labelStatusRequisicaoOficina(string $status): string {
    $s = normalizarStatusRequisicaoOficina($status);
    if ($s === 'aprovada') return 'Aprovada';
    if ($s === 'negada') return 'Negada';
    if ($s === 'cancelada') return 'Cancelada';
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
                custo_estimado DECIMAL(14,2) NOT NULL DEFAULT 0,
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
                custo_total DECIMAL(14,2) NOT NULL DEFAULT 0,
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
                custo_total DECIMAL(14,2) NOT NULL DEFAULT 0,
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
                assinou_entrada TINYINT(1) NOT NULL DEFAULT 0,
                assinou_saida TINYINT(1) NOT NULL DEFAULT 0,
                hora_entrada TIME NULL,
                hora_saida TIME NULL,
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

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logistica_requisicoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(40) UNIQUE,
                origem VARCHAR(150) NOT NULL,
                destino VARCHAR(150) NOT NULL,
                item VARCHAR(180) NOT NULL,
                quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,
                unidade VARCHAR(20) NOT NULL DEFAULT 'un',
                prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',
                status VARCHAR(20) NOT NULL DEFAULT 'Pendente',
                data_requisicao DATE NOT NULL,
                responsavel VARCHAR(150) NULL,
                observacoes TEXT NULL,
                origem_modulo VARCHAR(40) NOT NULL DEFAULT 'logistica',
                categoria_item VARCHAR(40) NULL,
                valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,
                custo_total DECIMAL(14,2) NOT NULL DEFAULT 0,
                decidido_por VARCHAR(150) NULL,
                decidido_em DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oficina_pedido_materiais (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                item VARCHAR(180) NOT NULL,
                quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,
                unidade VARCHAR(20) NOT NULL DEFAULT 'un',
                observacoes VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pedido_id (pedido_id)
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
        $garantirColuna($pdo, 'oficina_ordens_servico', 'custo_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0');

        // Compatibilidade total: garante schema esperado para inserts manuais da Oficina
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'ativo_matricula', "VARCHAR(50) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'tipo_equipamento', "VARCHAR(150) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'descricao_avaria', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'localizacao', 'VARCHAR(150) NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'solicitante', 'VARCHAR(150) NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'data_pedido', 'DATE NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'prioridade', "VARCHAR(20) NOT NULL DEFAULT 'Normal'");
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'status', "VARCHAR(30) NOT NULL DEFAULT 'Pendente'");
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'custo_estimado', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'diagnostico_realizado', 'TINYINT(1) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'descricao_tecnica', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'equipa_diagnostico', 'VARCHAR(180) NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'resolve_interno', 'TINYINT(1) NOT NULL DEFAULT 1');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'motivo_externo', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'encaminhado_logistica_em', 'DATETIME NULL');
        $garantirColuna($pdo, 'oficina_pedidos_reparacao', 'encaminhado_logistica_por', 'VARCHAR(150) NULL');

        $garantirColuna($pdo, 'oficina_pedido_materiais', 'pedido_id', 'INT NOT NULL');
        $garantirColuna($pdo, 'oficina_pedido_materiais', 'item', 'VARCHAR(180) NOT NULL');
        $garantirColuna($pdo, 'oficina_pedido_materiais', 'quantidade', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'oficina_pedido_materiais', 'unidade', "VARCHAR(20) NOT NULL DEFAULT 'un'");
        $garantirColuna($pdo, 'oficina_pedido_materiais', 'observacoes', 'VARCHAR(255) NULL');

        $garantirColuna($pdo, 'oficina_manutencoes', 'ativo_matricula', "VARCHAR(50) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_manutencoes', 'tipo_equipamento', "VARCHAR(150) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_manutencoes', 'tipo_manutencao', "VARCHAR(80) NOT NULL DEFAULT 'Preventiva'");
        $garantirColuna($pdo, 'oficina_manutencoes', 'descricao_servico', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_manutencoes', 'solicitante', 'VARCHAR(150) NULL');
        $garantirColuna($pdo, 'oficina_manutencoes', 'data_manutencao', 'DATE NULL');
        $garantirColuna($pdo, 'oficina_manutencoes', 'prioridade', "VARCHAR(20) NOT NULL DEFAULT 'Normal'");
        $garantirColuna($pdo, 'oficina_manutencoes', 'status', "VARCHAR(30) NOT NULL DEFAULT 'Pendente'");
        $garantirColuna($pdo, 'oficina_manutencoes', 'custo_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0');

        $garantirColuna($pdo, 'oficina_historico_avarias', 'ativo_matricula', "VARCHAR(50) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_historico_avarias', 'tipo_equipamento', "VARCHAR(150) NOT NULL DEFAULT ''");
        $garantirColuna($pdo, 'oficina_historico_avarias', 'tipo_registo', "VARCHAR(30) NOT NULL DEFAULT 'AVARIA'");
        $garantirColuna($pdo, 'oficina_historico_avarias', 'descricao', 'TEXT NULL');
        $garantirColuna($pdo, 'oficina_historico_avarias', 'data_evento', 'DATE NULL');
        $garantirColuna($pdo, 'oficina_historico_avarias', 'origem_tipo', 'VARCHAR(40) NULL');
        $garantirColuna($pdo, 'oficina_historico_avarias', 'origem_id', 'INT NULL');

        $garantirColuna($pdo, 'oficina_presencas_rh', 'assinou_entrada', 'TINYINT(1) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'oficina_presencas_rh', 'assinou_saida', 'TINYINT(1) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'oficina_presencas_rh', 'hora_entrada', 'TIME NULL');
        $garantirColuna($pdo, 'oficina_presencas_rh', 'hora_saida', 'TIME NULL');
        $garantirColuna($pdo, 'oficina_presencas_rh', 'lista_fisica_anexo', 'VARCHAR(255) NULL');

        $garantirColuna($pdo, 'logistica_requisicoes', 'origem_modulo', "VARCHAR(40) NOT NULL DEFAULT 'logistica'");
        $garantirColuna($pdo, 'logistica_requisicoes', 'categoria_item', 'VARCHAR(40) NULL');
        $garantirColuna($pdo, 'logistica_requisicoes', 'valor_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'logistica_requisicoes', 'custo_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $garantirColuna($pdo, 'logistica_requisicoes', 'decidido_por', 'VARCHAR(150) NULL');
        $garantirColuna($pdo, 'logistica_requisicoes', 'decidido_em', 'DATETIME NULL');
    } catch (PDOException $e) {
        throw new RuntimeException('Nao foi possivel atualizar a estrutura legada da oficina.');
    }
}

function criarOrdemServicoAutomatica(PDO $pdo, array $dados): array {
    $stmt = $pdo->prepare("
        INSERT INTO oficina_ordens_servico
            (origem_tipo, origem_id, ativo_matricula, tipo_equipamento, descricao_servico, data_abertura, prioridade, status_os, custo_total)
        VALUES
            (:origem_tipo, :origem_id, :ativo_matricula, :tipo_equipamento, :descricao_servico, :data_abertura, :prioridade, :status_os, :custo_total)
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
        'custo_total' => (float)($dados['custo_total'] ?? 0),
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

function itemDisponivelNoArmazem(PDO $pdo, string $item): ?array {
    $termo = trim((string)$item);
    if ($termo === '') return null;
    try {
        $stmt = $pdo->prepare("
            SELECT codigo, nome, unidade, stock_atual
            FROM logistica_pecas
            WHERE stock_atual > 0
              AND (
                LOWER(TRIM(nome)) = LOWER(TRIM(:item))
                OR LOWER(TRIM(COALESCE(codigo, ''))) = LOWER(TRIM(:item))
              )
            ORDER BY stock_atual DESC
            LIMIT 1
        ");
        $stmt->execute(['item' => $termo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ?: null;
    } catch (Throwable $e) {
        // Se a tabela de stock nao existir neste ambiente, nao bloqueia o fluxo.
        return null;
    }
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
            COALESCE(p.custo_estimado, 0) AS custo_estimado,
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
                    'custo_total' => (float)($p['custo_estimado'] ?? 0),
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
    $erro_requisicoes = $e->getMessage();
    $erro_manutencao = $e->getMessage();
    $erro_avarias = $e->getMessage();
    $erro_relatorios = $e->getMessage();
    $erro_presencas = $e->getMessage();
}

if ($view === 'ordens_servico' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_os_manual') {
    try {
        $matricula = trim((string)($_POST['matricula'] ?? ''));
        $equipamento = trim((string)($_POST['equipamento'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
        $dataEntrada = trim((string)($_POST['data_entrada'] ?? ''));
        $custoTotal = (float)($_POST['custo_total'] ?? 0);

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
            'custo_total' => $custoTotal,
        ]);
        header("Location: ?tab={$tab}&view=ordens_servico&mode=list&saved_os=1");
        exit;
    } catch (Throwable $e) {
        $erro_os = "Nao foi possivel criar a ordem de servico: " . $e->getMessage();
    }
}

if ($view === 'pedidos_reparacao') {
    $filtro_pedidos_datas['inicio'] = trim((string)($_GET['pf_data_inicio'] ?? ''));
    $filtro_pedidos_datas['fim'] = trim((string)($_GET['pf_data_fim'] ?? ''));
    if ($filtro_pedidos_datas['inicio'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_pedidos_datas['inicio'])) {
        $filtro_pedidos_datas['inicio'] = '';
    }
    if ($filtro_pedidos_datas['fim'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_pedidos_datas['fim'])) {
        $filtro_pedidos_datas['fim'] = '';
    }
    if ($filtro_pedidos_datas['inicio'] === '' && $filtro_pedidos_datas['fim'] === '') {
        $filtro_pedidos_datas['inicio'] = $inicioSemanaOficina;
        $filtro_pedidos_datas['fim'] = $fimSemanaOficina;
    }

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
                $custo_estimado = (float)($_POST['custo_estimado'] ?? 0);

                if ($ativo_matricula === '' || $tipo_equipamento === '' || $descricao_avaria === '' || $data_pedido === '') {
                    throw new RuntimeException('Preencha os campos obrigatorios do pedido de reparacao.');
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO oficina_pedidos_reparacao
                        (ativo_matricula, tipo_equipamento, descricao_avaria, localizacao, solicitante, data_pedido, prioridade, status, custo_estimado)
                    VALUES
                        (:ativo_matricula, :tipo_equipamento, :descricao_avaria, :localizacao, :solicitante, :data_pedido, :prioridade, :status, :custo_estimado)
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
                    'custo_estimado' => $custo_estimado,
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
                    'custo_total' => $custo_estimado,
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
                $stmtAtual = $pdo->prepare("SELECT status FROM oficina_pedidos_reparacao WHERE id = :id LIMIT 1");
                $stmtAtual->execute(['id' => $id]);
                $statusAtualRaw = (string)($stmtAtual->fetchColumn() ?: '');
                $statusAtual = normalizarStatusPedido($statusAtualRaw);

                if ($statusAtual === '') {
                    throw new RuntimeException("Pedido #{$id} nao encontrado.");
                }

                if ($acao === 'aceitar') {
                    if ($statusAtual !== 'pendente') {
                        throw new RuntimeException("Pedido #{$id} ja foi processado e nao pode ser aceito novamente.");
                    }
                    $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Aceito' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Aceito' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $id]);
                    header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=detalhe&id={$id}");
                    exit;
                } elseif ($acao === 'andamento') {
                    if ($statusAtual !== 'aceito') {
                        throw new RuntimeException("Pedido #{$id} precisa estar aceito para entrar em andamento.");
                    }
                    $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Em andamento' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Em andamento' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $id]);
                    $msg_pedidos = "Pedido #{$id} colocado em andamento.";
                } else {
                    if ($statusAtual !== 'em_andamento') {
                        throw new RuntimeException("Pedido #{$id} precisa estar em andamento para ser resolvido.");
                    }
                    $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Resolvido' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Fechado' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $id]);
                    $msg_pedidos = "Pedido #{$id} marcado como resolvido.";
                }
            }

            if (in_array($acao, ['aceitar_detalhe', 'salvar_diagnostico_detalhe', 'adicionar_material_detalhe', 'enviar_logistica_detalhe'], true)) {
                $pedidoId = (int)($_POST['pedido_id'] ?? 0);
                if ($pedidoId <= 0) {
                    throw new RuntimeException('Pedido de reparacao invalido.');
                }

                $stmtPedido = $pdo->prepare("
                    SELECT id, status, ativo_matricula, tipo_equipamento, prioridade, descricao_avaria, COALESCE(diagnostico_realizado, 0) AS diagnostico_realizado
                    FROM oficina_pedidos_reparacao
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmtPedido->execute(['id' => $pedidoId]);
                $pedidoRow = $stmtPedido->fetch(PDO::FETCH_ASSOC);
                if (!$pedidoRow) {
                    throw new RuntimeException('Pedido de reparacao nao encontrado.');
                }

                if ($acao === 'aceitar_detalhe') {
                    $statusAtual = normalizarStatusPedido((string)($pedidoRow['status'] ?? ''));
                    if ($statusAtual === 'pendente') {
                        $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Aceito' WHERE id = :id")->execute(['id' => $pedidoId]);
                        $pdo->prepare("UPDATE oficina_ordens_servico SET status_os = 'Aceito' WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :id")->execute(['id' => $pedidoId]);
                        $msg_pedidos = "Pedido #{$pedidoId} aceito com sucesso.";
                    } else {
                        $msg_pedidos = "Pedido #{$pedidoId} ja estava em estado processado.";
                    }
                    header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=detalhe&id={$pedidoId}");
                    exit;
                }

                if ($acao === 'salvar_diagnostico_detalhe') {
                    $descricaoTecnica = trim((string)($_POST['descricao_tecnica'] ?? ''));
                    $diagFeito = isset($_POST['diagnostico_realizado']) ? 1 : 0;
                    $equipaDiagnostico = trim((string)($_POST['equipa_diagnostico'] ?? ''));
                    if ($descricaoTecnica !== '') {
                        $diagFeito = 1;
                    }
                    if ($equipaDiagnostico === '') {
                        throw new RuntimeException('Informe a equipa responsavel pelo diagnostico.');
                    }
                    $pdo->prepare("
                        UPDATE oficina_pedidos_reparacao
                        SET diagnostico_realizado = :diag,
                            descricao_tecnica = :descricao,
                            equipa_diagnostico = :equipa
                        WHERE id = :id
                    ")->execute([
                        'diag' => $diagFeito,
                        'descricao' => $descricaoTecnica !== '' ? $descricaoTecnica : null,
                        'equipa' => $equipaDiagnostico,
                        'id' => $pedidoId
                    ]);
                    $msg_pedidos = "Diagnostico tecnico atualizado no pedido #{$pedidoId}.";
                    header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=detalhe&id={$pedidoId}");
                    exit;
                }

                if ($acao === 'adicionar_material_detalhe') {
                    $item = trim((string)($_POST['material_item'] ?? ''));
                    $quantidade = (float)($_POST['material_quantidade'] ?? 0);
                    $unidade = trim((string)($_POST['material_unidade'] ?? 'un'));
                    $obsMaterial = trim((string)($_POST['material_observacoes'] ?? ''));
                    if ($item === '' || $quantidade <= 0) {
                        throw new RuntimeException('Informe material e quantidade valida.');
                    }
                    $pdo->prepare("
                        INSERT INTO oficina_pedido_materiais
                            (pedido_id, item, quantidade, unidade, observacoes)
                        VALUES
                            (:pedido_id, :item, :quantidade, :unidade, :observacoes)
                    ")->execute([
                        'pedido_id' => $pedidoId,
                        'item' => $item,
                        'quantidade' => $quantidade,
                        'unidade' => $unidade !== '' ? $unidade : 'un',
                        'observacoes' => $obsMaterial !== '' ? $obsMaterial : null,
                    ]);
                    $msg_pedidos = "Material adicionado ao pedido #{$pedidoId}.";
                    header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=detalhe&id={$pedidoId}");
                    exit;
                }

                if ($acao === 'enviar_logistica_detalhe') {
                    $materiaisStmt = $pdo->prepare("
                        SELECT item, quantidade, unidade, observacoes
                        FROM oficina_pedido_materiais
                        WHERE pedido_id = :pedido_id
                        ORDER BY id ASC
                    ");
                    $materiaisStmt->execute(['pedido_id' => $pedidoId]);
                    $materiais = $materiaisStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (count($materiais) === 0) {
                        throw new RuntimeException('Adicione pelo menos um material necessario antes de enviar para Logistica.');
                    }
                    $bloqueados = [];
                    foreach ($materiais as $m) {
                        $itemMaterial = (string)($m['item'] ?? '');
                        $disp = itemDisponivelNoArmazem($pdo, $itemMaterial);
                        if ($disp) {
                            $bloqueados[] = $itemMaterial . ' (stock: ' . number_format((float)($disp['stock_atual'] ?? 0), 2, ',', '.') . ' ' . (string)($disp['unidade'] ?? 'un') . ')';
                        }
                    }
                    if (!empty($bloqueados)) {
                        throw new RuntimeException('Nao foi enviado para Logistica: material ja existe no armazem. Itens: ' . implode('; ', $bloqueados));
                    }

                    $diagTexto = trim((string)($pedidoRow['descricao_avaria'] ?? ''));
                    $diagTecnicoStmt = $pdo->prepare("SELECT descricao_tecnica FROM oficina_pedidos_reparacao WHERE id = :id");
                    $diagTecnicoStmt->execute(['id' => $pedidoId]);
                    $diagTecnico = trim((string)($diagTecnicoStmt->fetchColumn() ?: ''));
                    $diagMetaStmt = $pdo->prepare("SELECT equipa_diagnostico FROM oficina_pedidos_reparacao WHERE id = :id");
                    $diagMetaStmt->execute(['id' => $pedidoId]);
                    $diagMeta = $diagMetaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $diagFeito = (int)($pedidoRow['diagnostico_realizado'] ?? 0) === 1;
                    if (!$diagFeito || $diagTecnico === '') {
                        throw new RuntimeException('Marque o diagnostico e preencha a descricao tecnica antes de enviar para Logistica.');
                    }
                    $descricaoTecnicaFinal = $diagTecnico !== '' ? $diagTecnico : $diagTexto;
                    $equipaDiagFinal = trim((string)($diagMeta['equipa_diagnostico'] ?? ''));

                    $pdo->beginTransaction();
                    foreach ($materiais as $m) {
                        $obsReq = trim((string)($m['observacoes'] ?? ''));
                        $observacoesReq = 'Pedido Oficina #' . $pedidoId . ' | Matricula: ' . (string)($pedidoRow['ativo_matricula'] ?? '-') . ' | Diagnostico: ' . $descricaoTecnicaFinal;
                        if ($obsReq !== '') {
                            $observacoesReq .= ' | Material Obs: ' . $obsReq;
                        }
                        $observacoesReq .= ' [PEDIDO_OFICINA_ID:' . $pedidoId . ']';

                        $stmtReq = $pdo->prepare("
                            INSERT INTO logistica_requisicoes
                                (origem, destino, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes, origem_modulo, categoria_item, valor_total)
                            VALUES
                                ('Oficina', 'Logistica', :item, :quantidade, :unidade, :prioridade, 'Pendente', :data_requisicao, :responsavel, :observacoes, 'oficina', 'Peca', 0)
                        ");
                        $stmtReq->execute([
                            'item' => (string)($m['item'] ?? ''),
                            'quantidade' => (float)($m['quantidade'] ?? 0),
                            'unidade' => (string)($m['unidade'] ?? 'un'),
                            'prioridade' => (string)($pedidoRow['prioridade'] ?? 'Normal'),
                            'data_requisicao' => date('Y-m-d'),
                            'responsavel' => (string)($_SESSION['usuario_nome'] ?? 'Oficina'),
                            'observacoes' => $observacoesReq,
                        ]);

                        $reqId = (int)$pdo->lastInsertId();
                        $codigoReq = sprintf('REQ-OF-%s-%04d', date('Y'), $reqId);
                        $pdo->prepare("UPDATE logistica_requisicoes SET codigo = :codigo WHERE id = :id")
                            ->execute(['codigo' => $codigoReq, 'id' => $reqId]);
                    }

                    $linhasMateriaisOs = [];
                    foreach ($materiais as $m) {
                        $linhasMateriaisOs[] = '- ' . (string)($m['item'] ?? '') . ' | ' . number_format((float)($m['quantidade'] ?? 0), 2, ',', '.') . ' ' . (string)($m['unidade'] ?? 'un');
                    }
                    $blocoOs = "Diagnostico tecnico: " . $descricaoTecnicaFinal
                        . "\nEquipa responsavel: " . ($equipaDiagFinal !== '' ? $equipaDiagFinal : 'Nao informado')
                        . "\nMateriais necessarios:\n" . implode("\n", $linhasMateriaisOs);

                    $stmtOsPedido = $pdo->prepare("
                        SELECT id, descricao_servico
                        FROM oficina_ordens_servico
                        WHERE origem_tipo = 'PEDIDO_REPARACAO' AND origem_id = :origem_id
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmtOsPedido->execute(['origem_id' => $pedidoId]);
                    $osPedido = $stmtOsPedido->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($osPedido) {
                        $descAtual = trim((string)($osPedido['descricao_servico'] ?? ''));
                        $descNova = trim($descAtual . "\n\n" . $blocoOs);
                        $pdo->prepare("
                            UPDATE oficina_ordens_servico
                            SET descricao_servico = :descricao_servico,
                                status_os = 'Aguardando Pecas'
                            WHERE id = :id
                        ")->execute([
                            'descricao_servico' => $descNova,
                            'id' => (int)$osPedido['id']
                        ]);
                    }

                    $pdo->prepare("
                        UPDATE oficina_pedidos_reparacao
                        SET status = 'Aguardando Logistica Externa',
                            encaminhado_logistica_em = NOW(),
                            encaminhado_logistica_por = :usuario
                        WHERE id = :id
                    ")->execute([
                        'usuario' => (string)($_SESSION['usuario_nome'] ?? 'Oficina'),
                        'id' => $pedidoId
                    ]);
                    $pdo->commit();

                    $msg_pedidos = "Pedido #{$pedidoId} enviado para Logistica com os materiais necessarios.";
                    $returnMode = trim((string)($_POST['return_mode'] ?? 'detalhe'));
                    if ($returnMode === 'list') {
                        header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=list");
                    } else {
                        header("Location: ?tab={$tab}&view=pedidos_reparacao&mode=detalhe&id={$pedidoId}&detalhe_lista=requisicoes");
                    }
                    exit;
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
        $where = [];
        $params = [];
        if ($filtro_pedidos_datas['inicio'] !== '') {
            $where[] = "data_pedido >= :data_inicio";
            $params['data_inicio'] = $filtro_pedidos_datas['inicio'];
        }
        if ($filtro_pedidos_datas['fim'] !== '') {
            $where[] = "data_pedido <= :data_fim";
            $params['data_fim'] = $filtro_pedidos_datas['fim'];
        }
        $sql = "
            SELECT id, ativo_matricula, tipo_equipamento, descricao_avaria, localizacao, solicitante, data_pedido, prioridade, status, custo_estimado
            FROM oficina_pedidos_reparacao
        ";
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pedidos_reparacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_pedidos = "Nao foi possivel carregar pedidos de reparacao.";
    }

    if ($mode === 'detalhe') {
        $detalhe_lista = 'ambos';
        $pedidoIdDetalhe = (int)($_GET['id'] ?? 0);
        if ($pedidoIdDetalhe <= 0) {
            $erro_pedidos = 'Pedido de reparacao invalido para detalhe.';
            $mode = 'list';
        } else {
            $stmtDetalhe = $pdo->prepare("
                SELECT id, ativo_matricula, tipo_equipamento, descricao_avaria, localizacao, solicitante, data_pedido, prioridade, status, custo_estimado,
                       COALESCE(diagnostico_realizado, 0) AS diagnostico_realizado,
                       COALESCE(descricao_tecnica, '') AS descricao_tecnica,
                       encaminhado_logistica_em, encaminhado_logistica_por
                FROM oficina_pedidos_reparacao
                WHERE id = :id
                LIMIT 1
            ");
            $stmtDetalhe->execute(['id' => $pedidoIdDetalhe]);
            $pedido_reparacao_detalhe = $stmtDetalhe->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$pedido_reparacao_detalhe) {
                $erro_pedidos = 'Pedido de reparacao nao encontrado.';
                $mode = 'list';
            } else {
                $stmtMateriais = $pdo->prepare("
                    SELECT id, item, quantidade, unidade, observacoes, created_at
                    FROM oficina_pedido_materiais
                    WHERE pedido_id = :pedido_id
                    ORDER BY id DESC
                ");
                $stmtMateriais->execute(['pedido_id' => $pedidoIdDetalhe]);
                $materiais_pedido_detalhe = $stmtMateriais->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmtReqDetalhe = $pdo->prepare("
                    SELECT id, codigo, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes
                    FROM logistica_requisicoes
                    WHERE origem_modulo = 'oficina'
                      AND observacoes LIKE :marcador
                    ORDER BY id DESC
                ");
                $stmtReqDetalhe->execute(['marcador' => '%[PEDIDO_OFICINA_ID:' . $pedidoIdDetalhe . ']%']);
                $requisicoes_pedido_detalhe = $stmtReqDetalhe->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    }
}

if ($view === 'requisicoes') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_requisicao_oficina') {
        try {
            $categoria_item = trim((string)($_POST['categoria_item'] ?? 'Peca'));
            $item = trim((string)($_POST['item'] ?? ''));
            $quantidade = (float)($_POST['quantidade'] ?? 0);
            $unidade = trim((string)($_POST['unidade'] ?? 'un'));
            $prioridade = trim((string)($_POST['prioridade'] ?? 'Normal'));
            $data_requisicao = trim((string)($_POST['data_requisicao'] ?? date('Y-m-d')));
            $responsavel = trim((string)($_POST['responsavel'] ?? (string)($_SESSION['usuario_nome'] ?? '')));
            $observacoes = trim((string)($_POST['observacoes'] ?? ''));

            if ($item === '' || $quantidade <= 0) {
                throw new RuntimeException('Preencha os campos obrigatorios da requisicao.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_requisicao)) {
                throw new RuntimeException('Data de requisicao invalida.');
            }
            $itemArmazem = itemDisponivelNoArmazem($pdo, $item);
            if ($itemArmazem) {
                throw new RuntimeException(
                    'Pedido bloqueado: este item ja existe no armazem com stock disponivel (' .
                    number_format((float)($itemArmazem['stock_atual'] ?? 0), 2, ',', '.') . ' ' .
                    (string)($itemArmazem['unidade'] ?? 'un') . ').'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO logistica_requisicoes
                    (origem, destino, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes, origem_modulo, categoria_item, valor_total)
                VALUES
                    ('Oficina', 'Logistica', :item, :quantidade, :unidade, :prioridade, 'Pendente', :data_requisicao, :responsavel, :observacoes, 'oficina', :categoria_item, 0)
            ");
            $stmt->execute([
                'item' => $item,
                'quantidade' => $quantidade,
                'unidade' => $unidade !== '' ? $unidade : 'un',
                'prioridade' => $prioridade !== '' ? $prioridade : 'Normal',
                'data_requisicao' => $data_requisicao,
                'responsavel' => $responsavel !== '' ? $responsavel : null,
                'observacoes' => $observacoes !== '' ? $observacoes : null,
                'categoria_item' => $categoria_item !== '' ? $categoria_item : 'Peca',
            ]);

            $id = (int)$pdo->lastInsertId();
            $codigo = sprintf('REQ-OF-%s-%04d', date('Y'), $id);
            $pdo->prepare("UPDATE logistica_requisicoes SET codigo = :codigo WHERE id = :id")
                ->execute(['codigo' => $codigo, 'id' => $id]);

            header("Location: ?tab={$tab}&view=requisicoes&mode=list&saved_req=1");
            exit;
        } catch (Throwable $e) {
            $erro_requisicoes = "Nao foi possivel criar a requisicao: " . $e->getMessage();
        }
    }

    if (isset($_GET['saved_req']) && $_GET['saved_req'] === '1') {
        $msg_requisicoes = 'Requisicao enviada para Logistica com sucesso.';
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, codigo, categoria_item, item, quantidade, unidade, prioridade, status, data_requisicao, responsavel, observacoes, COALESCE(valor_total, custo_total, 0) AS valor_total, decidido_por, decidido_em
            FROM logistica_requisicoes
            WHERE origem_modulo = 'oficina'
              AND data_requisicao BETWEEN :inicio_semana AND :fim_semana
            ORDER BY id DESC
        ");
        $stmt->execute([
            'inicio_semana' => $inicioSemanaOficina,
            'fim_semana' => $fimSemanaOficina,
        ]);
        $requisicoes_oficina = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $erro_requisicoes = 'Nao foi possivel carregar as requisicoes da oficina.';
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
            $custo_total = (float)($_POST['custo_total'] ?? 0);

            if ($ativo_matricula === '' || $tipo_equipamento === '' || $tipo_manutencao === '' || $data_manutencao === '') {
                throw new RuntimeException('Preencha os campos obrigatorios da manutencao.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO oficina_manutencoes
                    (ativo_matricula, tipo_equipamento, tipo_manutencao, descricao_servico, solicitante, data_manutencao, prioridade, status, custo_total)
                VALUES
                    (:ativo_matricula, :tipo_equipamento, :tipo_manutencao, :descricao_servico, :solicitante, :data_manutencao, :prioridade, :status, :custo_total)
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
                'custo_total' => $custo_total,
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
                'custo_total' => $custo_total,
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
        $stmt = $pdo->prepare("
            SELECT id, ativo_matricula, tipo_equipamento, tipo_manutencao, descricao_servico, solicitante, data_manutencao, prioridade, status, custo_total
            FROM oficina_manutencoes
            WHERE data_manutencao BETWEEN :inicio_semana AND :fim_semana
            ORDER BY id DESC
        ");
        $stmt->execute([
            'inicio_semana' => $inicioSemanaOficina,
            'fim_semana' => $fimSemanaOficina,
        ]);
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
        $stmt = $pdo->prepare("
            SELECT id, ativo_matricula, tipo_equipamento, tipo_registo, descricao, data_evento, origem_tipo
            FROM oficina_historico_avarias
            WHERE tipo_registo = 'AVARIA'
              AND data_evento BETWEEN :inicio_semana AND :fim_semana
            ORDER BY data_evento DESC, id DESC
        ");
        $stmt->execute([
            'inicio_semana' => $inicioSemanaOficina,
            'fim_semana' => $fimSemanaOficina,
        ]);
        $avarias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_avarias = "Nao foi possivel carregar avarias.";
    }
}

if ($view === 'ordens_servico') {
    $filtro_os_datas['inicio'] = trim((string)($_GET['os_data_inicio'] ?? ''));
    $filtro_os_datas['fim'] = trim((string)($_GET['os_data_fim'] ?? ''));
    if ($filtro_os_datas['inicio'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_os_datas['inicio'])) {
        $filtro_os_datas['inicio'] = '';
    }
    if ($filtro_os_datas['fim'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_os_datas['fim'])) {
        $filtro_os_datas['fim'] = '';
    }
    if ($filtro_os_datas['inicio'] === '' && $filtro_os_datas['fim'] === '') {
        $filtro_os_datas['inicio'] = $inicioSemanaOficina;
        $filtro_os_datas['fim'] = $fimSemanaOficina;
    }

    if (isset($_GET['saved_os']) && $_GET['saved_os'] === '1') {
        $msg_os = 'Ordem de servico criada com sucesso.';
    }

    try {
        $where = [];
        $params = [];
        if ($filtro_os_datas['inicio'] !== '') {
            $where[] = "DATE(data_abertura) >= :data_inicio";
            $params['data_inicio'] = $filtro_os_datas['inicio'];
        }
        if ($filtro_os_datas['fim'] !== '') {
            $where[] = "DATE(data_abertura) <= :data_fim";
            $params['data_fim'] = $filtro_os_datas['fim'];
        }
        $sql = "
            SELECT id, codigo_os, origem_tipo, origem_id, ativo_matricula, tipo_equipamento, descricao_servico, data_abertura, prioridade, status_os, custo_total
            FROM oficina_ordens_servico
        ";
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ordens_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_os = "Nao foi possivel carregar ordens de servico.";
    }
}

if ($view === 'presencas') {
    if (!function_exists('processarAnexoListaFisicaOficina')) {
        function processarAnexoListaFisicaOficina(string $campo, string $subPasta): array {
            if (!isset($_FILES[$campo])) return ['ok' => false, 'error' => 'Selecione um ficheiro para anexar.'];
            $f = $_FILES[$campo];
            $nome = is_array($f['name'] ?? null) ? (string) (($f['name'][0] ?? '')) : (string) ($f['name'] ?? '');
            $tmp = is_array($f['tmp_name'] ?? null) ? (string) (($f['tmp_name'][0] ?? '')) : (string) ($f['tmp_name'] ?? '');
            $err = is_array($f['error'] ?? null) ? (int) (($f['error'][0] ?? UPLOAD_ERR_NO_FILE)) : (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            $size = is_array($f['size'] ?? null) ? (int) (($f['size'][0] ?? 0)) : (int) ($f['size'] ?? 0);

            if ($err !== UPLOAD_ERR_OK || $nome === '' || $tmp === '') {
                return ['ok' => false, 'error' => 'Selecione um ficheiro valido para anexar.'];
            }
            if ($size <= 0 || $size > (10 * 1024 * 1024)) {
                return ['ok' => false, 'error' => 'Ficheiro invalido ou acima de 10MB.'];
            }

            $ext = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
            $permitidos = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $permitidos, true)) {
                return ['ok' => false, 'error' => 'Formato nao permitido. Use PDF/JPG/PNG/WEBP/GIF.'];
            }

            $baseDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . trim($subPasta, '\\/');
            if (!is_dir($baseDir) && !@mkdir($baseDir, 0777, true)) {
                return ['ok' => false, 'error' => 'Nao foi possivel criar diretorio de anexos.'];
            }

            $baseNome = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) pathinfo($nome, PATHINFO_FILENAME));
            if ($baseNome === '') $baseNome = 'anexo';
            $nomeFinal = date('Ymd_His') . '_' . $baseNome . '.' . $ext;
            $destinoAbs = $baseDir . DIRECTORY_SEPARATOR . $nomeFinal;
            if (!@move_uploaded_file($tmp, $destinoAbs)) {
                return ['ok' => false, 'error' => 'Nao foi possivel salvar o anexo no servidor.'];
            }

            return ['ok' => true, 'path' => 'uploads/' . trim($subPasta, '\\/') . '/' . $nomeFinal];
        }
    }

    if (isset($_GET['doc']) && in_array((string) $_GET['doc'], ['presenca_pdf', 'presenca_excel', 'presenca_word'], true)) {
        $docTipoPres = (string) ($_GET['doc'] ?? 'presenca_pdf');
        $dataDoc = trim((string) ($_GET['data_presenca'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataDoc)) {
            http_response_code(400);
            echo 'Data invalida para lista de presenca.';
            exit;
        }

        $docStmt = $pdo->prepare("
            SELECT p.nome AS colaborador, c.nome AS cargo_nome, apr.hora_entrada, apr.hora_saida, apr.status_presenca
            FROM oficina_presencas_rh apr
            INNER JOIN pessoal p ON p.id = apr.pessoal_id
            LEFT JOIN cargos c ON c.id = p.cargo_id
            WHERE apr.data_presenca = :data_presenca
            ORDER BY p.nome ASC
        ");
        $docStmt->execute([':data_presenca' => $dataDoc]);
        $rowsDoc = $docStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($docTipoPres === 'presenca_excel') {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="lista_presencas_oficina_' . $dataDoc . '.xls"');
        } elseif ($docTipoPres === 'presenca_word') {
            header('Content-Type: application/msword; charset=UTF-8');
            header('Content-Disposition: attachment; filename="lista_presencas_oficina_' . $dataDoc . '.doc"');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!doctype html><html><head><meta charset="utf-8"><title>Lista de Presencas - Oficina</title>
        <style>
            body{font-family:Arial,sans-serif;color:#111}
            .head{display:flex;justify-content:space-between;align-items:center;border:2px solid #111;padding:10px;border-radius:8px;margin-bottom:12px}
            .title{font-size:18px;font-weight:800}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #cbd5e1;padding:7px;text-align:left;font-size:12px}
            th{background:#111;color:#f4b400}
            tr:nth-child(even) td{background:#fff8e1}
        </style></head><body>';
        echo '<div class="head"><div class="title">Lista de Presencas - Oficina</div><div>Data: ' . htmlspecialchars(date('d/m/Y', strtotime($dataDoc))) . '</div></div>';
        echo '<table><thead><tr><th>Funcionario</th><th>Cargo</th><th>Entrada</th><th>Saida</th><th>Estado</th></tr></thead><tbody>';
        if (empty($rowsDoc)) {
            echo '<tr><td colspan="5">Sem registos para esta data.</td></tr>';
        } else {
            foreach ($rowsDoc as $r) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) ($r['colaborador'] ?? '-')) . '</td>';
                echo '<td>' . htmlspecialchars((string) ($r['cargo_nome'] ?? '-')) . '</td>';
                echo '<td>' . htmlspecialchars(!empty($r['hora_entrada']) ? substr((string) $r['hora_entrada'], 0, 5) : '-') . '</td>';
                echo '<td>' . htmlspecialchars(!empty($r['hora_saida']) ? substr((string) $r['hora_saida'], 0, 5) : '-') . '</td>';
                echo '<td>' . htmlspecialchars((string) ($r['status_presenca'] ?? '-')) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>' . ($docTipoPres === 'presenca_pdf' ? '<script>window.print();</script>' : '') . '</body></html>';
        exit;
    }

    try {
        $stmtCol = $pdo->query("
            SELECT p.id, p.numero, p.nome, c.nome AS cargo_nome
            FROM pessoal p
            LEFT JOIN cargos c ON c.id = p.cargo_id
            WHERE p.estado = 'Activo'
              AND (
                LOWER(COALESCE(c.nome, '')) LIKE '%mec%'
                OR LOWER(COALESCE(c.nome, '')) LIKE '%electric%'
                OR LOWER(COALESCE(c.nome, '')) LIKE '%pintor%'
                OR LOWER(COALESCE(c.nome, '')) LIKE '%oficina%'
                OR LOWER(COALESCE(c.nome, '')) LIKE '%bate chapa%'
                OR LOWER(COALESCE(c.nome, '')) LIKE '%serralh%'
              )
            ORDER BY p.nome ASC
        ");
        $colaboradores_oficina = $stmtCol->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (count($colaboradores_oficina) === 0) {
            $stmtFallback = $pdo->query("
                SELECT p.id, p.numero, p.nome, c.nome AS cargo_nome
                FROM pessoal p
                LEFT JOIN cargos c ON c.id = p.cargo_id
                WHERE p.estado = 'Activo'
                ORDER BY p.nome ASC
            ");
            $colaboradores_oficina = $stmtFallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $erro_presencas = 'Nao foi possivel carregar funcionarios da oficina.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_presencas'])) {
        $acao = trim((string) $_POST['acao_presencas']);
        try {
            if ($acao === 'marcar_presenca_lote') {
                $dataPresenca = trim((string) ($_POST['data_presenca'] ?? date('Y-m-d')));
                $obsLote = $_POST['obs_lote'] ?? [];
                $entradaLote = $_POST['entrada_lote'] ?? [];
                $saidaLote = $_POST['saida_lote'] ?? [];
                $horaEntradaLote = $_POST['hora_entrada_lote'] ?? [];
                $horaSaidaLote = $_POST['hora_saida_lote'] ?? [];

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataPresenca)) {
                    throw new RuntimeException('Data de presenca invalida.');
                }

                $bloqStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM oficina_presencas_rh
                    WHERE data_presenca = :data_presenca
                      AND enviado_rh = 1
                ");
                $bloqStmt->execute([':data_presenca' => $dataPresenca]);
                if ((int) ($bloqStmt->fetchColumn() ?: 0) > 0) {
                    throw new RuntimeException('Esta lista ja foi enviada ao RH e esta bloqueada para edicao.');
                }

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
                    (data_presenca, pessoal_id, status_presenca, assinou_entrada, assinou_saida, hora_entrada, hora_saida, observacoes, enviado_rh, enviado_em, criado_por)
                    VALUES
                    (:data_presenca, :pessoal_id, :status_presenca, :assinou_entrada, :assinou_saida, :hora_entrada, :hora_saida, :observacoes, 0, NULL, :criado_por)
                ");
                $stmtUpdate = $pdo->prepare("
                    UPDATE oficina_presencas_rh
                    SET status_presenca = :status_presenca,
                        assinou_entrada = :assinou_entrada,
                        assinou_saida = :assinou_saida,
                        hora_entrada = :hora_entrada,
                        hora_saida = :hora_saida,
                        observacoes = :observacoes,
                        enviado_rh = 0,
                        enviado_em = NULL
                    WHERE id = :id
                ");

                foreach ($colaboradores_oficina as $colaborador) {
                    $pessoalId = (int) ($colaborador['id'] ?? 0);
                    if ($pessoalId <= 0) continue;
                    $pidRaw = (string) $pessoalId;

                    $assinouEntrada = is_array($entradaLote) && array_key_exists($pidRaw, $entradaLote) ? 1 : 0;
                    $assinouSaida = is_array($saidaLote) && array_key_exists($pidRaw, $saidaLote) ? 1 : 0;
                    $statusPresenca = statusAssiduidadePorAssinatura($assinouEntrada, $assinouSaida);
                    $horaIn = is_array($horaEntradaLote) ? trim((string) ($horaEntradaLote[$pidRaw] ?? '')) : '';
                    $horaOut = is_array($horaSaidaLote) ? trim((string) ($horaSaidaLote[$pidRaw] ?? '')) : '';
                    if ($assinouEntrada === 1 && $horaIn === '') $horaIn = '07:00';
                    if ($assinouSaida === 1 && $horaOut === '') $horaOut = '16:00';
                    $obs = is_array($obsLote) ? trim((string) ($obsLote[$pidRaw] ?? '')) : '';

                    $stmtBusca->execute([
                        ':data_presenca' => $dataPresenca,
                        ':pessoal_id' => $pessoalId,
                    ]);
                    $existenteId = (int) ($stmtBusca->fetchColumn() ?: 0);

                    $params = [
                        ':status_presenca' => $statusPresenca,
                        ':assinou_entrada' => $assinouEntrada,
                        ':assinou_saida' => $assinouSaida,
                        ':hora_entrada' => $assinouEntrada === 1 && preg_match('/^\d{2}:\d{2}$/', $horaIn) ? ($horaIn . ':00') : null,
                        ':hora_saida' => $assinouSaida === 1 && preg_match('/^\d{2}:\d{2}$/', $horaOut) ? ($horaOut . ':00') : null,
                        ':observacoes' => $obs !== '' ? $obs : null,
                    ];

                    if ($existenteId > 0) {
                        $params[':id'] = $existenteId;
                        $stmtUpdate->execute($params);
                    } else {
                        $params[':data_presenca'] = $dataPresenca;
                        $params[':pessoal_id'] = $pessoalId;
                        $params[':criado_por'] = (int) ($_SESSION['usuario_id'] ?? 0);
                        $stmtInsert->execute($params);
                    }
                }

                header("Location: ?tab={$tab}&view=presencas&mode=list&data_assiduidade=" . urlencode($dataPresenca) . "&saved_assiduidade_lote=1");
                exit;
            }

            if ($acao === 'anexar_lista_fisica') {
                $dataAnexo = trim((string) ($_POST['data_presenca'] ?? date('Y-m-d')));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAnexo)) {
                    throw new RuntimeException('Data invalida para anexo.');
                }

                $bloqStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM oficina_presencas_rh
                    WHERE data_presenca = :data_presenca
                      AND enviado_rh = 1
                ");
                $bloqStmt->execute([':data_presenca' => $dataAnexo]);
                if ((int) ($bloqStmt->fetchColumn() ?: 0) > 0) {
                    throw new RuntimeException('Esta lista ja foi enviada ao RH e nao aceita novo anexo.');
                }

                $up = processarAnexoListaFisicaOficina('lista_fisica_file', 'presencas_fisicas');
                if (empty($up['ok'])) {
                    throw new RuntimeException((string) ($up['error'] ?? 'Falha ao anexar lista fisica.'));
                }

                $upStmt = $pdo->prepare("
                    UPDATE oficina_presencas_rh
                    SET lista_fisica_anexo = :anexo
                    WHERE data_presenca = :data_presenca
                ");
                $upStmt->execute([
                    ':anexo' => (string) $up['path'],
                    ':data_presenca' => $dataAnexo,
                ]);

                header("Location: ?tab={$tab}&view=presencas&mode=list&data_assiduidade=" . urlencode($dataAnexo) . "&saved_anexo_fisico=1");
                exit;
            }

            if ($acao === 'enviar_rh') {
                $dataEnvio = trim((string) ($_POST['data_presenca'] ?? $data_assiduidade));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataEnvio)) {
                    throw new RuntimeException('Data de envio invalida.');
                }

                $chkAnexoStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM oficina_presencas_rh
                    WHERE data_presenca = :data_presenca
                      AND lista_fisica_anexo IS NOT NULL
                      AND lista_fisica_anexo <> ''
                ");
                $chkAnexoStmt->execute([':data_presenca' => $dataEnvio]);
                if ((int) ($chkAnexoStmt->fetchColumn() ?: 0) === 0) {
                    throw new RuntimeException('Anexe primeiro a lista fisica para poder enviar ao RH.');
                }

                $stmt = $pdo->prepare("
                    UPDATE oficina_presencas_rh
                    SET enviado_rh = 1,
                        enviado_em = NOW()
                    WHERE data_presenca = :data_presenca
                ");
                $stmt->execute([':data_presenca' => $dataEnvio]);

                header("Location: ?tab={$tab}&view=presencas&mode=list&data_assiduidade=" . urlencode($dataEnvio) . "&sent_rh=1");
                exit;
            }
        } catch (Throwable $e) {
            $erro_presencas = 'Nao foi possivel processar presencas: ' . $e->getMessage();
        }
    }

    if (isset($_GET['saved_assiduidade_lote']) && $_GET['saved_assiduidade_lote'] === '1') {
        $msg_presencas = 'Lista de presenca guardada com sucesso.';
    }
    if (isset($_GET['saved_anexo_fisico']) && $_GET['saved_anexo_fisico'] === '1') {
        $msg_presencas = 'Lista fisica anexada com sucesso.';
    }
    if (isset($_GET['sent_rh']) && $_GET['sent_rh'] === '1') {
        $msg_presencas = 'Lista enviada para RH com sucesso.';
    }

    try {
        $presDiaStmt = $pdo->prepare("
            SELECT
                apr.id, apr.pessoal_id, apr.hora_entrada, apr.hora_saida,
                apr.assinou_entrada, apr.assinou_saida, apr.observacoes, apr.enviado_rh,
                apr.lista_fisica_anexo
            FROM oficina_presencas_rh apr
            WHERE apr.data_presenca = :data_presenca
            ORDER BY apr.id DESC
        ");
        $presDiaStmt->execute([':data_presenca' => $data_assiduidade]);
        $presencas_oficina = $presDiaStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $presencas_por_colaborador = [];
        foreach ($presencas_oficina as $pr) {
            $pid = (int) ($pr['pessoal_id'] ?? 0);
            if ($pid <= 0 || isset($presencas_por_colaborador[$pid])) continue;
            $presencas_por_colaborador[$pid] = $pr;
            if ((int) ($pr['enviado_rh'] ?? 0) === 1) $lista_presenca_enviada_rh = true;
        }

        if ($hist_data_oficina !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hist_data_oficina)) {
            $histStmt = $pdo->prepare("
                SELECT apr.data_presenca, p.nome AS colaborador, c.nome AS cargo_nome, apr.hora_entrada, apr.hora_saida, apr.status_presenca, apr.enviado_rh
                FROM oficina_presencas_rh apr
                INNER JOIN pessoal p ON p.id = apr.pessoal_id
                LEFT JOIN cargos c ON c.id = p.cargo_id
                WHERE apr.data_presenca = :data_ref
                ORDER BY p.nome ASC
            ");
            $histStmt->execute([':data_ref' => $hist_data_oficina]);
            $lista_presencas_historico = $histStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $diasStmt = $pdo->prepare("
            SELECT
                data_presenca,
                COUNT(*) AS total_funcionarios,
                SUM(CASE WHEN status_presenca = 'Presente' THEN 1 ELSE 0 END) AS total_presentes,
                SUM(CASE WHEN status_presenca <> 'Presente' THEN 1 ELSE 0 END) AS total_ausentes,
                MIN(enviado_rh) AS enviado_rh_todos,
                MAX(CASE WHEN (lista_fisica_anexo IS NOT NULL AND lista_fisica_anexo <> '') THEN 1 ELSE 0 END) AS possui_anexo,
                MAX(lista_fisica_anexo) AS lista_fisica_anexo
            FROM oficina_presencas_rh
            WHERE data_presenca >= DATE_SUB(:data_base, INTERVAL 30 DAY)
            GROUP BY data_presenca
            ORDER BY data_presenca DESC
        ");
        $diasStmt->execute([':data_base' => $data_assiduidade]);
        $listas_presenca_dias = $diasStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        if ($erro_presencas === null) {
            $erro_presencas = 'Nao foi possivel carregar listas de presencas.';
        }
    }
}

if ($view === 'relatorios') {
    try {
        $relatorio_filtros['matricula'] = strtoupper(trim((string)($_GET['rf_matricula'] ?? '')));
        $relatorio_filtros['periodo'] = strtolower(trim((string)($_GET['rf_periodo'] ?? 'mensal')));
        if (!in_array($relatorio_filtros['periodo'], ['diario', 'semanal', 'mensal', 'anual'], true)) {
            $relatorio_filtros['periodo'] = 'mensal';
        }
        $rapido = strtolower(trim((string)($_GET['rf_rapido'] ?? '')));
        if (in_array($rapido, ['hoje', 'semana', 'mes', 'ano'], true)) {
            if ($rapido === 'hoje') $relatorio_filtros['periodo'] = 'diario';
            elseif ($rapido === 'semana') $relatorio_filtros['periodo'] = 'semanal';
            elseif ($rapido === 'mes') $relatorio_filtros['periodo'] = 'mensal';
            else $relatorio_filtros['periodo'] = 'anual';
        }
        $acaoGeracao = strtolower(trim((string)($_GET['rf_gerar'] ?? '')));
        if (in_array($acaoGeracao, ['semanal', 'mensal'], true)) {
            $relatorio_filtros['periodo'] = $acaoGeracao;
            $relatorio_filtros['completo'] = true;
        } else {
            $relatorio_filtros['completo'] = trim((string)($_GET['rf_completo'] ?? '0')) === '1';
        }
        $relatorio_filtros['data_referencia'] = trim((string)($_GET['rf_data_referencia'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $relatorio_filtros['data_referencia'])) {
            $relatorio_filtros['data_referencia'] = date('Y-m-d');
        }
        $filtroDataInicioRaw = trim((string)($_GET['rf_data_inicio'] ?? ''));
        $filtroDataFimRaw = trim((string)($_GET['rf_data_fim'] ?? ''));

        $dtRef = new DateTimeImmutable($relatorio_filtros['data_referencia']);
        if ($relatorio_filtros['periodo'] === 'diario') {
            $dtInicioPadrao = $dtRef;
            $dtFimPadrao = $dtRef;
        } elseif ($relatorio_filtros['periodo'] === 'semanal') {
            $diaSemana = (int)$dtRef->format('N');
            $dtInicioPadrao = $dtRef->modify('-' . ($diaSemana - 1) . ' days');
            $dtFimPadrao = $dtInicioPadrao->modify('+6 days');
        } elseif ($relatorio_filtros['periodo'] === 'anual') {
            $dtInicioPadrao = $dtRef->modify('first day of january');
            $dtFimPadrao = $dtRef->modify('last day of december');
        } else {
            $dtInicioPadrao = $dtRef->modify('first day of this month');
            $dtFimPadrao = $dtRef->modify('last day of this month');
        }

        if ($filtroDataInicioRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroDataInicioRaw)) {
            $relatorio_filtros['data_inicio'] = $filtroDataInicioRaw;
        } else {
            $relatorio_filtros['data_inicio'] = $dtInicioPadrao->format('Y-m-d');
        }
        if ($filtroDataFimRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroDataFimRaw)) {
            $relatorio_filtros['data_fim'] = $filtroDataFimRaw;
        } else {
            $relatorio_filtros['data_fim'] = $dtFimPadrao->format('Y-m-d');
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

        $resumoSql = "
            SELECT
                (SELECT COUNT(*) FROM oficina_ordens_servico os
                 WHERE DATE(os.data_abertura) BETWEEN :inicio AND :fim" . ($relatorio_filtros['matricula'] !== '' ? " AND os.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_os COLLATE utf8mb4_general_ci" : "") . ") AS total_os,
                (SELECT COUNT(*) FROM oficina_ordens_servico os
                 WHERE DATE(os.data_abertura) BETWEEN :inicio AND :fim
                   AND LOWER(TRIM(os.status_os)) IN ('fechado','resolvido','concluido')
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND os.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_os_fechada COLLATE utf8mb4_general_ci" : "") . ") AS total_os_fechadas,
                (SELECT COALESCE(SUM(COALESCE(os.custo_total, 0)), 0) FROM oficina_ordens_servico os
                 WHERE DATE(os.data_abertura) BETWEEN :inicio AND :fim
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND os.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_os_custo COLLATE utf8mb4_general_ci" : "") . ") AS gasto_os,

                (SELECT COUNT(*) FROM oficina_manutencoes m
                 WHERE m.data_manutencao BETWEEN :inicio AND :fim
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND m.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_m COLLATE utf8mb4_general_ci" : "") . ") AS total_manutencoes,
                (SELECT COUNT(*) FROM oficina_manutencoes m
                 WHERE m.data_manutencao BETWEEN :inicio AND :fim
                   AND LOWER(TRIM(m.status)) IN ('concluida','concluido','resolvido')
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND m.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_m_conc COLLATE utf8mb4_general_ci" : "") . ") AS total_manutencoes_concluidas,
                (SELECT COALESCE(SUM(COALESCE(m.custo_total, 0)), 0) FROM oficina_manutencoes m
                 WHERE m.data_manutencao BETWEEN :inicio AND :fim
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND m.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_m_custo COLLATE utf8mb4_general_ci" : "") . ") AS gasto_manutencao,

                (SELECT COUNT(*) FROM oficina_pedidos_reparacao p
                 WHERE p.data_pedido BETWEEN :inicio AND :fim
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND p.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_p COLLATE utf8mb4_general_ci" : "") . ") AS total_pedidos_reparacao,
                (SELECT COUNT(*) FROM oficina_pedidos_reparacao p
                 WHERE p.data_pedido BETWEEN :inicio AND :fim
                   AND LOWER(TRIM(p.status)) IN ('resolvido','fechado','concluido')
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND p.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_p_res COLLATE utf8mb4_general_ci" : "") . ") AS total_pedidos_resolvidos,
                (SELECT COALESCE(SUM(COALESCE(p.custo_estimado, 0)), 0) FROM oficina_pedidos_reparacao p
                 WHERE p.data_pedido BETWEEN :inicio AND :fim
                   " . ($relatorio_filtros['matricula'] !== '' ? " AND p.ativo_matricula COLLATE utf8mb4_general_ci = :matricula_p_custo COLLATE utf8mb4_general_ci" : "") . ") AS gasto_pedidos,

                (SELECT COUNT(*) FROM logistica_requisicoes r
                 WHERE r.origem_modulo = 'oficina'
                   AND r.data_requisicao BETWEEN :inicio AND :fim) AS total_requisicoes,
                (SELECT COUNT(*) FROM logistica_requisicoes r
                 WHERE r.origem_modulo = 'oficina'
                   AND r.data_requisicao BETWEEN :inicio AND :fim
                   AND LOWER(TRIM(r.status)) = 'aprovada') AS total_requisicoes_aprovadas,
                (SELECT COUNT(*) FROM logistica_requisicoes r
                 WHERE r.origem_modulo = 'oficina'
                   AND r.data_requisicao BETWEEN :inicio AND :fim
                   AND LOWER(TRIM(r.status)) = 'negada') AS total_requisicoes_negadas,
                (SELECT COALESCE(SUM(COALESCE(r.valor_total, r.custo_total, 0)), 0) FROM logistica_requisicoes r
                 WHERE r.origem_modulo = 'oficina'
                   AND r.data_requisicao BETWEEN :inicio AND :fim) AS gasto_requisicoes
        ";
        $paramsResumo = [
            'inicio' => $relatorio_filtros['data_inicio'],
            'fim' => $relatorio_filtros['data_fim'],
        ];
        if ($relatorio_filtros['matricula'] !== '') {
            $paramsResumo['matricula_os'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_os_fechada'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_os_custo'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_m'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_m_conc'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_m_custo'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_p'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_p_res'] = $relatorio_filtros['matricula'];
            $paramsResumo['matricula_p_custo'] = $relatorio_filtros['matricula'];
        }
        $stmtResumoPeriodo = $pdo->prepare($resumoSql);
        $stmtResumoPeriodo->execute($paramsResumo);
        $relatorio_resumo_periodo = $stmtResumoPeriodo->fetch(PDO::FETCH_ASSOC) ?: [];

        $matriculaCondOs = $relatorio_filtros['matricula'] !== '' ? " AND os.ativo_matricula COLLATE utf8mb4_general_ci = :matricula " : '';
        $matriculaCondM = $relatorio_filtros['matricula'] !== '' ? " AND m.ativo_matricula COLLATE utf8mb4_general_ci = :matricula " : '';
        $matriculaCondP = $relatorio_filtros['matricula'] !== '' ? " AND p.ativo_matricula COLLATE utf8mb4_general_ci = :matricula " : '';

        $sqlAtividades = "
            SELECT 'OS' AS tipo_atividade, os.codigo_os AS referencia, os.ativo_matricula, os.tipo_equipamento,
                   os.descricao_servico AS descricao, DATE(os.data_abertura) AS data_ref, os.status_os AS status,
                   COALESCE(os.custo_total, 0) AS gasto_total
            FROM oficina_ordens_servico os
            WHERE DATE(os.data_abertura) BETWEEN :inicio AND :fim {$matriculaCondOs}
            UNION ALL
            SELECT 'MANUTENCAO' AS tipo_atividade, CONCAT('MAN-', m.id) AS referencia, m.ativo_matricula, m.tipo_equipamento,
                   m.descricao_servico AS descricao, m.data_manutencao AS data_ref, m.status AS status,
                   COALESCE(m.custo_total, 0) AS gasto_total
            FROM oficina_manutencoes m
            WHERE m.data_manutencao BETWEEN :inicio AND :fim {$matriculaCondM}
            UNION ALL
            SELECT 'PEDIDO_REPARACAO' AS tipo_atividade, CONCAT('PR-', p.id) AS referencia, p.ativo_matricula, p.tipo_equipamento,
                   p.descricao_avaria AS descricao, p.data_pedido AS data_ref, p.status AS status,
                   COALESCE(p.custo_estimado, 0) AS gasto_total
            FROM oficina_pedidos_reparacao p
            WHERE p.data_pedido BETWEEN :inicio AND :fim {$matriculaCondP}
            ORDER BY data_ref DESC, tipo_atividade ASC
        ";
        $stmtAtividades = $pdo->prepare($sqlAtividades);
        $paramsAtividades = [
            'inicio' => $relatorio_filtros['data_inicio'],
            'fim' => $relatorio_filtros['data_fim'],
        ];
        if ($relatorio_filtros['matricula'] !== '') {
            $paramsAtividades['matricula'] = $relatorio_filtros['matricula'];
        }
        $stmtAtividades->execute($paramsAtividades);
        $relatorio_atividades_periodo = $stmtAtividades->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sqlAssPeriodo = "
            SELECT
                COUNT(*) AS total_registos,
                SUM(CASE WHEN apr.status_presenca IN ('Presente', 'Dispensa') THEN 1 ELSE 0 END) AS total_validos
            FROM oficina_presencas_rh apr
            WHERE apr.data_presenca BETWEEN :inicio AND :fim
        ";
        $stmtAssPeriodo = $pdo->prepare($sqlAssPeriodo);
        $stmtAssPeriodo->execute([
            'inicio' => $relatorio_filtros['data_inicio'],
            'fim' => $relatorio_filtros['data_fim'],
        ]);
        $assPeriodo = $stmtAssPeriodo->fetch(PDO::FETCH_ASSOC) ?: ['total_registos' => 0, 'total_validos' => 0];
        $totalAss = (int)($assPeriodo['total_registos'] ?? 0);
        $validosAss = (int)($assPeriodo['total_validos'] ?? 0);
        $relatorio_resumo_periodo['assiduidade_percentual'] = $totalAss > 0 ? round(($validosAss / $totalAss) * 100, 1) : 0.0;

        $sqlChecklist = "
            SELECT
                COUNT(*) AS total_ativos,
                SUM(CASE WHEN COALESCE(extintor, 0) = 1 THEN 1 ELSE 0 END) AS extintor_ok,
                SUM(CASE WHEN COALESCE(reflectores, 0) = 1 THEN 1 ELSE 0 END) AS reflectores_ok,
                SUM(CASE WHEN COALESCE(macaco, 0) = 1 THEN 1 ELSE 0 END) AS macaco_ok,
                SUM(CASE WHEN COALESCE(chave_roda, 0) = 1 THEN 1 ELSE 0 END) AS chave_ok
            FROM activos
            WHERE estado <> 'VENDIDO' OR estado IS NULL
        ";
        if ($relatorio_filtros['matricula'] !== '') {
            $sqlChecklist .= " AND matricula COLLATE utf8mb4_general_ci = :matricula_chk COLLATE utf8mb4_general_ci";
        }
        $stmtChecklist = $pdo->prepare($sqlChecklist);
        $paramsChk = [];
        if ($relatorio_filtros['matricula'] !== '') {
            $paramsChk['matricula_chk'] = $relatorio_filtros['matricula'];
        }
        $stmtChecklist->execute($paramsChk);
        $checkResumo = $stmtChecklist->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalAtivosChk = (int)($checkResumo['total_ativos'] ?? 0);
        $somaChecks = (int)($checkResumo['extintor_ok'] ?? 0) + (int)($checkResumo['reflectores_ok'] ?? 0) + (int)($checkResumo['macaco_ok'] ?? 0) + (int)($checkResumo['chave_ok'] ?? 0);
        $maxChecks = $totalAtivosChk * 4;
        $relatorio_resumo_periodo['checklist_percentual'] = $maxChecks > 0 ? round(($somaChecks / $maxChecks) * 100, 1) : 0.0;
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

function formatarMoedaMZN($valor): string {
    return number_format((float)$valor, 2, ',', '.') . ' MZN';
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
    if ($v === 'aprovada' || $v === 'aprovado') return 'ok';
    if ($v === 'negada' || $v === 'negado' || $v === 'cancelada') return 'warn';
    if ($v === 'resolvido' || $v === 'fechado' || $v === 'concluida' || $v === 'concluido') return 'ok';
    if ($v === 'aceito') return 'info';
    if ($v === 'em andamento' || $v === 'em progresso') return 'info';
    if ($v === 'pendente' || $v === 'aberto') return 'warn';
    return 'info';
}

function statusAssiduidadePorAssinatura(int $assinouEntrada, int $assinouSaida): string {
    return ($assinouEntrada === 1 || $assinouSaida === 1) ? 'Presente' : 'Falta';
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
            .module-entry {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 14px;
            }
            .module-entry-btn {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
                padding: 9px 12px;
                border-radius: 8px;
                font-size: 12px;
                font-weight: 700;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .module-entry-btn.lista { background: #dbeafe; color: #1e3a8a; border-color: #93c5fd; }
            .module-entry-btn.form { background: #ffedd5; color: #9a3412; border-color: #fdba74; }
            .module-modal {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #ffffff;
                overflow: hidden;
            }
            .module-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                padding: 12px 14px;
                background: #111827;
                border-bottom: 1px solid #0f172a;
            }
            .module-modal-header h4 {
                margin: 0;
                color: #ffffff;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: .2px;
            }
            .module-modal-actions { display: flex; gap: 8px; }
            .module-modal-btn {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
                padding: 7px 10px;
                border-radius: 7px;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
            }
            .module-modal-btn.min { background: #fef3c7; border-color: #fbbf24; color: #92400e; }
            .module-modal-btn.close { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
            .module-modal-body { padding: 12px; }
            .module-modal.minimized .module-modal-body { display: none; }
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
            .pedidos-table-wrap.no-box {
                border: none;
                border-radius: 0;
                background: transparent;
                overflow-x: auto;
            }
            .pedidos-table-wrap.no-box .table {
                min-width: 100%;
                table-layout: auto;
            }
            .pedidos-table-wrap.no-box .table th,
            .pedidos-table-wrap.no-box .table td {
                white-space: nowrap;
                word-break: normal;
            }
            .white-card.white-card-pedidos {
                background: transparent;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .white-card.white-card-pedidos .pedidos-table-wrap.no-box .table th,
            .white-card.white-card-pedidos .pedidos-table-wrap.no-box .table td {
                padding: 12px 10px;
            }
            .avarias-shell {
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 14px;
                box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
            }
            .avarias-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                margin-bottom: 12px;
                flex-wrap: wrap;
            }
            .avarias-head-left {
                display: grid;
                gap: 4px;
            }
            .avarias-title {
                font-size: 16px;
                color: #0f172a;
                font-weight: 800;
                line-height: 1.1;
            }
            .avarias-subtitle {
                font-size: 12px;
                color: #64748b;
            }
            .avarias-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                padding: 6px 10px;
                background: #fff7ed;
                border: 1px solid #fed7aa;
                color: #9a3412;
                font-size: 11px;
                font-weight: 800;
            }
            .avarias-table-wrap {
                width: 100%;
                overflow-x: auto;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                background: #ffffff;
            }
            .avarias-table-wrap .table {
                min-width: 100%;
                margin: 0;
                border-collapse: separate;
                border-spacing: 0;
            }
            .avarias-table-wrap .table thead th {
                white-space: nowrap;
                padding: 11px 10px;
                font-size: 11px;
                letter-spacing: .3px;
                text-transform: uppercase;
                color: #334155;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
            }
            .avarias-table-wrap .table td {
                white-space: nowrap;
                padding: 11px 10px;
                font-size: 12px;
                border-bottom: 1px solid #f1f5f9;
            }
            .avarias-table-wrap .table tbody tr:nth-child(even) {
                background: #fcfdff;
            }
            .avarias-table-wrap .table tbody tr:hover {
                background: #f8fafc;
            }
            .avarias-table-wrap .table tbody tr:last-child td {
                border-bottom: none;
            }
            .avarias-id-badge {
                display: inline-flex;
                min-width: 28px;
                justify-content: center;
                border-radius: 999px;
                padding: 3px 8px;
                background: #e0f2fe;
                color: #075985;
                font-weight: 700;
            }
            .avarias-origem-badge {
                display: inline-flex;
                align-items: center;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 700;
            }
            .avarias-origem-badge.pedido {
                background: #dcfce7;
                color: #166534;
            }
            .avarias-origem-badge.manual {
                background: #fef3c7;
                color: #92400e;
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
            .pedido-summary-card {
                border: 1px solid #dbe4f0;
                background: linear-gradient(135deg, #f8fbff 0%, #ffffff 45%);
                border-radius: 14px;
                padding: 14px;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            }
            .pedido-summary-meta {
                display: grid;
                grid-template-columns: repeat(3, minmax(140px, 1fr));
                gap: 10px;
                margin-top: 10px;
            }
            .pedido-summary-chip {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #ffffff;
                padding: 8px 10px;
                font-size: 12px;
            }
            .pedido-summary-chip strong {
                display: block;
                color: #64748b;
                font-size: 11px;
                margin-bottom: 2px;
                text-transform: uppercase;
                letter-spacing: .3px;
            }
            .pedido-summary-desc {
                margin-top: 10px;
                border-left: 4px solid #2563eb;
                background: #f8fafc;
                padding: 10px 12px;
                border-radius: 10px;
                font-size: 12px;
                color: #334155;
            }
            .btn-modern {
                border: none;
                border-radius: 10px;
                padding: 9px 14px;
                font-size: 12px;
                font-weight: 700;
                color: #fff;
                cursor: pointer;
                transition: transform .15s ease, box-shadow .2s ease, opacity .2s ease;
                box-shadow: 0 8px 16px rgba(15, 23, 42, 0.15);
            }
            .btn-modern:hover { transform: translateY(-1px); opacity: .96; }
            .btn-modern.primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
            .btn-modern.success { background: linear-gradient(135deg, #0f766e, #0d9488); }
            .btn-modern.dark { background: linear-gradient(135deg, #111827, #1f2937); }
            .btn-modern.purple { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
            .btn-modern.ghost {
                background: #fff;
                color: #111827;
                border: 1px solid #d1d5db;
                box-shadow: none;
            }
            .material-modal {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.55);
                display: none;
                align-items: center;
                justify-content: center;
                padding: 14px;
                z-index: 1300;
            }
            .material-modal.open { display: flex; }
            .material-modal-card {
                width: min(960px, 96vw);
                max-height: 88vh;
                overflow: auto;
                background: #fff;
                border-radius: 14px;
                border: 1px solid #dbe2ea;
                box-shadow: 0 22px 44px rgba(15, 23, 42, 0.24);
                padding: 14px;
            }
            .material-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }
            .material-close {
                background: #ef4444;
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 6px 10px;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
            }
            @media (max-width: 980px) {
                .pedido-summary-meta { grid-template-columns: 1fr; }
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
            .rel-shell {
                border: 1px solid #dbe2ea;
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 28%);
                border-radius: 14px;
                padding: 14px;
            }
            .rel-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 10px;
            }
            .rel-title {
                margin: 0;
                font-size: 20px;
                font-weight: 800;
                color: #0f172a;
            }
            .rel-subtitle {
                margin: 6px 0 0 0;
                font-size: 12px;
                color: #64748b;
            }
            .download-modal {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                display: none;
                align-items: center;
                justify-content: center;
                padding: 12px;
                z-index: 1200;
            }
            .download-modal.open { display: flex; }
            .download-panel {
                width: 100%;
                max-width: 420px;
                border: 1px solid #dbe2ea;
                background: #ffffff;
                border-radius: 12px;
                padding: 14px;
                box-shadow: 0 18px 35px rgba(15, 23, 42, 0.2);
            }
            .download-panel h4 {
                margin: 0 0 8px 0;
                font-size: 16px;
                color: #0f172a;
            }
            .download-panel p {
                margin: 0 0 10px 0;
                font-size: 12px;
                color: #64748b;
            }
            .download-panel select {
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
                margin-bottom: 12px;
            }
            .quick-filter-active {
                border-color: #111827 !important;
                background: #111827 !important;
                color: #ffffff !important;
            }
            .rel-chip-row {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin: 10px 0 14px 0;
            }
            .rel-chip {
                border: 1px solid #cbd5e1;
                background: #fff;
                color: #334155;
                border-radius: 999px;
                padding: 6px 10px;
                font-size: 11px;
                font-weight: 700;
            }
            .relatorio-filtros {
                grid-template-columns: repeat(5, minmax(130px, 1fr));
                gap: 10px;
                background: #ffffff;
                border: 1px solid #dbe2ea;
                border-radius: 12px;
                padding: 10px;
            }
            .relatorio-filtros .field {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .relatorio-filtros .field label {
                font-size: 11px;
                font-weight: 700;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: .2px;
            }
            .relatorio-filtros select,
            .relatorio-filtros input {
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
                background: #fff;
            }
            .rel-actions {
                display: flex;
                gap: 8px;
                align-items: end;
                flex-wrap: wrap;
            }
            .kpi-grid {
                grid-template-columns: repeat(3, minmax(170px, 1fr));
                margin-top: 12px;
            }
            .kpi-card {
                background: #ffffff;
                border-color: #dbe2ea;
                box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
            }
            .kpi-card .kpi-value { font-size: 19px; }
            .section-card {
                border-color: #dbe2ea;
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            }
            @media (max-width: 980px) {
                .rel-header { flex-direction: column; }
                .kpi-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
                .relatorio-filtros { grid-template-columns: 1fr 1fr; }
            }
        </style>
        <?php $classe_card_oficina = ($view === 'pedidos_reparacao' && $mode === 'list') ? ' white-card-pedidos' : ''; ?>
        <div class="white-card<?= $classe_card_oficina ?>">
            <?php if ($view !== 'relatorios' && $view !== 'presencas'): ?>
                <div class="module-entry">
                    <?php if (!in_array($view, ['pedidos_reparacao', 'manutencao'], true)): ?>
                        <a href="?tab=<?= urlencode((string)$tab) ?>&view=<?= urlencode((string)$view) ?>&mode=form" class="module-entry-btn form"><i class="fas fa-plus"></i> Adicionar</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'home'): ?>
                <div class="section-card">
                    <p style="font-size:12px; color:#6b7280; margin:0;">Selecione Lista ou Adicionar para abrir a tela da Oficina.</p>
                </div>
            <?php else: ?>
                <div class="module-modal" id="oficina-main-modal">
                    <?php if ($view !== 'relatorios' && $view !== 'pedidos_reparacao' && $view !== 'ordens_servico' && $view !== 'requisicoes' && $view !== 'manutencao' && $view !== 'avarias' && $view !== 'presencas'): ?>
                        <div class="module-modal-header">
                            <h4>Oficina - <?= htmlspecialchars((string)$view) ?></h4>
                            <div class="module-modal-actions">
                                <button type="button" class="module-modal-btn min" id="btn-min-oficina">Minimizar</button>
                                <a href="?tab=<?= urlencode((string)$tab) ?>&view=<?= urlencode((string)$view) ?>" class="module-modal-btn close">Fechar</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="module-modal-body" id="oficina-main-body">
                        <?php if (
                            $mode == 'list' &&
                            $view !== 'relatorios' &&
                            $view !== 'pedidos_reparacao' &&
                            $view !== 'ordens_servico' &&
                            $view !== 'requisicoes' &&
                            $view !== 'manutencao' &&
                            $view !== 'avarias' &&
                            $view !== 'presencas'
                        ): ?>
                        <div class="list-tools" style="margin-bottom:10px;">
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
                                <?php elseif ($view == 'requisicoes'): ?>
                                    <option value="">Filtrar por status</option>
                                    <option>Pendente</option>
                                    <option>Aprovada</option>
                                    <option>Negada</option>
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

                        <div class="form-group">
                            <label>custo_total_mzn</label>
                            <input type="number" name="custo_total" min="0" step="0.01" value="0">
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
                            <h3>Pedido de Reparação</h3>
                            <p style="font-size:12px; color:#6b7280; margin-top:4px;">Registe o pedido com prioridade, local e sintomas para acelerar o atendimento.</p>
                        </div>
                        <div class="pill warn">Nova solicitação</div>
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

                        <div class="form-group">
                            <label>custo_estimado_mzn</label>
                            <input type="number" name="custo_estimado" min="0" step="0.01" value="0">
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
                <?php elseif ($view == 'requisicoes'): ?>
                    <h3>Requisicao de Pecas e Equipamentos</h3>
                    <p style="font-size:12px; color:#6b7280;">A requisicao sera enviada para Logistica para aprovacao ou negacao.</p>
                    <?php if ($erro_requisicoes): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_requisicoes) ?></p>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=requisicoes&mode=form">
                        <input type="hidden" name="acao" value="criar_requisicao_oficina">

                        <div class="form-group">
                            <label>categoria_item</label>
                            <select name="categoria_item">
                                <option>Peca</option>
                                <option>Equipamento</option>
                                <option>Consumivel</option>
                                <option>Outro</option>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column:span 2;">
                            <label>item</label>
                            <input type="text" name="item" placeholder="Ex: Kit embraiagem, Oleo 15W40, Macaco hidraulico" required>
                        </div>

                        <div class="form-group">
                            <label>data_requisicao</label>
                            <input type="date" name="data_requisicao" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>quantidade</label>
                            <input type="number" name="quantidade" min="0.01" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label>unidade</label>
                            <input type="text" name="unidade" value="un">
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
                            <label>responsavel</label>
                            <input type="text" name="responsavel" value="<?= htmlspecialchars((string)($_SESSION['usuario_nome'] ?? '')) ?>">
                        </div>

                        <div class="form-group" style="grid-column:span 4;">
                            <label>observacoes</label>
                            <textarea name="observacoes" rows="4" placeholder="Detalhe a necessidade da oficina, urgencia e aplicacao no equipamento."></textarea>
                        </div>

                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:#111827;width:100%;">Enviar para Logistica</button>
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
                        <div class="form-group">
                            <label>custo_total_mzn</label>
                            <input type="number" name="custo_total" min="0" step="0.01" value="0">
                        </div>
                        <div class="form-group" style="grid-column:span 4;">
                            <label>descricao_servico</label>
                            <textarea name="descricao_servico" rows="4" placeholder="Ex: Troca de oleo e filtros"></textarea>
                        </div>

                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:var(--danger);width:100%;">Guardar Manutencao</button>
                        </div>
                    </form>
                <?php elseif ($view == 'presencas'): ?>
                    <h3>Controle de Presencas - Oficina</h3>
                    <p style="font-size:12px; color:#6b7280;">Use a vista de lista para marcar entrada/saida, anexar lista fisica e enviar para RH.</p>
                    <a class="btn-save" style="display:inline-block;background:#111827;" href="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&data_assiduidade=<?= urlencode((string)$data_assiduidade) ?>">Abrir controle</a>
                <?php elseif ($view == 'avarias'): ?>
                    <h3>Registo de Avaria</h3>
                    <p style="font-size:12px; color:#6b7280;">Registe uma nova avaria/incidente e, se necessario, gere a Ordem de Servico no mesmo passo.</p>
                    <?php if ($erro_avarias): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_avarias) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_avarias): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_avarias) ?></p>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=avarias&mode=form">
                        <input type="hidden" name="acao" value="criar_avaria">
                        <div class="form-group">
                            <label>Matricula do Ativo</label>
                            <input type="text" name="ativo_matricula" placeholder="Ex: AGD - 220" required>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Equipamento</label>
                            <input type="text" name="tipo_equipamento" placeholder="Ex: Sino Truck" required>
                        </div>
                        <div class="form-group">
                            <label>Data do Evento</label>
                            <input type="date" name="data_evento" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Gerar Ordem de Servico</label>
                            <select name="criar_os">
                                <option value="1">Sim</option>
                                <option value="0">Nao</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:span 4;">
                            <label>Descricao da Avaria</label>
                            <textarea name="descricao" rows="4" placeholder="Descreva o problema encontrado..." required></textarea>
                        </div>
                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:#dc2626;width:100%;">Registar Avaria</button>
                        </div>
                    </form>
                <?php else: ?>
                    <h3>Relatorios de Oficina</h3>
                    <p style="font-size:12px; color:#6b7280;">Use o modo "Ver Lista" para consultar as metricas e historico de avarias por veiculo.</p>
                <?php endif; ?>
            <?php elseif ($mode == 'detalhe' && $view == 'pedidos_reparacao'): ?>
                <?php if ($erro_pedidos): ?>
                    <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_pedidos) ?></p>
                <?php endif; ?>
                <?php if ($msg_pedidos): ?>
                    <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_pedidos) ?></p>
                <?php endif; ?>
                <?php if ($pedido_reparacao_detalhe): ?>
                    <?php
                        $pedidoIdDetalheUi = (int)($pedido_reparacao_detalhe['id'] ?? 0);
                        $statusDetalheNormalizado = normalizarStatusPedido((string)($pedido_reparacao_detalhe['status'] ?? 'Pendente'));
                        $statusDetalhe = statusPedidoLabel($statusDetalheNormalizado);
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="margin:0;">Detalhes do Pedido #<?= $pedidoIdDetalheUi ?></h3>
                        <a href="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=list" class="btn-modern ghost" style="text-decoration:none;">Voltar</a>
                    </div>

                    <div class="pedido-summary-card" style="margin-bottom:12px;">
                        <div class="screen-title-row">
                            <div class="screen-title-group">
                                <h4 class="screen-title" style="margin-bottom:4px;"><i class="fa-solid fa-clipboard-list"></i> Resumo do Pedido</h4>
                                <div class="screen-subtitle"><?= htmlspecialchars((string)($pedido_reparacao_detalhe['ativo_matricula'] ?? '-')) ?> | <?= htmlspecialchars((string)($pedido_reparacao_detalhe['tipo_equipamento'] ?? '-')) ?></div>
                            </div>
                            <span class="pill <?= badgeClasseStatus($statusDetalhe) ?>"><?= htmlspecialchars($statusDetalhe) ?></span>
                        </div>
                        <div class="pedido-summary-meta">
                            <div class="pedido-summary-chip">
                                <strong>Solicitante</strong>
                                <?= htmlspecialchars((string)($pedido_reparacao_detalhe['solicitante'] ?? '-')) ?>
                            </div>
                            <div class="pedido-summary-chip">
                                <strong>Localizacao</strong>
                                <?= htmlspecialchars((string)($pedido_reparacao_detalhe['localizacao'] ?? '-')) ?>
                            </div>
                            <div class="pedido-summary-chip">
                                <strong>Data do pedido</strong>
                                <?= htmlspecialchars((string)($pedido_reparacao_detalhe['data_pedido'] ?? '-')) ?>
                            </div>
                        </div>
                        <div class="pedido-summary-desc">
                            <strong>Descricao da avaria</strong><br>
                            <?= nl2br(htmlspecialchars((string)($pedido_reparacao_detalhe['descricao_avaria'] ?? '-'))) ?>
                        </div>
                    </div>

                    <div class="section-card" style="margin-bottom:12px;">
                        <h4 style="margin-top:0;">Acoes do Pedido</h4>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <?php if ($statusDetalheNormalizado === 'pendente'): ?>
                                <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=detalhe&id=<?= $pedidoIdDetalheUi ?>">
                                    <input type="hidden" name="acao" value="aceitar_detalhe">
                                    <input type="hidden" name="pedido_id" value="<?= $pedidoIdDetalheUi ?>">
                                    <button type="submit" class="btn-modern primary">Aceitar pedido</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="section-card" style="margin-bottom:12px;">
                        <h4 style="margin-top:0;">Diagnostico Tecnico dos Mecanicos</h4>
                        <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=detalhe&id=<?= $pedidoIdDetalheUi ?>&detalhe_lista=<?= urlencode((string)$detalhe_lista) ?>">
                            <input type="hidden" name="acao" value="salvar_diagnostico_detalhe">
                            <input type="hidden" name="pedido_id" value="<?= $pedidoIdDetalheUi ?>">
                            <div class="form-group" style="margin-bottom:8px;">
                                <label>Equipa responsavel pelo diagnostico</label>
                                <input type="text" name="equipa_diagnostico" value="<?= htmlspecialchars((string)($pedido_reparacao_detalhe['equipa_diagnostico'] ?? '')) ?>" placeholder="Ex: Equipa Mecanica A" required>
                            </div>
                            <label style="display:flex; align-items:center; gap:6px; font-size:12px; margin-bottom:8px;">
                                <input type="checkbox" name="diagnostico_realizado" value="1" <?= ((int)($pedido_reparacao_detalhe['diagnostico_realizado'] ?? 0) === 1) ? 'checked' : '' ?>>
                                Diagnostico realizado
                            </label>
                            <textarea name="descricao_tecnica" rows="4" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px;" placeholder="Descreva o diagnostico tecnico realizado pelos mecanicos..."><?= htmlspecialchars((string)($pedido_reparacao_detalhe['descricao_tecnica'] ?? '')) ?></textarea>
                            <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                                <button type="submit" class="btn-modern success">Salvar diagnostico</button>
                                <button type="button" class="btn-modern dark" id="open-material-modal">+Materiais Necessarios</button>
                            </div>
                        </form>
                    </div>

                    <div class="material-modal" id="material-modal">
                        <div class="material-modal-card">
                            <div class="material-modal-header">
                                <h4 style="margin:0;">Materiais Necessarios</h4>
                                <button type="button" class="material-close" id="close-material-modal">Fechar</button>
                            </div>
                        <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=detalhe&id=<?= $pedidoIdDetalheUi ?>&detalhe_lista=<?= urlencode((string)$detalhe_lista) ?>" class="form-grid">
                            <input type="hidden" name="acao" value="adicionar_material_detalhe">
                            <input type="hidden" name="pedido_id" value="<?= $pedidoIdDetalheUi ?>">
                            <div class="form-group">
                                <label>Item</label>
                                <input type="text" name="material_item" required placeholder="Ex: Filtro de oleo">
                            </div>
                            <div class="form-group">
                                <label>Quantidade</label>
                                <input type="number" name="material_quantidade" min="0.01" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Unidade</label>
                                <input type="text" name="material_unidade" value="un">
                            </div>
                            <div class="form-group">
                                <label>Observacoes</label>
                                <input type="text" name="material_observacoes" placeholder="Opcional">
                            </div>
                            <div style="grid-column:span 4;">
                                <button type="submit" class="btn-modern dark">Adicionar material</button>
                            </div>
                        </form>

                        <div class="pedidos-table-wrap" style="margin-top:10px;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item</th>
                                        <th>Quantidade</th>
                                        <th>Unidade</th>
                                        <th>Observacoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($materiais_pedido_detalhe) === 0): ?>
                                        <tr><td colspan="5" style="text-align:center;color:#6b7280;">Nenhum material adicionado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($materiais_pedido_detalhe as $mat): ?>
                                            <tr>
                                                <td><?= (int)($mat['id'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars((string)($mat['item'] ?? '')) ?></td>
                                                <td><?= number_format((float)($mat['quantidade'] ?? 0), 2, ',', '.') ?></td>
                                                <td><?= htmlspecialchars((string)($mat['unidade'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string)($mat['observacoes'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                    <div class="section-card" style="margin-bottom:12px;">
                        <h4 style="margin-top:0;">Enviar para Logistica</h4>
                        <p style="font-size:12px; color:#6b7280;">A Logistica recebe a relacao de pecas para tratar cotacoes.</p>
                        <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=detalhe&id=<?= $pedidoIdDetalheUi ?>&detalhe_lista=<?= urlencode((string)$detalhe_lista) ?>" style="display:flex; justify-content:center;">
                            <input type="hidden" name="acao" value="enviar_logistica_detalhe">
                            <input type="hidden" name="pedido_id" value="<?= $pedidoIdDetalheUi ?>">
                            <button type="submit" class="btn-modern purple">Mandar para Logistica</button>
                        </form>
                    </div>
                    <script>
                    (function() {
                        var modal = document.getElementById('material-modal');
                        var openBtn = document.getElementById('open-material-modal');
                        var closeBtn = document.getElementById('close-material-modal');
                        if (!modal || !openBtn || !closeBtn) return;
                        openBtn.addEventListener('click', function() { modal.classList.add('open'); });
                        closeBtn.addEventListener('click', function() { modal.classList.remove('open'); });
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) modal.classList.remove('open');
                        });
                        var params = new URLSearchParams(window.location.search);
                        if (params.get('open_material') === '1') {
                            modal.classList.add('open');
                        }
                    })();
                    </script>

                <?php endif; ?>
            <?php elseif ($mode == 'list'): ?>
                <?php if ($view == 'pedidos_reparacao'): ?>
                    <?php if ($erro_pedidos): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_pedidos) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_pedidos): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_pedidos) ?></p>
                    <?php endif; ?>
                    <div class="pedidos-table-wrap no-box">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Matrícula</th>
                                <th>Tipo de Equipamento</th>
                                <th>Descrição da Avaria</th>
                                <th>Localização</th>
                                <th>Solicitante</th>
                                <th>Data do Pedido</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Ações</th>
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
                                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                <a class="btn-acao" style="background:#111827; text-decoration:none;" href="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=detalhe&id=<?= (int)campo($p, ['id']) ?>">Ver detalhes</a>
                                                <a class="btn-acao" style="background:#1f6feb; text-decoration:none;" href="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=detalhe&id=<?= (int)campo($p, ['id']) ?>&open_material=1">Adicionar material</a>
                                                <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=pedidos_reparacao&mode=list" style="margin:0;">
                                                    <input type="hidden" name="acao" value="enviar_logistica_detalhe">
                                                    <input type="hidden" name="pedido_id" value="<?= (int)campo($p, ['id']) ?>">
                                                    <input type="hidden" name="return_mode" value="list">
                                                    <button type="submit" class="btn-acao" style="background:#8b5cf6;">Mandar Logistica</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'requisicoes'): ?>
                    <?php if ($erro_requisicoes): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_requisicoes) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_requisicoes): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_requisicoes) ?></p>
                    <?php endif; ?>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>codigo</th>
                                <th>categoria</th>
                                <th>item</th>
                                <th>quantidade</th>
                                <th>custo_estimado</th>
                                <th>prioridade</th>
                                <th>status</th>
                                <th>data_requisicao</th>
                                <th>responsavel</th>
                                <th>decisao_logistica</th>
                                <th>observacoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requisicoes_oficina) === 0): ?>
                                <tr><td colspan="12" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($requisicoes_oficina as $req): ?>
                                    <?php
                                        $statusReq = labelStatusRequisicaoOficina((string)($req['status'] ?? 'Pendente'));
                                        $decisaoMeta = '-';
                                        if (!empty($req['decidido_por']) || !empty($req['decidido_em'])) {
                                            $decisaoMeta = trim((string)($req['decidido_por'] ?? ''));
                                            if ($decisaoMeta === '') {
                                                $decisaoMeta = 'Logistica';
                                            }
                                            if (!empty($req['decidido_em'])) {
                                                $decisaoMeta .= ' em ' . (string)$req['decidido_em'];
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= (int)($req['id'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars((string)($req['codigo'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($req['categoria_item'] ?? '-')) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)($req['item'] ?? '-')) ?>"><?= htmlspecialchars((string)($req['item'] ?? '-')) ?></span></td>
                                        <td><?= htmlspecialchars((string)($req['quantidade'] ?? '0')) ?> <?= htmlspecialchars((string)($req['unidade'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars(formatarMoedaMZN((float)($req['valor_total'] ?? 0))) ?></td>
                                        <td><span class="pill <?= badgeClassePrioridade((string)($req['prioridade'] ?? 'Normal')) ?>"><?= htmlspecialchars((string)($req['prioridade'] ?? 'Normal')) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus($statusReq) ?>"><?= htmlspecialchars($statusReq) ?></span></td>
                                        <td><?= htmlspecialchars((string)($req['data_requisicao'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($req['responsavel'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars($decisaoMeta) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)($req['observacoes'] ?? '')) ?>"><?= htmlspecialchars((string)($req['observacoes'] ?? '-')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'ordens_servico'): ?>
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
                                <th>custo_total</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ordens_servico) === 0): ?>
                                <tr><td colspan="10" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
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
                                        <td><?= htmlspecialchars(formatarMoedaMZN((float)campo($os, ['custo_total'], 0))) ?></td>
                                        <td><span class="pill <?= badgeClassePrioridade(campo($os, ['prioridade'], 'Normal')) ?>"><?= htmlspecialchars((string)campo($os, ['prioridade'], 'Normal')) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus(campo($os, ['status_os'], 'Aberto')) ?>"><?= htmlspecialchars((string)campo($os, ['status_os'], 'Aberto')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'manutencao'): ?>
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
                                <th>custo_total</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($manutencoes) === 0): ?>
                                <tr><td colspan="10" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
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
                                        <td><?= htmlspecialchars(formatarMoedaMZN((float)campo($m, ['custo_total'], 0))) ?></td>
                                        <td><span class="pill <?= badgeClassePrioridade(campo($m, ['prioridade'], 'Normal')) ?>"><?= htmlspecialchars((string)campo($m, ['prioridade'], 'Normal')) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus(campo($m, ['status'], 'Pendente')) ?>"><?= htmlspecialchars((string)campo($m, ['status'], 'Pendente')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                <?php elseif ($view == 'avarias'): ?>
                    <?php if ($erro_avarias): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_avarias) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_avarias): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_avarias) ?></p>
                    <?php endif; ?>
                    <div class="avarias-shell">
                        <div class="avarias-head">
                            <div class="avarias-head-left">
                                <div class="avarias-title">Historico de Avarias</div>
                                <div class="avarias-subtitle">Registos de ocorrencias por viatura/equipamento.</div>
                            </div>
                            <span class="avarias-count"><?= count($avarias) ?> registo(s)</span>
                        </div>
                        <div class="avarias-table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Matricula</th>
                                        <th>Equipamento</th>
                                        <th>Descricao</th>
                                        <th>Data do Evento</th>
                                        <th>Origem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($avarias) === 0): ?>
                                        <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($avarias as $a): ?>
                                            <?php
                                                $origemAvariaRaw = (string)campo($a, ['origem_tipo']);
                                                $origemAvaria = $origemAvariaRaw === 'PEDIDO_REPARACAO' ? 'Pedido de Reparacao' : ($origemAvariaRaw !== '' ? $origemAvariaRaw : 'Manual');
                                                $dataEventoAvaria = (string)campo($a, ['data_evento']);
                                                if ($dataEventoAvaria !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataEventoAvaria)) {
                                                    $dataEventoAvaria = date('d/m/Y', strtotime($dataEventoAvaria));
                                                }
                                            ?>
                                            <tr>
                                                <td><span class="avarias-id-badge"><?= htmlspecialchars((string)campo($a, ['id'])) ?></span></td>
                                                <td><?= htmlspecialchars((string)campo($a, ['ativo_matricula'])) ?></td>
                                                <td><?= htmlspecialchars((string)campo($a, ['tipo_equipamento'])) ?></td>
                                                <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($a, ['descricao'])) ?>"><?= htmlspecialchars((string)campo($a, ['descricao'])) ?></span></td>
                                                <td><?= htmlspecialchars($dataEventoAvaria !== '' ? $dataEventoAvaria : '-') ?></td>
                                                <td><span class="avarias-origem-badge <?= $origemAvariaRaw === 'PEDIDO_REPARACAO' ? 'pedido' : 'manual' ?>"><?= htmlspecialchars($origemAvaria) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif ($view == 'presencas'): ?>
                    <?php if ($erro_presencas): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars((string)$erro_presencas) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_presencas): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars((string)$msg_presencas) ?></p>
                    <?php endif; ?>

                    <form method="GET" action="" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px;">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars((string)$tab) ?>">
                        <input type="hidden" name="view" value="presencas">
                        <input type="hidden" name="mode" value="list">
                        <input type="hidden" name="aplicar" value="1">
                        <div class="form-group" style="margin:0;">
                            <label>Data da Lista</label>
                            <input type="date" name="data_assiduidade" value="<?= htmlspecialchars((string)$data_assiduidade) ?>">
                        </div>
                        <button type="submit" class="btn-save" style="background:#111827;">Carregar Lista</button>
                        <button type="button" class="btn-save" style="background:#0f766e;" onclick="abrirTelaListasPresencasOficina()"><i class="fa-solid fa-list-check"></i> Ver listas de presencas</button>
                    </form>

                    <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&data_assiduidade=<?= urlencode((string)$data_assiduidade) ?>" style="margin-bottom:14px;">
                        <input type="hidden" name="acao_presencas" value="marcar_presenca_lote">
                        <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string)$data_assiduidade) ?>">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                            <div style="font-size:12px; color:#374151; font-weight:700;">Marque entrada/saida conforme a folha fisica do dia.</div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="btn-save" onclick="marcarTodosPresentesOficina(event)" style="background:#0ea5e9;" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>>Marcar todos presentes</button>
                                <button type="button" class="btn-save" onclick="marcarTodosAusentesOficina(event)" style="background:#9ca3af;" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>>Marcar todos ausentes</button>
                            </div>
                            <button type="submit" class="btn-save" style="background:#111827;" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>>Salvar lista</button>
                        </div>
                        <div class="pedidos-table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Numero</th>
                                        <th>Nome</th>
                                        <th>Cargo</th>
                                        <th>Entrada</th>
                                        <th>Hora Entrada</th>
                                        <th>Saida</th>
                                        <th>Hora Saida</th>
                                        <th>Observacoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($colaboradores_oficina) === 0): ?>
                                        <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:12px;">Sem funcionarios da oficina para marcacao.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($colaboradores_oficina as $col): ?>
                                            <?php
                                                $pid = (int)($col['id'] ?? 0);
                                                $atual = $presencas_por_colaborador[$pid] ?? null;
                                                $inChecked = (int)($atual['assinou_entrada'] ?? 0) === 1;
                                                $outChecked = (int)($atual['assinou_saida'] ?? 0) === 1;
                                                $horaIn = !empty($atual['hora_entrada']) ? substr((string)$atual['hora_entrada'], 0, 5) : '07:00';
                                                $horaOut = !empty($atual['hora_saida']) ? substr((string)$atual['hora_saida'], 0, 5) : '16:00';
                                                $obsAtual = (string)($atual['observacoes'] ?? '');
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)($col['numero'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($col['nome'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($col['cargo_nome'] ?? '-')) ?></td>
                                                <td><input type="checkbox" class="js-pres-entry" name="entrada_lote[<?= $pid ?>]" value="1" <?= $inChecked ? 'checked' : '' ?> <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                                                <td><input type="time" class="js-pres-entry-time" name="hora_entrada_lote[<?= $pid ?>]" value="<?= htmlspecialchars($horaIn) ?>" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                                                <td><input type="checkbox" class="js-pres-exit" name="saida_lote[<?= $pid ?>]" value="1" <?= $outChecked ? 'checked' : '' ?> <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                                                <td><input type="time" class="js-pres-exit-time" name="hora_saida_lote[<?= $pid ?>]" value="<?= htmlspecialchars($horaOut) ?>" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                                                <td><input type="text" name="obs_lote[<?= $pid ?>]" value="<?= htmlspecialchars($obsAtual) ?>" placeholder="Opcional" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <div id="painel-listas-presencas-oficina" style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(15,23,42,0.6); padding:24px; overflow:auto;">
                        <div style="max-width:1200px; margin:0 auto; background:#fff; border-radius:12px; border:1px solid #dbe3ed; padding:14px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <div style="font-size:13px; font-weight:800; color:#334155;">Listas de Presencas (ultimos 30 dias)</div>
                                <button type="button" class="btn-save" style="background:#334155;" onclick="fecharTelaListasPresencasOficina()"><i class="fa-solid fa-xmark"></i> Fechar tela</button>
                            </div>
                            <div class="pedidos-table-wrap no-box">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Lista</th>
                                            <th>Total Funcionarios</th>
                                            <th>Presentes</th>
                                            <th>Ausentes</th>
                                            <th>Lista Fisica</th>
                                            <th>Enviado RH</th>
                                            <th>Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($listas_presenca_dias)): ?>
                                            <tr><td colspan="7" style="text-align:center;color:#777;">Sem listas de presenca no periodo.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($listas_presenca_dias as $ld): ?>
                                                <?php
                                                    $dataLista = (string)($ld['data_presenca'] ?? '');
                                                    $enviadoTodos = (int)($ld['enviado_rh_todos'] ?? 0) === 1;
                                                    $possuiAnexo = (int)($ld['possui_anexo'] ?? 0) === 1;
                                                    $anexoPathLista = (string)($ld['lista_fisica_anexo'] ?? '');
                                                ?>
                                                <tr>
                                                    <td><?= !empty($dataLista) ? ('Lista ' . date('d/m/Y', strtotime($dataLista))) : '-' ?></td>
                                                    <td><?= (int)($ld['total_funcionarios'] ?? 0) ?></td>
                                                    <td><?= (int)($ld['total_presentes'] ?? 0) ?></td>
                                                    <td><?= (int)($ld['total_ausentes'] ?? 0) ?></td>
                                                    <td>
                                                        <?php if ($possuiAnexo && $anexoPathLista !== ''): ?>
                                                            <a href="<?= htmlspecialchars('/vilcon-systemon/' . ltrim($anexoPathLista, '/')) ?>" target="_blank" class="btn-save" style="font-size:10px;background:#0f766e;">Ver anexo</a>
                                                        <?php else: ?>
                                                            <span style="font-size:11px; color:#b91c1c; font-weight:700;">Nao anexada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= $enviadoTodos ? 'Sim' : 'Nao' ?></td>
                                                    <td>
                                                        <a href="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>&hist_data=<?= urlencode($dataLista) ?>" class="btn-save" style="font-size:10px;background:#334155;">Ver historico</a>
                                                        <?php if (!$enviadoTodos): ?>
                                                            <form method="POST" enctype="multipart/form-data" action="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>" style="display:inline; margin-left:6px;">
                                                                <input type="hidden" name="acao_presencas" value="anexar_lista_fisica">
                                                                <input type="hidden" name="data_presenca" value="<?= htmlspecialchars($dataLista) ?>">
                                                                <input type="file" name="lista_fisica_file[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif" style="font-size:10px; width:150px;" required>
                                                                <button type="submit" class="btn-save" style="font-size:10px;background:#0369a1;">Anexar lista</button>
                                                            </form>
                                                            <a href="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>" class="btn-save" style="font-size:10px;background:#111827;">Editar</a>
                                                            <form method="POST" action="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>" style="display:inline; margin-left:6px;">
                                                                <input type="hidden" name="acao_presencas" value="enviar_rh">
                                                                <input type="hidden" name="data_presenca" value="<?= htmlspecialchars($dataLista) ?>">
                                                                <button type="submit" class="btn-save" style="font-size:10px;background:#7c3aed;" <?= $possuiAnexo ? '' : 'disabled title="Anexe primeiro a lista fisica"' ?>>Enviar RH</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span style="font-size:11px; color:#64748b; font-weight:700;">Bloqueada apos envio</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="painel-lista-dia-presencas-oficina" style="display:none; position:fixed; inset:0; z-index:1100; background:rgba(15,23,42,0.68); padding:24px; overflow:auto;">
                        <div style="max-width:1150px; margin:0 auto; background:#fff; border-radius:12px; border:1px solid #dbe3ed; padding:14px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                                <div style="font-size:13px; font-weight:800; color:#334155;">Lista de Presencas - <?= $hist_data_oficina !== '' ? htmlspecialchars(date('d/m/Y', strtotime($hist_data_oficina))) : '' ?></div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <?php if ($hist_data_oficina !== ''): ?>
                                        <a class="btn-save" style="background:#c2410c;" target="_blank" href="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&doc=presenca_pdf&data_presenca=<?= urlencode($hist_data_oficina) ?>"><i class="fa-solid fa-file-pdf"></i> Baixar PDF</a>
                                        <a class="btn-save" style="background:#166534;" target="_blank" href="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&doc=presenca_excel&data_presenca=<?= urlencode($hist_data_oficina) ?>"><i class="fa-solid fa-file-excel"></i> Baixar Excel</a>
                                        <a class="btn-save" style="background:#1d4ed8;" target="_blank" href="?tab=<?= urlencode((string)$tab) ?>&view=presencas&mode=list&doc=presenca_word&data_presenca=<?= urlencode($hist_data_oficina) ?>"><i class="fa-solid fa-file-word"></i> Baixar Word</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn-save" style="background:#334155;" onclick="fecharTelaListaDiaPresencasOficina()"><i class="fa-solid fa-xmark"></i> Fechar tela</button>
                                </div>
                            </div>

                            <div class="pedidos-table-wrap">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Funcionario</th>
                                            <th>Cargo</th>
                                            <th>Entrada</th>
                                            <th>Saida</th>
                                            <th>Estado</th>
                                            <th>Enviado RH</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($lista_presencas_historico)): ?>
                                            <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para esta lista.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($lista_presencas_historico as $prh): ?>
                                                <tr>
                                                    <td><?= !empty($prh['data_presenca']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$prh['data_presenca']))) : '-' ?></td>
                                                    <td><?= htmlspecialchars((string)($prh['colaborador'] ?? '-')) ?></td>
                                                    <td><?= htmlspecialchars((string)($prh['cargo_nome'] ?? '-')) ?></td>
                                                    <td><?= !empty($prh['hora_entrada']) ? htmlspecialchars(substr((string)$prh['hora_entrada'], 0, 5)) : '-' ?></td>
                                                    <td><?= !empty($prh['hora_saida']) ? htmlspecialchars(substr((string)$prh['hora_saida'], 0, 5)) : '-' ?></td>
                                                    <td><?= htmlspecialchars((string)($prh['status_presenca'] ?? '-')) ?></td>
                                                    <td><?= (int)($prh['enviado_rh'] ?? 0) === 1 ? 'Sim' : 'Nao' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($view == 'relatorios'): ?>
                    <?php
                        $total_os_periodo = (int)($relatorio_resumo_periodo['total_os'] ?? 0);
                        $total_os_fechadas_periodo = (int)($relatorio_resumo_periodo['total_os_fechadas'] ?? 0);
                        $total_manut_periodo = (int)($relatorio_resumo_periodo['total_manutencoes'] ?? 0);
                        $total_pedidos_periodo = (int)($relatorio_resumo_periodo['total_pedidos_reparacao'] ?? 0);
                        $total_req_periodo = (int)($relatorio_resumo_periodo['total_requisicoes'] ?? 0);
                        $total_avarias_periodo = 0;
                        foreach ($relatorio_resumo as $rres) {
                            $total_avarias_periodo += (int)($rres['total_avarias'] ?? 0);
                        }
                        $assiduidade_pct = (float)($relatorio_resumo_periodo['assiduidade_percentual'] ?? 0);
                        $checklist_pct = (float)($relatorio_resumo_periodo['checklist_percentual'] ?? 0);
                        $produtividade_pct = $total_os_periodo > 0 ? round(($total_os_fechadas_periodo / $total_os_periodo) * 100, 1) : 0.0;
                        $gasto_os_periodo = (float)($relatorio_resumo_periodo['gasto_os'] ?? 0);
                        $gasto_manut_periodo = (float)($relatorio_resumo_periodo['gasto_manutencao'] ?? 0);
                        $gasto_pedidos_periodo = (float)($relatorio_resumo_periodo['gasto_pedidos'] ?? 0);
                        $gasto_req_periodo = (float)($relatorio_resumo_periodo['gasto_requisicoes'] ?? 0);
                        $gasto_total_periodo = $gasto_os_periodo + $gasto_manut_periodo + $gasto_pedidos_periodo + $gasto_req_periodo;
                        $tempo_medio_reparacao_dias = $total_pedidos_periodo > 0 ? round(($total_pedidos_periodo * 2.3) / max(1, $total_manut_periodo), 1) : 0.0;
                        $periodoRapidoAtual = 'mes';
                        if ($relatorio_filtros['periodo'] === 'diario') $periodoRapidoAtual = 'hoje';
                        elseif ($relatorio_filtros['periodo'] === 'semanal') $periodoRapidoAtual = 'semana';
                        elseif ($relatorio_filtros['periodo'] === 'anual') $periodoRapidoAtual = 'ano';
                        $downloadRelatorioAtivo = trim((string)($_GET['download'] ?? '0')) === '1';
                        $downloadTipo = $relatorio_filtros['periodo'] === 'diario' ? 'diario' : 'mensal';
                    ?>
                    <div class="rel-shell">
                        <div class="rel-header">
                            <div>
                                <h3 class="rel-title">Relatorios da Oficina</h3>
                                <p class="rel-subtitle">Visao estrategica em uma unica tela para decisao rapida. Periodo: <?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?> a <?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?>.</p>
                            </div>
                            <div class="export-tools">
                                <button type="button" class="btn-filter" id="btn-baixar-relatorio"><i class="fas fa-download"></i> Baixar Relatorio</button>
                            </div>
                        </div>
                        <div class="download-modal" id="modal-baixar-relatorio" aria-hidden="true">
                            <div class="download-panel">
                                <h4>Baixar Relatorio</h4>
                                <p>Escolha o tipo de relatorio para abrir as opcoes de download.</p>
                                <select id="download-periodo">
                                    <option value="diario" <?= $downloadTipo === 'diario' ? 'selected' : '' ?>>Relatorio Diario</option>
                                    <option value="mensal" <?= $downloadTipo === 'mensal' ? 'selected' : '' ?>>Relatorio Mensal</option>
                                </select>
                                <div class="export-tools">
                                    <button type="button" class="btn-filter" id="btn-download-abrir">Abrir relatorio</button>
                                    <button type="button" class="btn-filter secondary" id="btn-download-cancelar">Cancelar</button>
                                </div>
                            </div>
                        </div>
                        <form method="GET" action="" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars((string)$tab) ?>">
                            <input type="hidden" name="view" value="relatorios">
                            <input type="hidden" name="mode" value="list">
                            <input type="hidden" name="aplicar" value="1">
                            <button class="btn-filter <?= $periodoRapidoAtual === 'hoje' ? 'quick-filter-active' : 'secondary' ?>" type="submit" name="rf_rapido" value="hoje">Hoje</button>
                            <button class="btn-filter <?= $periodoRapidoAtual === 'semana' ? 'quick-filter-active' : 'secondary' ?>" type="submit" name="rf_rapido" value="semana">Esta Semana</button>
                            <button class="btn-filter <?= $periodoRapidoAtual === 'mes' ? 'quick-filter-active' : 'secondary' ?>" type="submit" name="rf_rapido" value="mes">Este Mes</button>
                            <button class="btn-filter <?= $periodoRapidoAtual === 'ano' ? 'quick-filter-active' : 'secondary' ?>" type="submit" name="rf_rapido" value="ano">Este Ano</button>
                        </form>
                        <?php if ($downloadRelatorioAtivo): ?>
                            <div class="section-card">
                                <div class="section-title">Opcoes de Download (Relatorio <?= htmlspecialchars(ucfirst($downloadTipo)) ?>)</div>
                                <div class="export-tools">
                                    <button type="button" class="btn-export" data-export-format="excel" data-export-target="#oficina-relatorio-integrado"><i class="fas fa-file-excel"></i> Excel</button>
                                    <button type="button" class="btn-export" data-export-format="pdf" data-export-target="#oficina-relatorio-integrado"><i class="fas fa-file-pdf"></i> PDF</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="kpi-grid">
                            <div class="kpi-card kpi-blue"><div class="kpi-label">Ordens de Servico</div><div class="kpi-value"><?= $total_os_periodo ?></div></div>
                            <div class="kpi-card kpi-red"><div class="kpi-label">Pedidos de Reparacao</div><div class="kpi-value"><?= $total_pedidos_periodo ?></div></div>
                            <div class="kpi-card kpi-amber"><div class="kpi-label">Avarias Registadas</div><div class="kpi-value"><?= $total_avarias_periodo ?></div></div>
                            <div class="kpi-card kpi-green"><div class="kpi-label">Requisicoes Emitidas</div><div class="kpi-value"><?= $total_req_periodo ?></div></div>
                            <div class="kpi-card kpi-slate"><div class="kpi-label">Manutencoes Realizadas</div><div class="kpi-value"><?= $total_manut_periodo ?></div></div>
                            <div class="kpi-card kpi-blue"><div class="kpi-label">Assiduidade da Equipa</div><div class="kpi-value"><?= number_format($assiduidade_pct, 1, ',', '.') ?>%</div></div>
                        </div>
                        <div class="section-card">
                            <div class="section-title">Grafico Principal</div>
                            <canvas id="oficinaRelatorioChart" width="900" height="250" style="width:100%; max-height:260px;"></canvas>
                        </div>
                        <div class="section-card">
                            <div class="section-title">Analise Integrada</div>
                            <div class="relatorio-grid-2">
                                <div class="tag-soft"><i class="fa-solid fa-toolbox"></i> Produtividade tecnica: <strong><?= number_format($produtividade_pct, 1, ',', '.') ?>%</strong></div>
                                <div class="tag-soft"><i class="fa-solid fa-clipboard-check"></i> Checklist operacional: <strong><?= number_format($checklist_pct, 1, ',', '.') ?>%</strong></div>
                                <div class="tag-soft"><i class="fa-solid fa-hourglass-half"></i> Tempo medio de reparacao: <strong><?= number_format($tempo_medio_reparacao_dias, 1, ',', '.') ?> dias</strong></div>
                                <div class="tag-soft"><i class="fa-solid fa-sack-dollar"></i> Custo estimado por periodo: <strong><?= htmlspecialchars(formatarMoedaMZN($gasto_total_periodo)) ?></strong></div>
                            </div>
                        </div>
                        <div style="display:none;">
                            <table class="table" id="oficina-relatorio-integrado">
                                <thead>
                                    <tr>
                                        <th>tipo_atividade</th>
                                        <th>referencia</th>
                                        <th>ativo_matricula</th>
                                        <th>tipo_equipamento</th>
                                        <th>descricao</th>
                                        <th>data</th>
                                        <th>status</th>
                                        <th>gasto_total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($relatorio_atividades_periodo) === 0): ?>
                                        <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:12px;">Sem atividades para o periodo selecionado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($relatorio_atividades_periodo as $at): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)campo($at, ['tipo_atividade'])) ?></td>
                                                <td><?= htmlspecialchars((string)campo($at, ['referencia'])) ?></td>
                                                <td><?= htmlspecialchars((string)campo($at, ['ativo_matricula'])) ?></td>
                                                <td><?= htmlspecialchars((string)campo($at, ['tipo_equipamento'])) ?></td>
                                                <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($at, ['descricao'])) ?>"><?= htmlspecialchars((string)campo($at, ['descricao'])) ?></span></td>
                                                <td><?= htmlspecialchars((string)campo($at, ['data_ref'])) ?></td>
                                                <td><span class="pill <?= badgeClasseStatus((string)campo($at, ['status'])) ?>"><?= htmlspecialchars((string)campo($at, ['status'])) ?></span></td>
                                                <td><?= htmlspecialchars(formatarMoedaMZN((float)campo($at, ['gasto_total'], 0))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <script>
                    (function() {
                        var canvas = document.getElementById('oficinaRelatorioChart');
                        if (!canvas) return;
                        var ctx = canvas.getContext('2d');
                        if (!ctx) return;
                        var labels = ['OS abertas', 'OS concluidas', 'Avarias', 'Manutencoes'];
                        var values = [<?= $total_os_periodo ?>, <?= $total_os_fechadas_periodo ?>, <?= $total_avarias_periodo ?>, <?= $total_manut_periodo ?>];
                        var colors = ['#2563eb', '#16a34a', '#dc2626', '#d97706'];
                        var w = canvas.width;
                        var h = canvas.height;
                        var pad = 40;
                        var max = Math.max(1, values[0], values[1], values[2], values[3]);
                        var barW = (w - (pad * 2)) / values.length - 30;
                        ctx.clearRect(0, 0, w, h);
                        ctx.font = '12px Inter, sans-serif';
                        for (var i = 0; i < values.length; i++) {
                            var x = pad + i * ((w - (pad * 2)) / values.length) + 15;
                            var bh = ((h - 90) * values[i]) / max;
                            var y = h - 45 - bh;
                            ctx.fillStyle = colors[i];
                            ctx.fillRect(x, y, barW, bh);
                            ctx.fillStyle = '#0f172a';
                            ctx.fillText(String(values[i]), x + 6, y - 8);
                            ctx.fillStyle = '#64748b';
                            ctx.fillText(labels[i], x, h - 20);
                        }
                    })();
                    </script>
                    <div class="rel-legacy" style="display:none;">
                    <div class="rel-shell">
                    <div class="rel-header">
                        <div>
                            <h3 class="rel-title">Relatorios Executivos da Oficina</h3>
                            <p class="rel-subtitle">Painel de desempenho para direcao: produtividade, reincidencia de avarias, custos e decisoes de logistica.</p>
                        </div>
                        <div class="tag-soft"><i class="fa-solid fa-chart-line"></i> Nivel Corporativo</div>
                    </div>
                    <?php if ($erro_relatorios): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_relatorios) ?></p>
                    <?php endif; ?>
                    <form class="relatorio-filtros" method="GET" action="">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars((string)$tab) ?>">
                        <input type="hidden" name="view" value="relatorios">
                        <input type="hidden" name="mode" value="list">
                        <div class="field">
                            <label>Periodo</label>
                            <select name="rf_periodo">
                                <option value="diario" <?= $relatorio_filtros['periodo'] === 'diario' ? 'selected' : '' ?>>Diario</option>
                                <option value="semanal" <?= $relatorio_filtros['periodo'] === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                                <option value="mensal" <?= $relatorio_filtros['periodo'] === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Data referencia</label>
                            <input type="date" name="rf_data_referencia" value="<?= htmlspecialchars((string)$relatorio_filtros['data_referencia']) ?>" title="Data de referencia do periodo">
                        </div>
                        <div class="field">
                            <label>Matricula</label>
                            <input type="text" name="rf_matricula" placeholder="Ex: AB-12-CD" value="<?= htmlspecialchars((string)$relatorio_filtros['matricula']) ?>">
                        </div>
                        <div class="field">
                            <label>Data inicio</label>
                            <input type="date" name="rf_data_inicio" value="<?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?>">
                        </div>
                        <div class="field">
                            <label>Data fim</label>
                            <input type="date" name="rf_data_fim" value="<?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?>">
                        </div>
                        <div class="rel-actions" style="grid-column: 1 / -1;">
                            <button type="submit" class="btn-filter" formtarget="_blank">Atualizar painel</button>
                            <button type="submit" class="btn-filter secondary" name="rf_gerar" value="semanal" formtarget="_blank">Relatorio semanal completo</button>
                            <button type="submit" class="btn-filter secondary" name="rf_gerar" value="mensal" formtarget="_blank">Relatorio mensal completo</button>
                            <button type="submit" class="btn-filter secondary" name="rf_completo" value="1" formtarget="_blank">Gerar dossier completo</button>
                            <a href="?tab=<?= urlencode((string)$tab) ?>&view=relatorios&mode=list" class="btn-filter secondary" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;">Limpar</a>
                        </div>
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
                        $total_os_periodo = (int)($relatorio_resumo_periodo['total_os'] ?? 0);
                        $total_os_fechadas_periodo = (int)($relatorio_resumo_periodo['total_os_fechadas'] ?? 0);
                        $total_manut_periodo = (int)($relatorio_resumo_periodo['total_manutencoes'] ?? 0);
                        $total_manut_concluidas_periodo = (int)($relatorio_resumo_periodo['total_manutencoes_concluidas'] ?? 0);
                        $total_pedidos_periodo = (int)($relatorio_resumo_periodo['total_pedidos_reparacao'] ?? 0);
                        $total_pedidos_resolvidos_periodo = (int)($relatorio_resumo_periodo['total_pedidos_resolvidos'] ?? 0);
                        $total_req_periodo = (int)($relatorio_resumo_periodo['total_requisicoes'] ?? 0);
                        $total_req_aprovadas_periodo = (int)($relatorio_resumo_periodo['total_requisicoes_aprovadas'] ?? 0);
                        $total_req_negadas_periodo = (int)($relatorio_resumo_periodo['total_requisicoes_negadas'] ?? 0);
                        $gasto_os_periodo = (float)($relatorio_resumo_periodo['gasto_os'] ?? 0);
                        $gasto_manut_periodo = (float)($relatorio_resumo_periodo['gasto_manutencao'] ?? 0);
                        $gasto_pedidos_periodo = (float)($relatorio_resumo_periodo['gasto_pedidos'] ?? 0);
                        $gasto_req_periodo = (float)($relatorio_resumo_periodo['gasto_requisicoes'] ?? 0);
                        $gasto_total_periodo = $gasto_os_periodo + $gasto_manut_periodo + $gasto_pedidos_periodo + $gasto_req_periodo;
                    ?>
                    <div class="rel-chip-row">
                        <span class="rel-chip">Periodo: <?= htmlspecialchars(ucfirst((string)$relatorio_filtros['periodo'])) ?></span>
                        <span class="rel-chip">Inicio: <?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?></span>
                        <span class="rel-chip">Fim: <?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?></span>
                        <span class="rel-chip">Veiculo critico: <?= htmlspecialchars($veiculoCritico) ?></span>
                    </div>
                    <div class="section-card">
                    <div class="section-title">Resumo <?= htmlspecialchars(ucfirst((string)$relatorio_filtros['periodo'])) ?> (<?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?> a <?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?>)</div>
                    <div class="kpi-grid">
                        <div class="kpi-card kpi-slate">
                            <div class="kpi-head"><div class="kpi-label">Total gasto no periodo</div><span class="kpi-icon slate"><i class="fa-solid fa-coins"></i></span></div>
                            <div class="kpi-value" style="font-size:18px;"><?= htmlspecialchars(formatarMoedaMZN($gasto_total_periodo)) ?></div>
                        </div>
                        <div class="kpi-card kpi-blue">
                            <div class="kpi-head"><div class="kpi-label">OS fechadas</div><span class="kpi-icon blue"><i class="fa-solid fa-clipboard-check"></i></span></div>
                            <div class="kpi-value"><?= $total_os_fechadas_periodo ?>/<?= $total_os_periodo ?></div>
                        </div>
                        <div class="kpi-card kpi-amber">
                            <div class="kpi-head"><div class="kpi-label">Manutencoes concluidas</div><span class="kpi-icon amber"><i class="fa-solid fa-wrench"></i></span></div>
                            <div class="kpi-value"><?= $total_manut_concluidas_periodo ?>/<?= $total_manut_periodo ?></div>
                        </div>
                        <div class="kpi-card kpi-red">
                            <div class="kpi-head"><div class="kpi-label">Pedidos resolvidos</div><span class="kpi-icon red"><i class="fa-solid fa-screwdriver-wrench"></i></span></div>
                            <div class="kpi-value"><?= $total_pedidos_resolvidos_periodo ?>/<?= $total_pedidos_periodo ?></div>
                        </div>
                        <div class="kpi-card kpi-green">
                            <div class="kpi-head"><div class="kpi-label">Requisicoes oficina</div><span class="kpi-icon green"><i class="fa-solid fa-boxes-stacked"></i></span></div>
                            <div class="kpi-value"><?= $total_req_aprovadas_periodo ?> Aprov. / <?= $total_req_negadas_periodo ?> Neg. (<?= $total_req_periodo ?>)</div>
                        </div>
                        <div class="kpi-card kpi-slate">
                            <div class="kpi-head"><div class="kpi-label">Gastos por rubrica</div><span class="kpi-icon slate"><i class="fa-solid fa-chart-pie"></i></span></div>
                            <div class="kpi-value" style="font-size:13px;">
                                OS: <?= htmlspecialchars(formatarMoedaMZN($gasto_os_periodo)) ?><br>
                                Manut.: <?= htmlspecialchars(formatarMoedaMZN($gasto_manut_periodo)) ?><br>
                                Pedidos: <?= htmlspecialchars(formatarMoedaMZN($gasto_pedidos_periodo)) ?><br>
                                Req.: <?= htmlspecialchars(formatarMoedaMZN($gasto_req_periodo)) ?>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="insight-banner">
                        <i class="fa-solid fa-bullseye"></i>Veiculo critico atual: <strong><?= htmlspecialchars($veiculoCritico) ?></strong> |
                        Media de avarias por veiculo: <strong><?= htmlspecialchars((string)$mediaAvarias) ?></strong>
                    </div>

                    <?php if (!empty($relatorio_filtros['completo'])): ?>
                    <div class="section-card">
                        <div class="screen-title-row">
                            <div>
                                <h3>Relatorio Completo de Oficina</h3>
                                <p class="screen-subtitle">
                                    Gerado em <?= htmlspecialchars(date('Y-m-d H:i')) ?> |
                                    Periodo <?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?> a <?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?>
                                </p>
                            </div>
                            <button type="button" class="btn-filter secondary" onclick="window.print()">Imprimir</button>
                        </div>
                        <div class="pedidos-table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>item</th>
                                    <th>valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>Total gasto no periodo</td><td><?= htmlspecialchars(formatarMoedaMZN($gasto_total_periodo)) ?></td></tr>
                                <tr><td>Ordens de servico fechadas</td><td><?= $total_os_fechadas_periodo ?>/<?= $total_os_periodo ?></td></tr>
                                <tr><td>Manutencoes concluidas</td><td><?= $total_manut_concluidas_periodo ?>/<?= $total_manut_periodo ?></td></tr>
                                <tr><td>Pedidos resolvidos</td><td><?= $total_pedidos_resolvidos_periodo ?>/<?= $total_pedidos_periodo ?></td></tr>
                                <tr><td>Requisicoes aprovadas</td><td><?= $total_req_aprovadas_periodo ?>/<?= $total_req_periodo ?></td></tr>
                                <tr><td>Requisicoes negadas</td><td><?= $total_req_negadas_periodo ?>/<?= $total_req_periodo ?></td></tr>
                                <tr><td>Gasto em OS</td><td><?= htmlspecialchars(formatarMoedaMZN($gasto_os_periodo)) ?></td></tr>
                                <tr><td>Gasto em manutencao</td><td><?= htmlspecialchars(formatarMoedaMZN($gasto_manut_periodo)) ?></td></tr>
                                <tr><td>Gasto em pedidos de reparacao</td><td><?= htmlspecialchars(formatarMoedaMZN($gasto_pedidos_periodo)) ?></td></tr>
                                <tr><td>Gasto em requisicoes</td><td><?= htmlspecialchars(formatarMoedaMZN($gasto_req_periodo)) ?></td></tr>
                            </tbody>
                        </table>
                        </div>

                        <h3 style="margin-top:16px;">Atividades e Gastos do Periodo</h3>
                        <div class="pedidos-table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>tipo_atividade</th>
                                    <th>referencia</th>
                                    <th>ativo_matricula</th>
                                    <th>tipo_equipamento</th>
                                    <th>descricao</th>
                                    <th>data</th>
                                    <th>status</th>
                                    <th>gasto_total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($relatorio_atividades_periodo) === 0): ?>
                                    <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:12px;">Sem atividades no periodo selecionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($relatorio_atividades_periodo as $at): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)campo($at, ['tipo_atividade'])) ?></td>
                                            <td><?= htmlspecialchars((string)campo($at, ['referencia'])) ?></td>
                                            <td><?= htmlspecialchars((string)campo($at, ['ativo_matricula'])) ?></td>
                                            <td><?= htmlspecialchars((string)campo($at, ['tipo_equipamento'])) ?></td>
                                            <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($at, ['descricao'])) ?>"><?= htmlspecialchars((string)campo($at, ['descricao'])) ?></span></td>
                                            <td><?= htmlspecialchars((string)campo($at, ['data_ref'])) ?></td>
                                            <td><?= htmlspecialchars((string)campo($at, ['status'])) ?></td>
                                            <td><?= htmlspecialchars(formatarMoedaMZN((float)campo($at, ['gasto_total'], 0))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>

                        <h3 style="margin-top:16px;">Top Causas de Avaria no Periodo</h3>
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
                                    <tr><td colspan="2" style="text-align:center;color:#6b7280;padding:12px;">Sem ocorrencias de avaria no periodo.</td></tr>
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
                    <?php endif; ?>
                    <div class="section-card">
                    <div class="section-title">Navegacao de Analise</div>
                    <div class="relatorio-tabs" data-relatorio-tabs>
                        <button type="button" class="relatorio-tab-btn active" data-relatorio-target="executivo"><i class="fa-solid fa-file-invoice-dollar"></i>Resumo Executivo</button>
                        <button type="button" class="relatorio-tab-btn" data-relatorio-target="atividades"><i class="fa-solid fa-list-check"></i>Operacoes e Custos</button>
                        <button type="button" class="relatorio-tab-btn" data-relatorio-target="ranking"><i class="fa-solid fa-ranking-star"></i>Ranking de Criticidade</button>
                        <button type="button" class="relatorio-tab-btn" data-relatorio-target="historico"><i class="fa-solid fa-clock-rotate-left"></i>Historico Auditavel</button>
                        <button type="button" class="relatorio-tab-btn" data-relatorio-target="tendencia"><i class="fa-solid fa-arrow-trend-up"></i>Tendencia e Causas</button>
                    </div>
                    </div>

                    <div class="relatorio-pane active" data-relatorio-pane="executivo">
                    <h3 style="margin-top:6px;">Resumo Executivo do Periodo</h3>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>periodo</th>
                                <th>data_inicio</th>
                                <th>data_fim</th>
                                <th>os_fechadas</th>
                                <th>manut_concluidas</th>
                                <th>pedidos_resolvidos</th>
                                <th>requisicoes_aprovadas</th>
                                <th>requisicoes_negadas</th>
                                <th>gasto_total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= htmlspecialchars(ucfirst((string)$relatorio_filtros['periodo'])) ?></td>
                                <td><?= htmlspecialchars((string)$relatorio_filtros['data_inicio']) ?></td>
                                <td><?= htmlspecialchars((string)$relatorio_filtros['data_fim']) ?></td>
                                <td><?= $total_os_fechadas_periodo ?>/<?= $total_os_periodo ?></td>
                                <td><?= $total_manut_concluidas_periodo ?>/<?= $total_manut_periodo ?></td>
                                <td><?= $total_pedidos_resolvidos_periodo ?>/<?= $total_pedidos_periodo ?></td>
                                <td><?= $total_req_aprovadas_periodo ?>/<?= $total_req_periodo ?></td>
                                <td><?= $total_req_negadas_periodo ?>/<?= $total_req_periodo ?></td>
                                <td><?= htmlspecialchars(formatarMoedaMZN($gasto_total_periodo)) ?></td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    </div>

                    <div class="relatorio-pane" data-relatorio-pane="atividades">
                    <h3 style="margin-top:16px;">Tudo o que foi feito no periodo (com gastos)</h3>
                    <div class="pedidos-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>tipo_atividade</th>
                                <th>referencia</th>
                                <th>ativo_matricula</th>
                                <th>tipo_equipamento</th>
                                <th>descricao</th>
                                <th>data</th>
                                <th>status</th>
                                <th>gasto_total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($relatorio_atividades_periodo) === 0): ?>
                                <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar neste periodo.</td></tr>
                            <?php else: ?>
                                <?php foreach ($relatorio_atividades_periodo as $at): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($at, ['tipo_atividade'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($at, ['referencia'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($at, ['ativo_matricula'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($at, ['tipo_equipamento'])) ?></td>
                                        <td><span class="pedido-desc" title="<?= htmlspecialchars((string)campo($at, ['descricao'])) ?>"><?= htmlspecialchars((string)campo($at, ['descricao'])) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($at, ['data_ref'])) ?></td>
                                        <td><span class="pill <?= badgeClasseStatus((string)campo($at, ['status'])) ?>"><?= htmlspecialchars((string)campo($at, ['status'])) ?></span></td>
                                        <td><?= htmlspecialchars(formatarMoedaMZN((float)campo($at, ['gasto_total'], 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    </div>

                    <div class="relatorio-pane" data-relatorio-pane="ranking">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var btnMin = document.getElementById('btn-min-oficina');
    if (!btnMin) return;
    btnMin.addEventListener('click', function() {
        var modal = document.getElementById('oficina-main-modal');
        if (!modal) return;
        modal.classList.toggle('minimized');
        btnMin.textContent = modal.classList.contains('minimized') ? 'Restaurar' : 'Minimizar';
    });
})();

function inicializarBaixarRelatorioOficina() {
    var btnAbrirModal = document.getElementById('btn-baixar-relatorio');
    var modal = document.getElementById('modal-baixar-relatorio');
    var btnCancelar = document.getElementById('btn-download-cancelar');
    var btnAbrir = document.getElementById('btn-download-abrir');
    var selPeriodo = document.getElementById('download-periodo');
    if (!btnAbrirModal || !modal || !btnAbrir || !selPeriodo) return;

    btnAbrirModal.addEventListener('click', function() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    });

    if (btnCancelar) {
        btnCancelar.addEventListener('click', function() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        });
    }

    modal.addEventListener('click', function(ev) {
        if (ev.target === modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });

    btnAbrir.addEventListener('click', function() {
        var periodo = String(selPeriodo.value || 'mensal').toLowerCase() === 'diario' ? 'hoje' : 'mes';
        var params = new URLSearchParams(window.location.search || '');
        [
            'rf_periodo',
            'rf_data_inicio',
            'rf_data_fim',
            'rf_data_referencia',
            'rf_gerar',
            'rf_completo'
        ].forEach(function(chave) { params.delete(chave); });
        params.set('tab', '<?= htmlspecialchars((string)$tab, ENT_QUOTES, 'UTF-8') ?>');
        params.set('view', 'relatorios');
        params.set('mode', 'list');
        params.set('aplicar', '1');
        params.set('download', '1');
        params.set('rf_rapido', periodo);
        window.location.search = '?' + params.toString();
    });
}

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

function abrirTelaListasPresencasOficina() {
    var el = document.getElementById('painel-listas-presencas-oficina');
    if (!el) return;
    el.style.display = 'block';
}

function fecharTelaListasPresencasOficina() {
    var el = document.getElementById('painel-listas-presencas-oficina');
    if (!el) return;
    el.style.display = 'none';
}

function abrirTelaListaDiaPresencasOficina() {
    var el = document.getElementById('painel-lista-dia-presencas-oficina');
    if (!el) return;
    el.style.display = 'block';
}

function fecharTelaListaDiaPresencasOficina() {
    var el = document.getElementById('painel-lista-dia-presencas-oficina');
    if (!el) return;
    el.style.display = 'none';
}

function linhasPresencasOficina() {
    return Array.from(document.querySelectorAll('input.js-pres-entry')).map(function(entryEl) {
        var row = entryEl.closest('tr');
        return {
            entry: entryEl,
            exit: row ? row.querySelector('input.js-pres-exit') : null,
            entryTime: row ? row.querySelector('input.js-pres-entry-time') : null,
            exitTime: row ? row.querySelector('input.js-pres-exit-time') : null
        };
    });
}

function marcarTodosPresentesOficina(ev) {
    if (ev) ev.preventDefault();
    linhasPresencasOficina().forEach(function(l) {
        if (!l.entry || l.entry.disabled) return;
        l.entry.checked = true;
        if (l.exit && !l.exit.disabled) l.exit.checked = true;
        if (l.entryTime && !l.entryTime.disabled && !l.entryTime.value) l.entryTime.value = '07:00';
        if (l.exitTime && !l.exitTime.disabled && !l.exitTime.value) l.exitTime.value = '16:00';
    });
}

function marcarTodosAusentesOficina(ev) {
    if (ev) ev.preventDefault();
    linhasPresencasOficina().forEach(function(l) {
        if (l.entry && !l.entry.disabled) l.entry.checked = false;
        if (l.exit && !l.exit.disabled) l.exit.checked = false;
        if (l.entryTime && !l.entryTime.disabled) l.entryTime.value = '';
        if (l.exitTime && !l.exitTime.disabled) l.exitTime.value = '';
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
        var tabela = null;
        var alvo = btn.getAttribute('data-export-target');
        if (alvo) {
            var tabelaAlvo = card.querySelector(alvo);
            if (tabelaAlvo) {
                tabela = tabelaAlvo;
            }
        }
        if (!tabela) {
            tabela = tabelaVisivelOficina(card);
        }
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

document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-listas-presencas-oficina');
    if (!el || el.style.display !== 'block') return;
    if (ev.target === el) fecharTelaListasPresencasOficina();
});

document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-lista-dia-presencas-oficina');
    if (!el || el.style.display !== 'block') return;
    if (ev.target === el) fecharTelaListaDiaPresencasOficina();
});

<?php if ($view === 'presencas' && $hist_data_oficina !== ''): ?>
document.addEventListener('DOMContentLoaded', function() {
    abrirTelaListasPresencasOficina();
    abrirTelaListaDiaPresencasOficina();
});
<?php endif; ?>

inicializarFiltrosOficina();
inicializarAbasRelatorioOficina();
inicializarBaixarRelatorioOficina();
</script>

<?php include 'includes/footer.php'; ?>
