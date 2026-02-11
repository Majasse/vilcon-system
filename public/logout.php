<?php
session_start();
require_once(__DIR__ . '/../app/config/db.php');

if (isset($_SESSION['usuario_id'])) {
    try {
        registrarAcaoSistema($pdo, 'LOGOUT: utilizador terminou sessao', 'auth', (int)$_SESSION['usuario_id']);
    } catch (Throwable $e) {
        // Nao bloquear logout por falha de auditoria.
    }
}

$_SESSION = [];
session_destroy();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: /vilcon-systemon/public/login.php");
exit;

