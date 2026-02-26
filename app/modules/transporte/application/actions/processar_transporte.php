<?php 
session_start();

require_once __DIR__ . '/../app/config/db.php';

/* Proteção de sessão */
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

/* Variáveis de controlo */
$tab  = $_GET['tab']  ?? 'transporte';
$view = $_GET['view'] ?? 'entrada';
$mode = $_GET['mode'] ?? 'list';

$proximo_id_os = "OS-" . date('Y') . "-0042";

/* Base URL */
define('BASE_URL', '/vilcon-systemon/public');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SIOV | Vilcon Operations</title>

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="stylesheet" href="/vilcon-systemon/public/assets/css/vilcon-bias-theme.css">

    <style>
        :root {
            --vilcon-black: #1a1a1a;
            --vilcon-orange: #f39c12;
            --bg-white: #f4f7f6;
            --border: #e1e8ed;
            --danger: #e74c3c;
            --success: #27ae60;
            --info: #3498db;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            display: flex;
            height: 100vh;
            background: var(--vilcon-black);
            overflow: hidden;
        }

        .sidebar {
            width: 260px;
            background: var(--vilcon-black);
            border-right: 1px solid #333;
        }

        .sidebar-logo {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid #333;
        }

        .main-content {
            flex: 1;
            background: var(--bg-white);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .header-section {
            padding: 20px 40px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .tab-menu {
            display: flex;
            gap: 8px;
        }

        .tab-btn {
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            font-size: 11px;
            border: 1px solid #ddd;
            color: #666;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn.active {
            background: var(--vilcon-orange);
            color: #fff;
            border-color: var(--vilcon-orange);
        }

        .sub-tab-container {
            background: #eee;
            padding: 8px;
            border-radius: 8px;
            margin: 20px 40px 10px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .sub-tab-btn {
            padding: 8px 18px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            color: #555;
            text-transform: uppercase;
        }

        .sub-tab-btn.active {
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            color: var(--vilcon-black);
        }

        .container { padding: 10px 40px 40px; }

        .white-card {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .inner-nav {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ddd;
        }

        .btn-mode {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #ddd;
            color: #666;
            background: #fff;
        }

        .btn-mode.active {
            background: var(--vilcon-black);
            color: #fff;
        }
    </style>
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/global-loader.css">
</head>


<div id="vilcon-global-loader" class="vilcon-loader-overlay" aria-live="polite" aria-busy="true" aria-label="A processar">
    <div class="vilcon-loader-spinner" role="status" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</div>

<div class="sidebar">
    <div class="sidebar-logo">
        <img src="<?= BASE_URL ?>/assets/img/logo-vilcon.png" style="width:140px;">
        <p style="color:#666;font-size:9px;margin-top:10px;">OPERATIONS SYSTEM</p>
    </div>
</div>

<div class="main-content">

    <div class="header-section">
        <div class="tab-menu">
            <a href="?tab=transporte" class="tab-btn <?= $tab=='transporte'?'active':'' ?>">
                <i class="fas fa-route"></i> Transporte
            </a>
            <a href="?tab=gestao_frota" class="tab-btn <?= $tab=='gestao_frota'?'active':'' ?>">
                <i class="fas fa-shuttle-van"></i> Frota
            </a>
        </div>
    </div>

    <div class="sub-tab-container">
        <?php
        $subtabs = [
            'entrada' => 'Ordem de Serviço',
            'pedido_reparacao' => 'Pedido de Reparação',
            'checklist' => 'Checklist',
            'plano_manutencao' => 'Plano Manutenção',
            'avarias' => 'Registo Avarias',
            'relatorio_atividades' => 'Relatório Atividades'
        ];

        foreach ($subtabs as $key => $label):
        ?>
            <a href="?tab=<?= $tab ?>&view=<?= $key ?>&mode=list"
               class="sub-tab-btn <?= $view==$key?'active':'' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="container">
        <div class="white-card">
            <div class="inner-nav">
                <div>
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=list"
                       class="btn-mode <?= $mode=='list'?'active':'' ?>">Lista</a>

                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=form"
                       class="btn-mode <?= $mode=='form'?'active':'' ?>">Novo</a>
                </div>
            </div>

            <?php if ($mode === 'list'): ?>
                <div style="text-align:center;padding:60px;color:#999;">
                    <i class="fas fa-folder-open" style="font-size:40px;"></i>
                    <p>Sem registos para <b><?= strtoupper(str_replace('_',' ',$view)) ?></b></p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

    <script src="/vilcon-systemon/public/assets/js/global-loader.js"></script>
</body>
</html>


