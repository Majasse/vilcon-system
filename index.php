<?php 
session_start();
require_once('config/db.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SIOV Vilcon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #121212;
            --sidebar-bg: #1e1e1e;
            --accent-orange: #e67e22;
            --text-main: #ffffff;
            --text-dim: #b3b3b3;
            --card-bg: #252525;
            --sidebar-hover: #2c2c2c;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            background-color: var(--bg-dark); 
            color: var(--text-main); 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar Dark Mode */
        .sidebar { 
            width: 280px; 
            background: var(--sidebar-bg); 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            border-right: 1px solid #333;
        }

        .sidebar-header { 
            padding: 30px 20px; 
            text-align: center; 
            border-bottom: 1px solid #333; 
        }

        /* Logotipo Vilcon na Sidebar */
        .sidebar-header img { 
            width: 200px; 
            margin-bottom: 10px;
        }
        
        .sidebar-header h2 { 
            font-size: 11px; 
            color: var(--accent-orange); 
            letter-spacing: 2px; 
            font-weight: 700;
            text-transform: uppercase;
            font-weight: 700;
        }
        
        .sidebar ul { list-style: none; padding: 20px 0; overflow-y: auto; flex-grow: 1; }
        
        .sidebar ul li a { 
            color: var(--text-dim); 
            text-decoration: none; 
            padding: 14px 25px; 
            display: flex; 
            align-items: center;
            transition: 0.3s; 
            font-size: 14px; 
            font-weight: 500;
        }

        .sidebar ul li a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 18px;
            color: var(--accent-orange);
        }
        
        .sidebar ul li a:hover { 
            background: var(--sidebar-hover); 
            color: var(--text-main); 
            padding-left: 35px; 
        }

        .sidebar ul li a.active {
            background: rgba(230, 126, 34, 0.1);
            color: var(--text-main);
            border-left: 4px solid var(--accent-orange);
        }
        
        .btn-sair { 
            background: #c0392b !important; 
            color: white !important; 
            font-weight: bold; 
            justify-content: center;
            margin: 20px;
            border-radius: 4px;
        }
        .btn-sair i { color: white !important; }
        .btn-sair:hover { background: #e74c3c !important; transform: scale(1.02); }

        /* Área de Trabalho */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        .top-bar { 
            height: 70px;
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 0 40px;
            background: var(--sidebar-bg);
            border-bottom: 1px solid #333;
        }

        .top-bar h2 { font-size: 22px; font-weight: 600; }
        
        .user-info { font-size: 14px; color: var(--text-dim); }
        .user-info strong { color: var(--accent-orange); }

        /* Estilo dos Cards do Dashboard (Camada 0) */
        .dashboard-container { padding: 40px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            border-bottom: 4px solid var(--accent-orange);
            transition: 0.3s;
        }

        .stat-card:hover { transform: translateY(-5px); }
        .stat-card span { font-size: 12px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }
        .stat-card h3 { font-size: 28px; margin-top: 10px; font-weight: 700; }

        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .chart-box {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            min-height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
            border: 1px dashed #333;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="assets/logo-vilcon.png" alt="Vilcon Logo">
        <h2>Sistema Integrado</h2>
    </div>
    <ul>
        <li><a href="index.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard & BI</a></li>
        <li><a href="modulo1_ativos.php"><i class="fa-solid fa-folder-tree"></i> Gestão Documental</a></li>
        
        <li><a href="modulo2_oficina.php"><i class="fa-solid fa-screwdriver-wrench"></i> Módulo de Oficina</a></li>
        <li><a href="modulo3_transporte.php"><i class="fa-solid fa-truck-ramp-box"></i> Módulo de Transporte</a></li>
        <li><a href="modulo4_logistica.php"><i class="fa-solid fa-boxes-packing"></i> Módulo de Logística</a></li>
        
        <li><a href="#"><i class="fa-solid fa-file-circle-check"></i> Módulo de Aprovações</a></li>
        <li><a href="#"><i class="fa-solid fa-chart-pie"></i> Relatórios & BI</a></li>
        <li><a href="modulo7_utilizadores.php"><i class="fa-solid fa-user-shield"></i> Utilizadores</a></li>
        
        <li><a href="logout.php" class="btn-sair"><i class="fa-solid fa-power-off"></i> SAIR</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
        <h2>Dashboard Estratégico</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i> <strong><?php echo $_SESSION['usuario_nome']; ?></strong> | Perfil: <?php echo $_SESSION['usuario_perfil']; ?>
        </div>
    </div>
    
    <div class="dashboard-container">
        <div class="stats-grid">
            <div class="stat-card">
                <span>Ativos Totais</span>
                <h3>0</h3>
            </div>
            <div class="stat-card">
                <span>Manutenção Ativa</span>
                <h3 style="color: #f1c40f;">0</h3>
            </div>
            <div class="stat-card">
                <span>Contratos Aluguer</span>
                <h3>0</h3>
            </div>
            <div class="stat-card">
                <span>Custos Mes (MT)</span>
                <h3 style="color: #e74c3c;">0,00</h3>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-box"></div>
            <div class="chart-box"></div>
        </div>
    </div>
</div>

</body>
</html>