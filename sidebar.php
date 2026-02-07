<?php 
// Identifica a página para marcar o menu como ativo
$pagina_atual = basename($_SERVER['PHP_SELF']); 
?>
<div class="sidebar">
    <div class="sidebar-logo">
        <img src="assets/logo-vilcon.png" alt="VILCON">
        <p style="color: #f39c12; font-size: 10px; font-weight: bold; text-transform: uppercase; margin-top: 5px;">Sistema Integrado</p>
    </div>
    
    <div class="nav-menu">
        <a href="index.php" class="nav-link <?= ($pagina_atual == 'index.php') ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Menu Principal
        </a>
        
        <a href="modulo1_ativos.php" class="nav-link <?= ($pagina_atual == 'modulo1_ativos.php') ? 'active' : '' ?>">
            <i class="fas fa-folder-open"></i> Gestão Documental
        </a>
        
        <a href="modulo2_oficina.php" class="nav-link <?= ($pagina_atual == 'modulo2_oficina.php') ? 'active' : '' ?>">
            <i class="fas fa-tools"></i> Módulo de Oficina
        </a>
        
        <div style="padding: 15px 25px; font-size: 10px; color: #555; text-transform: uppercase; font-weight: bold;">Brevemente</div>
        
        <a href="javascript:void(0)" class="nav-link" style="opacity: 0.5; cursor: default;">
            <i class="fas fa-truck"></i> Transporte
        </a>
        <a href="javascript:void(0)" class="nav-link" style="opacity: 0.5; cursor: default;">
            <i class="fas fa-boxes"></i> Logística
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-sair">
            <i class="fas fa-power-off"></i> SAIR
        </a>
    </div>
</div>

<style>
    /* Estilos para garantir que o menu funcione visualmente */
    .sidebar { width: 280px; background: #1a1a1a; height: 100vh; display: flex; flex-direction: column; }
    .nav-link { 
        padding: 14px 25px; 
        color: #b3b3b3; 
        text-decoration: none; 
        display: flex; 
        align-items: center; 
        font-size: 13px; 
        border-left: 4px solid transparent;
        transition: 0.3s;
    }
    .nav-link i { margin-right: 15px; color: #f39c12; width: 20px; text-align: center; }
    .nav-link:hover, .nav-link.active { 
        background: #252525; 
        color: #fff; 
        border-left-color: #f39c12; 
    }
    .btn-sair { 
        background: #c0392b; 
        color: white; 
        padding: 12px; 
        margin: 20px; 
        border-radius: 6px; 
        text-decoration: none; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        gap: 10px; 
        font-weight: bold;
    }
</style>