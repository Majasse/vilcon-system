<?php

if (!function_exists('usuarioEhAdmin')) {
    function usuarioEhAdmin(): bool
    {
        $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
        return in_array($perfil, ['admin', 'administrador', 'superadmin', 'diretor', 'diretor geral'], true);
    }
}

if (!function_exists('garantirPermissao')) {
    function garantirPermissao(PDO $pdo, string $modulo, string $acao = 'ver'): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php');
            exit();
        }

        if (usuarioEhAdmin()) {
            return;
        }

        // Compatibilidade: se a tabela ainda nao existir, nao bloqueia o modulo.
        $tblExistsStmt = $pdo->query("SHOW TABLES LIKE 'usuarios_permissoes'");
        $tblExists = $tblExistsStmt ? (bool) $tblExistsStmt->fetchColumn() : false;
        if (!$tblExists) {
            return;
        }

        $coluna = 'pode_ver';
        if ($acao === 'editar') {
            $coluna = 'pode_editar';
        } elseif ($acao === 'aprovar') {
            $coluna = 'pode_aprovar';
        }

        $st = $pdo->prepare("SELECT `$coluna` FROM usuarios_permissoes WHERE usuario_id = :u AND modulo = :m LIMIT 1");
        $st->execute([
            ':u' => (int) ($_SESSION['usuario_id'] ?? 0),
            ':m' => $modulo,
        ]);

        $permitido = (int) ($st->fetchColumn() ?: 0) === 1;
        if (!$permitido) {
            http_response_code(403);
            exit('Acesso negado: sem permissao para este modulo.');
        }
    }
}

if (!function_exists('auditarPostBasico')) {
    function auditarPostBasico(PDO $pdo, ?string $modulo = null): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        if (!isset($_SESSION['usuario_id'])) {
            return;
        }

        $acao = 'POST';
        $keys = array_keys($_POST);
        if (!empty($keys)) {
            $acao .= ' ' . implode(',', array_slice($keys, 0, 6));
        }

        try {
            if (function_exists('registrarAcaoSistema')) {
                registrarAcaoSistema($pdo, $acao . ($modulo ? " [$modulo]" : ''), 'request', (int) $_SESSION['usuario_id']);
            } elseif (function_exists('registrarAuditoria')) {
                registrarAuditoria($pdo, $acao . ($modulo ? " [$modulo]" : ''), 'request');
            }
        } catch (Throwable $e) {
            // Nao interromper fluxo por falha de auditoria.
        }
    }
}
