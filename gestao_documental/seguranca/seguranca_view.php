<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit(); }
$tab = $_GET['tab'] ?? 'projetos';
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>

<h3>Segurança & Alertas</h3>
<p>Monitorização de prazos e vencimentos críticos.</p>