<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['usuario_id'])) { header("Location: ../../login.php"); exit(); }
$tab = $_GET['tab'] ?? 'projetos';
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>

<h3>Gestão de Ativos Vilcon</h3>
<form class="form-grid" enctype="multipart/form-data">
	<div class="section-title">Informação Base e Alocação</div>
	<div class="form-group"><label>Matrícula / Código Interno</label><input type="text" placeholder="Ex: ABC-123-MC"></div>
    
	 <div class="form-group">
		  <label>Projeto / Localização Atual</label>
		  <select>
			  <option>TRABALHO INTERNO (SEDE/OFICINA)</option>
				<option>PROJETO X (EXEMPLO)</option>
			 </select>
	</div>

	<div class="form-group">
		 <label>Atividade Atual</label>
		  <input type="text" placeholder="Ex: Escavação, Carga, Em Manutenção">
		</div>

	<div class="form-group"><label>Nº de Chassi</label><input type="text" placeholder="Ex: 9BWZZZ... "></div>
	<div class="form-group">
		 <label>Tipo de Ativo</label>
		<select onchange="checkNovo(this, 'novo_tipo')">
			  <option>Bulldozer</option><option>Escavadora</option><option>Retroescavadora</option>
			  <option value="novo">-- Adicionar Novo --</option>
		  </select>
		  <input type="text" id="novo_tipo" class="input-novo" placeholder="Escreva o tipo aqui">
	 </div>
	<div class="form-group">
		 <label>Marca</label>
		  <select onchange="checkNovo(this, 'nova_marca')">
			  <option>Caterpillar</option><option>Volvo</option><option>Komatsu</option>
			  <option value="novo">-- Adicionar Nova --</option>
		  </select>
		  <input type="text" id="nova_marca" class="input-novo" placeholder="Escreva a marca aqui">
	 </div>

	 <div class="section-title">Documentos e Validades Individuais</div>
	 <div class="form-group"><label>Vencimento Seguro</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
	<div class="form-group"><label>Inspeção Periódica</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
	<div class="form-group"><label>Taxas de Rádio</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
	<div class="form-group"><label>Manifestos</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
	<div style="grid-column: span 3;"><button class="btn-save">Salvar Registro</button></div>
</form>

