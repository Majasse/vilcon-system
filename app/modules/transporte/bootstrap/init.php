<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__) . '/domain/services/TransporteSchemaService.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

static $transporteSchemaMigrated = false;
if (!$transporteSchemaMigrated) {
    (new TransporteSchemaService())->migrate($pdo);
    $transporteSchemaMigrated = true;
}
