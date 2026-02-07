<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit(); }
$tab = $_GET['tab'] ?? 'projetos';
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>

<h3>Gestão de Projetos e Centros de Custo</h3>
<form class="form-grid">
    <div class="section-title">Novo Projeto ou Frente de Trabalho</div>
    <div class="form-group">
        <label>Nome do Projeto / Obra</label>
        <input type="text" placeholder="Ex: Construção Ponte Maputo">
    </div>
   <div class="form-group">
       <label>Cliente / Destino</label>
        <select>
            <option value="interno">VILCON (TRABALHO INTERNO)</option>
           <option value="externo">CLIENTE EXTERNO</option>
       </select>
   </div>
   <div class="form-group">
       <label>Nome do Cliente</label>
        <input type="text" placeholder="Ex: Consultec / Vale">
   </div>
   <div class="form-group">
       <label>Localização / Província</label>
       <input type="text" placeholder="Ex: Tete, Moatize">
   </div>
   <div class="form-group">
       <label>Data de Início</label>
        <input type="date">
   </div>
   <div class="form-group">
       <label>Status do Projeto</label>
        <select>
           <option>Ativo</option>
           <option>Pausado</option>
           <option>Concluído</option>
       </select>
   </div>
    <div style="grid-column: span 3;"><button class="btn-save">Registrar Projeto</button></div>
</form>