<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once __DIR__ . '/lib_relatorios.php';

$setores = relatorios_setores();

try {
    relatorios_garantir_estrutura($pdo);
    $cfg = relatorios_carregar_cfg($pdo);

    if ((int)$cfg['ativo'] !== 1) throw new RuntimeException('Envio automatico desativado.');
    if (trim((string)$cfg['destinatarios']) === '') throw new RuntimeException('Sem destinatario configurado.');

    $destinatarios = relatorios_emails_validos((string)$cfg['destinatarios']);
    if (!$destinatarios) throw new RuntimeException('Sem email valido configurado.');

    $periodo = relatorios_periodo_auto((string)$cfg['periodicidade'], (string)$cfg['hora_envio']);
    if ($periodo === null) throw new RuntimeException('Ainda nao chegou a hora prevista para envio.');
    if (relatorios_auto_enviado($pdo, $periodo, (string)$cfg['destinatarios'])) throw new RuntimeException('Periodo ja enviado automaticamente.');

    $linhas = relatorios_linhas_auditoria($pdo, $periodo, $setores);
    $financeiro = relatorios_financeiro_por_setor($pdo, $periodo, $setores);
    $consumo = relatorios_consumo_por_setor($pdo, $periodo, $setores);
    $resumo = relatorios_resumo_integrado($linhas, $setores, $periodo, $financeiro, $consumo);
    $html = relatorios_html($resumo, $cfg, 50);
    $assunto = relatorios_assunto($cfg, $periodo);

    [$ok, $mensagem] = relatorios_enviar($destinatarios, $assunto, $html);
    relatorios_log_envio($pdo, 'automatico', $periodo, (string)$cfg['destinatarios'], $assunto, $ok ? 'enviado' : 'falhou', $mensagem, (int)$resumo['total']);
    if ($ok && function_exists('registrarAcaoSistema')) registrarAcaoSistema($pdo, 'Enviou relatorio executivo automatico por tarefa agendada', 'relatorios_exec_envios');

    if (PHP_SAPI === 'cli') {
        echo ($ok ? 'OK: ' : 'ERRO: ') . $mensagem . PHP_EOL;
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo ($ok ? 'OK: ' : 'ERRO: ') . $mensagem;
    }
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        echo 'INFO: ' . $e->getMessage() . PHP_EOL;
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'INFO: ' . $e->getMessage();
    }
}
