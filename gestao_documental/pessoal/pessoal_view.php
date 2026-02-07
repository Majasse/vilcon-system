<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit(); }
$tab = $_GET['tab'] ?? 'projetos';
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>

<h3>Motoristas & Operadores</h3>
<form class="form-grid">
    <div class="section-title">Cadastro e Destino Operacional</div>
    <div class="form-group"><label>Nome Completo</label><input type="text"></div>
  
    <div class="form-group">
        <label>Projeto / Destino de Trabalho</label>
        <select>
           <option>TRABALHO INTERNO (SEDE/LOGÍSTICA)</option>
            <option>PROJETO X (EXEMPLO)</option>
       </select>
   </div>

   <div class="form-group">
       <label>Atividade Designada</label>
        <input type="text" placeholder="Ex: Operador de Escavadora">
    </div>

   <div class="form-group">
       <label>Categoria</label>
       <select><option>Motorista</option><option>Operador de Máquina</option></select>
   </div>
   <div class="form-group">
       <label>Tipo de Carta</label>
        <select><option>Profissional</option><option>Pesado</option><option>Ligeiro</option><option>Outra</option></select>
   </div>
   <div class="form-group"><label>Validade BI</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
   <div class="form-group"><label>Validade Carta</label><div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div></div>
    <div class="form-group">
        <label>Exame Médico / Medical (Validade)</label>
        <div class="doc-control"><input type="date"><label class="btn-upload">Anexo <input type="file" style="display:none"></label></div>
   </div>
    <div style="grid-column: span 3;"><button class="btn-save">Gravar Pessoal</button></div>
</form>