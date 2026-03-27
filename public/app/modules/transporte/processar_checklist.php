<?php
session_start();
require_once dirname(__DIR__, 4) . '/app/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=transporte&view=checklist&mode=list');
    exit;
}

$payload = [
    'created_at' => date('c'),
    'usuario_id' => $_SESSION['usuario_id'] ?? null,
    'data_registo' => $_POST['data_registo'] ?? null,
    'horas' => $_POST['horas'] ?? null,
    'tipo_equipamento' => $_POST['tipo_equipamento'] ?? null,
    'km' => $_POST['km'] ?? null,
    'marca' => $_POST['marca'] ?? null,
    'sector' => $_POST['sector'] ?? null,
    'tipo_manutencao' => isset($_POST['tipo_manutencao']) && is_array($_POST['tipo_manutencao']) ? array_values($_POST['tipo_manutencao']) : [],
    'data_inicio' => $_POST['data_inicio'] ?? null,
    'hora_inicio' => $_POST['hora_inicio'] ?? null,
    'data_fim' => $_POST['data_fim'] ?? null,
    'hora_fim' => $_POST['hora_fim'] ?? null,
    'tempo_total_horas' => $_POST['tempo_total_horas'] ?? null,
    'tarifa_horaria' => $_POST['tarifa_horaria'] ?? null,
    'custo_total' => $_POST['custo_total'] ?? null,
    'servicos_realizados' => $_POST['servicos_realizados'] ?? null,
    'pecas_substituidas' => $_POST['pecas_substituidas'] ?? null,
    'condicao_operacional' => $_POST['condicao_operacional'] ?? null,
    'observacoes_tecnicas' => $_POST['observacoes_tecnicas'] ?? null,
    'manutencao_nome' => $_POST['manutencao_nome'] ?? null,
    'manutencao_cargo' => $_POST['manutencao_cargo'] ?? null,
    'manutencao_assinatura' => $_POST['manutencao_assinatura'] ?? null,
    'manutencao_data' => $_POST['manutencao_data'] ?? null,
    'operacao_nome' => $_POST['operacao_nome'] ?? null,
    'operacao_assinatura' => $_POST['operacao_assinatura'] ?? null,
    'operacao_data' => $_POST['operacao_data'] ?? null,
];

$storageDir = dirname(__DIR__, 4) . '/storage/logs';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

$storageFile = $storageDir . '/transporte_checklist_qualidade.jsonl';
file_put_contents($storageFile, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);

// Alimenta o painel HSE/Transporte (contadores de hoje) na tabela oficial.
try {
    $templateId = 0;
    $stmtTpl = $pdo->query("SELECT id FROM transporte_checklist_templates WHERE ativo = 1 ORDER BY nome ASC LIMIT 1");
    if ($stmtTpl) {
        $templateId = (int) $stmtTpl->fetchColumn();
    }

    if ($templateId > 0) {
        $stmtNext = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM transporte_checklist_registos");
        $nextId = $stmtNext ? (int) $stmtNext->fetchColumn() : 1;
        $codigo = 'CHK-' . date('Y') . '-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);

        $stmtIns = $pdo->prepare("
            INSERT INTO transporte_checklist_registos
            (codigo, template_id, viatura_id, tipo_equipamento, projeto, condutor, data_inspecao, inspector, observacoes, status_geral)
            VALUES
            (:codigo, :template_id, :viatura_id, :tipo_equipamento, :projeto, :condutor, :data_inspecao, :inspector, :observacoes, :status_geral)
        ");
        $stmtIns->execute([
            ':codigo' => $codigo,
            ':template_id' => $templateId,
            ':viatura_id' => (string) ($payload['km'] ?? ''), // Identificador disponível neste formulário.
            ':tipo_equipamento' => (string) ($payload['tipo_equipamento'] ?? ''),
            ':projeto' => (string) ($payload['sector'] ?? ''),
            ':condutor' => (string) ($payload['operacao_nome'] ?? ''),
            ':data_inspecao' => !empty($payload['data_registo']) ? (string) $payload['data_registo'] : date('Y-m-d'),
            ':inspector' => (string) ($payload['manutencao_nome'] ?? ''),
            ':observacoes' => (string) ($payload['observacoes_tecnicas'] ?? ''),
            ':status_geral' => ($payload['condicao_operacional'] === 'nao_apto') ? 'Nao Conforme' : 'Conforme',
        ]);
    }
} catch (Throwable $e) {
    // Nao bloquear o utilizador caso haja falha no espelho para HSE.
}

header('Location: index.php?tab=transporte&view=checklist&mode=list&saved=1');
exit;
