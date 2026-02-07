<?php 
session_start();
require_once('config/db.php'); 
if (!isset($_SESSION['usuario_id'])) {
header("Location: login.php"); exit(); }
 
$tab = $_GET['tab'] ?? 'projetos'; // Alterado para iniciar em projetos como padrão
 
// Verifica se é administrador
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum'; 
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SIOV | Gestão Documental Vilcon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
           --vilcon-black: #1a1a1a; 
           --vilcon-orange: #f39c12; 
           --bg-white: #f4f7f6; 
           --border: #e1e8ed;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background: var(--vilcon-black); overflow: hidden; }

        /* SIDEBAR FIXA */
        .sidebar { width: 280px; background: var(--vilcon-black); display: flex; flex-direction: column; height: 100vh; flex-shrink: 0; border-right: 1px solid #333; }
        .sidebar-logo { padding: 30px 20px; text-align: center; }
        .sidebar-logo img { width: 160px; }
        .sidebar-logo p { color: var(--vilcon-orange); font-size: 10px; font-weight: bold; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px; }

        .nav-menu { flex: 1; overflow-y: auto; }
        .nav-link { padding: 14px 25px; color: #b3b3b3; text-decoration: none; display: flex; align-items: center; font-size: 13px; transition: 0.3s; border-left: 4px solid transparent; }
        .nav-link i { margin-right: 15px; color: var(--vilcon-orange); width: 20px; text-align: center; }
        .nav-link:hover, .nav-link.active { background: #252525; color: #fff; border-left-color: var(--vilcon-orange); }

        .sidebar-footer { padding: 20px; }
        .btn-sair { background: #c0392b; color: white; padding: 12px; border-radius: 6px; text-decoration: none; text-align: center; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 13px; }

        /* CONTEÚDO PRINCIPAL */
        .main-content { flex: 1; background: var(--bg-white); display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        .header-section { padding: 25px 40px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        
        .tab-menu { display: flex; gap: 8px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid var(--vilcon-orange); background: #fff; color: var(--vilcon-orange); transition: 0.3s; display: flex; align-items: center; gap: 6px; text-transform: uppercase; }
        .tab-btn:hover, .tab-btn.active { background: var(--vilcon-orange); color: #fff; }

        .container { padding: 30px 40px; }
        .white-card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid var(--border); }

        h3 { border-left: 5px solid var(--vilcon-black); padding-left: 15px; margin-bottom: 25px; color: var(--vilcon-black); font-size: 18px; text-transform: uppercase; }
        .section-title { grid-column: span 3; background: var(--vilcon-black); padding: 10px 15px; font-size: 11px; font-weight: bold; border-radius: 4px; color: #fff; text-transform: uppercase; margin: 10px 0; }

        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 11px; font-weight: 800; color: #555; margin-bottom: 6px; text-transform: uppercase; }
        input, select, textarea { padding: 11px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: #fff; }
        
        .doc-control { display: flex; gap: 8px; align-items: center; margin-top: 5px; }
        .doc-control input[type="date"] { flex: 1; padding: 7px; font-size: 12px; }
        .btn-upload { background: #f8f9fa; border: 1px dashed var(--vilcon-orange); padding: 8px 12px; border-radius: 4px; font-size: 10px; cursor: pointer; color: var(--vilcon-orange); font-weight: bold; display: flex; align-items: center; gap: 5px; position: relative; }

        .input-novo { margin-top: 10px; display: none; border-color: var(--vilcon-orange); }
        .btn-save { background: var(--vilcon-orange); color: white; border: none; padding: 15px 40px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 30px; text-transform: uppercase; width: fit-content; }

        /* Estilos da Tabela de Histórico */
        .table-historico { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-historico th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #666; border-bottom: 2px solid var(--border); }
        .table-historico td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .action-icons { display: flex; gap: 15px; font-size: 16px; }
        .action-icons a { color: #555; transition: 0.3s; }
        .icon-download { color: #27ae60 !important; }
        .icon-edit { color: #f39c12 !important; }
        .icon-delete { color: #e74c3c !important; }
        .restricted { opacity: 0.5; cursor: not-allowed; }

        /* Barra de Filtro Rápido */
        .filter-container { display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo"><img src="assets/logo-vilcon.png" alt="VILCON"><p>Sistema Integrado</p></div>
    <div class="nav-menu">
        <a href="index.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard & BI</a>
        <a href="modulo1_ativos.php" class="nav-link active"><i class="fas fa-folder-open"></i> Gestão Documental</a>
        <a href="#" class="nav-link"><i class="fas fa-tools"></i> Módulo de Oficina</a>
        <a href="#" class="nav-link"><i class="fas fa-truck"></i> Módulo de Transporte</a>
        <a href="#" class="nav-link"><i class="fas fa-boxes"></i> Módulo de Logística</a>
        <a href="#" class="nav-link"><i class="fas fa-check-circle"></i> Módulo de Aprovações</a>
        <a href="#" class="nav-link"><i class="fas fa-file-contract"></i> Relatórios & BI</a>
        <a href="#" class="nav-link"><i class="fas fa-users"></i> Utilizadores</a>
    </div>
    <div class="sidebar-footer"><a href="logout.php" class="btn-sair"><i class="fas fa-power-off"></i> SAIR</a></div>
</div>

<div class="main-content">
    <div class="header-section">
        <div class="tab-menu">
            <a href="?tab=projetos" class="tab-btn <?= $tab == 'projetos' ? 'active' : '' ?>"><i class="fas fa-project-diagram"></i> Projetos & Destinos</a>
            <a href="?tab=ativos" class="tab-btn <?= $tab == 'ativos' ? 'active' : '' ?>">Ativos</a>
            <a href="?tab=pessoal" class="tab-btn <?= $tab == 'pessoal' ? 'active' : '' ?>">Motoristas & Operadores</a>
            <a href="?tab=elevacao" class="tab-btn <?= $tab == 'elevacao' ? 'active' : '' ?>">Equipamento de Elevação</a>
            <a href="?tab=compra_venda" class="tab-btn <?= $tab == 'compra_venda' ? 'active' : '' ?>">Compra & Venda</a>
            <a href="?tab=seguranca" class="tab-btn <?= $tab == 'seguranca' ? 'active' : '' ?>">Segurança & Alertas</a>
            <a href="?tab=historico" class="tab-btn <?= $tab == 'historico' ? 'active' : '' ?>">Histórico</a>
        </div>
    </div>

    <div class="container">
        <div class="white-card">
            
            <?php
                // Carrega a view correspondente à aba atual a partir da pasta gestao_documental
                $allowed = ['projetos','ativos','pessoal','elevacao','compra_venda','seguranca','historico'];
                $dir = in_array($tab, $allowed) ? $tab : 'projetos';
                $viewPath = __DIR__ . '/gestao_documental/' . $dir . '/' . $dir . '_view.php';
                if (file_exists($viewPath)) {
                    include $viewPath;
                } else {
                    echo '<p>View não encontrada.</p>';
                }
            ?>
        </div>
    </div>
</div>

<script>
    function checkNovo(select, inputId) {
        var input = document.getElementById(inputId);
        if (select.value === 'novo') {
            input.style.display = 'block';
            input.focus();
        } else {
            input.style.display = 'none';
        }
    }
</script>
</body>
</html>