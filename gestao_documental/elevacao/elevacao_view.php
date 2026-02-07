<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit(); }
$tab = $_GET['tab'] ?? 'projetos';
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>

<h3>Equipamento de Elevação de Cargas</h3>
<form class="form-grid">
   <div class="section-title">Registro de Equipamento</div>
   <div class="form-group"><label>Descrição / Nome</label><input type="text" placeholder="Ex: Guindaste 20T"></div>
   <div class="form-group"><label>Nº de Série</label><input type="text"></div>
   <div class="form-group"><label>Próxima Inspeção</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
   <div style="grid-column: span 3;"><button class="btn-save">Gravar Equipamento</button></div>
</form>