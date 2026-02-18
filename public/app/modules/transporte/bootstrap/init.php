<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}
