<?php
session_start();
require_once('config/db.php'); 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

/* =========================
   CONTROLES DO MÓDULO OFICINA
========================= */
$tab  = $_GET['tab']  ?? 'oficina';
$view = $_GET['view'] ?? 'ordens_servico';
$mode = $_GET['mode'] ?? 'list';

$proximo_os = "OS-OF-" . date('Y') . "-001";

// Página ativa sidebar
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>SIOV | Módulo Oficina</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --vilcon-black:#1a1a1a;
    --vilcon-orange:#f39c12;
    --bg-white:#f4f7f6;
    --border:#e1e8ed;
    --danger:#e74c3c;
    --success:#27ae60;
    --info:#3498db;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:Inter,sans-serif;}
body{display:flex;height:100vh;background:var(--vilcon-black);overflow:hidden;}

/* ===== SIDEBAR ===== */
.sidebar{width:280px;background:#1a1a1a;height:100vh;display:flex;flex-direction:column;position:fixed;left:0;top:0;}
.sidebar-logo{padding:25px;text-align:center;border-bottom:1px solid #333;}
.sidebar-logo img{width:140px;}
.nav-menu{flex:1;}
.nav-link{padding:14px 25px;color:#b3b3b3;text-decoration:none;display:flex;align-items:center;font-size:13px;border-left:4px solid transparent;transition:.3s;}
.nav-link i{margin-right:15px;color:#f39c12;width:20px;text-align:center;}
.nav-link:hover,.nav-link.active{background:#252525;color:#fff;border-left-color:#f39c12;}
.sidebar-footer{padding:15px;}
.btn-sair{background:#c0392b;color:#fff;padding:12px;border-radius:6px;text-decoration:none;display:flex;justify-content:center;align-items:center;gap:10px;font-weight:bold;}

/* ===== MAIN ===== */
.main-content{margin-left:280px;flex:1;background:var(--bg-white);display:flex;flex-direction:column;overflow-y:auto;}
.header-section{padding:20px 40px;background:#fff;border-bottom:1px solid var(--border);position:sticky;top:0;}
.tab-menu{display:flex;gap:10px;}
.tab-btn{padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:700;font-size:11px;border:1px solid #ddd;color:#666;text-transform:uppercase;}
.tab-btn.active{background:var(--vilcon-orange);color:#fff;border-color:var(--vilcon-orange);}
.sub-tab-container{background:#eee;padding:8px;border-radius:8px;margin:20px 40px;display:flex;gap:6px;flex-wrap:wrap;}
.sub-tab-btn{padding:8px 15px;border-radius:5px;text-decoration:none;font-size:10px;font-weight:700;color:#555;text-transform:uppercase;}
.sub-tab-btn.active{background:#fff;color:#000;box-shadow:0 2px 4px rgba(0,0,0,.1);}
.container{padding:10px 40px 40px;}
.white-card{background:#fff;border-radius:12px;padding:25px;border:1px solid var(--border);box-shadow:0 4px 12px rgba(0,0,0,.03);}
.inner-nav{display:flex;justify-content:space-between;margin-bottom:15px;border-bottom:1px dashed #ddd;padding-bottom:10px;}
.list-tools{display:flex;align-items:center;gap:8px;}
.search-group{display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #ddd;border-radius:20px;padding:6px 10px;}
.search-group i{color:#999;font-size:12px;}
.search-input{border:none;outline:none;font-size:12px;padding:2px 4px;background:transparent;min-width:180px;}
.filter-select{border:1px solid #ddd;border-radius:20px;padding:6px 10px;font-size:12px;background:#fff;}
.btn-mode{padding:6px 14px;border-radius:20px;font-size:11px;border:1px solid #ddd;text-decoration:none;color:#666;font-weight:700;}
.btn-mode.active{background:var(--vilcon-black);color:#fff;border-color:var(--vilcon-black);}
.form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;}
.section-title{grid-column:span 4;background:#f8f9fa;padding:10px;font-size:11px;font-weight:800;border-left:5px solid var(--vilcon-orange);margin-top:10px;text-transform:uppercase;}
.form-group{display:flex;flex-direction:column;}
label{font-size:10px;font-weight:800;color:#444;margin-bottom:4px;text-transform:uppercase;}
input,select,textarea{padding:10px;border:1px solid #ccc;border-radius:6px;font-size:13px;}
.btn-save{padding:12px;border-radius:6px;font-weight:700;font-size:11px;border:none;color:#fff;cursor:pointer;text-transform:uppercase;}
</style>
</head>

<body>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">
    <div class="sidebar-logo">
        <img src="assets/logo-vilcon.png" alt="VILCON">
        <p style="color:#f39c12;font-size:10px;font-weight:bold;text-transform:uppercase;margin-top:5px;">Sistema Integrado</p>
    </div>

    <div class="nav-menu">
        <a href="index.php" class="nav-link <?= ($pagina_atual=='index.php')?'active':'' ?>">
            <i class="fas fa-th-large"></i> Menu Principal
        </a>

        <a href="modulo2_oficina.php" class="nav-link active">
            <i class="fas fa-tools"></i> Oficina
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-sair"><i class="fas fa-power-off"></i> SAIR</a>
    </div>
</div>

<!-- ================= MAIN ================= -->
<div class="main-content">

<div class="header-section">
    <div class="tab-menu">
        <a class="tab-btn active"><i class="fas fa-tools"></i> Oficina</a>
        <a class="tab-btn"><i class="fas fa-warehouse"></i> Armazém</a>
    </div>
</div>

<!-- SUB MENU -->
<div class="sub-tab-container">
    <a href="?view=ordens_servico" class="sub-tab-btn <?= $view=='ordens_servico'?'active':'' ?>">Ordens Serviço</a>
    <a href="?view=pedidos_reparacao" class="sub-tab-btn <?= $view=='pedidos_reparacao'?'active':'' ?>">Pedidos Reparação</a>
    <a href="?view=manutencao" class="sub-tab-btn <?= $view=='manutencao'?'active':'' ?>">Manutenção</a>
    <a href="?view=checklist" class="sub-tab-btn <?= $view=='checklist'?'active':'' ?>">Checklist</a>
    <a href="?view=avarias" class="sub-tab-btn <?= $view=='avarias'?'active':'' ?>">Avarias</a>
    <a href="?view=relatorios" class="sub-tab-btn <?= $view=='relatorios'?'active':'' ?>">Relatórios</a>
</div>

<div class="container">
<div class="white-card">

<div class="inner-nav">
     <div class="mode-selector">
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=list" class="btn-mode <?= $mode == 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i> Ver Lista</a>
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=form" class="btn-mode <?= $mode == 'form' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Adicionar Novo</a>
                </div>
    <?php if($mode=='list'): ?>
    <div class="list-tools">
        <div class="search-group">
            <i class="fas fa-search"></i>
            <input class="search-input" type="text" placeholder="Pesquisar...">
        </div>
        <select class="filter-select">
            <?php if($view=='pedidos_reparacao'): ?>
                <option value="">Filtrar por status</option>
                <option>Pendente</option>
                <option>Em andamento</option>
                <option>Resolvido</option>
            <?php else: ?>
                <option value="">Filtrar por status</option>
                <option>Aberto</option>
                <option>Em andamento</option>
                <option>Fechado</option>
            <?php endif; ?>
        </select>
    </div>
    <?php endif; ?>
</div>

<?php if($mode=='form'): ?>

<!-- ================= ORDENS SERVIÇO ================= -->
<?php if($view=='ordens_servico'): ?>
<h3>Nova Ordem de Serviço</h3>
<p style="font-size:11px;color:var(--info);">OS Nº <?= $proximo_os ?></p>

<form class="form-grid" method="POST" action="salvar_os_oficina.php">

<div class="section-title">Equipamento</div>

<div class="form-group">
<label>Matrícula</label>
<input type="text" name="matricula">
</div>

<div class="form-group">
<label>Equipamento</label>
<input type="text" name="equipamento">
</div>

<div class="form-group">
<label>Operador</label>
<input type="text" name="operador">
</div>

<div class="form-group">
<label>Data Entrada</label>
<input type="datetime-local" name="data_entrada" value="<?= date('Y-m-d\TH:i') ?>">
</div>

<div class="section-title">Descrição</div>

<div class="form-group" style="grid-column:span 4;">
<textarea name="descricao" rows="4"></textarea>
</div>

<div style="grid-column:span 4;">
<button class="btn-save" style="background:var(--vilcon-black);width:100%;">Enviar OS</button>
</div>
</form>

<!-- ================= PEDIDOS DE REPARAÇÃO ================= -->
<?php elseif($view=='pedidos_reparacao'): ?>
<h3>Pedido de Reparação</h3>

<form class="form-grid" method="POST" action="salvar_pedido_reparacao.php">

<div class="section-title">Solicitante</div>

<div class="form-group">
<label>Nome do Solicitante</label>
<input type="text" name="solicitante">
</div>

<div class="form-group">
<label>Departamento</label>
<input type="text" name="departamento">
</div>

<div class="form-group">
<label>Data Pedido</label>
<input type="date" name="data_pedido" value="<?= date('Y-m-d') ?>">
</div>

<div class="form-group">
<label>Prioridade</label>
<select name="prioridade">
<option>Normal</option>
<option>Alta</option>
<option>Urgente</option>
</select>
</div>

<div class="section-title">Equipamento</div>

<div class="form-group">
<label>Matrícula / TAG</label>
<input type="text" name="matricula">
</div>

<div class="form-group">
<label>Equipamento</label>
<input type="text" name="equipamento">
</div>

<div class="section-title">Descrição do Problema</div>

<div class="form-group" style="grid-column:span 4;">
<textarea name="descricao_problema" rows="4"></textarea>
</div>

<div style="grid-column:span 4;">
<button class="btn-save" style="background:var(--danger);width:100%;">Enviar Pedido</button>
</div>
</form>

<?php endif; ?>

<?php else: ?>

<!-- ================= LISTAGEM ================= -->
<?php if($view=='pedidos_reparacao'): ?>
<h3>Lista de Pedidos de Reparação</h3>
<table width="100%" style="font-size:12px;border-collapse:collapse;">
<tr style="background:#f8f9fa;">
<th>ID</th><th>Solicitante</th><th>Equipamento</th><th>Data</th><th>Prioridade</th><th>Status</th>
</tr>
<tr>
<td colspan="6" style="text-align:center;color:#666;padding:12px;">Sem registos para mostrar.</td>
</tr>
</table>

<?php else: ?>
<h3>Lista de Registos</h3>
<table width="100%" style="font-size:12px;border-collapse:collapse;">
<tr style="background:#f8f9fa;">
<th>ID</th><th>Equipamento</th><th>Data</th><th>Status</th>
</tr>
<tr>
<td colspan="4" style="text-align:center;color:#666;padding:12px;">Sem registos para mostrar.</td>
</tr>
</table>
<?php endif; ?>

<?php endif; ?>

</div>
</div>
</div>

</body>
</html>


