<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit(); }
$tab = $_GET['tab'] ?? 'projetos';
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>

<h3>Registro de Aquisição e Alienação</h3>
<form class="form-grid" enctype="multipart/form-data">
   <div class="section-title">Dados da Compra</div>
   <div class="form-group"><label>Data da Compra</label><input type="date"></div>
   <div class="form-group"><label>Valor (MZN)</label><input type="text"></div>
   <div class="form-group"><label>Fotos da Compra</label><label class="btn-upload"><i class="fas fa-camera"></i> Anexar Fotos <input type="file" multiple style="display:none"></label></div>
    
   <div class="section-title">Dados de Venda</div>
   <div class="form-group"><label>Data da Venda</label><input type="date"></div>
   <div class="form-group"><label>Valor de Venda</label><input type="text"></div>
   <div class="form-group">
       <label>Fotos da Venda</label>
       <label class="btn-upload"><i class="fas fa-camera"></i> Anexar Fotos Venda <input type="file" multiple style="display:none"></label>
   </div>
    <div style="grid-column: span 3;"><button class="btn-save">Registrar Transação</button></div>
</form>