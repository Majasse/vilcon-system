<?php 
session_start();
require_once('config/db.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Buscar utilizadores
$sql = "SELECT id, nome, email, perfil, estado, data_criacao FROM usuarios ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Utilizadores | SIOV</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --bg-dark:#121212;
    --sidebar-bg:#1e1e1e;
    --accent:#e67e22;
    --text:#fff;
    --text-dim:#b3b3b3;
    --card:#252525;
    --hover:#2c2c2c;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:Inter,sans-serif;}
body{background:var(--bg-dark);color:var(--text);display:flex;height:100vh;overflow:hidden;}

/* SIDEBAR */
.sidebar{width:280px;background:var(--sidebar-bg);display:flex;flex-direction:column;border-right:1px solid #333;}
.sidebar-header{padding:25px;text-align:center;border-bottom:1px solid #333;}
.sidebar-header img{width:180px;}
.sidebar-header h2{font-size:11px;color:var(--accent);letter-spacing:2px;text-transform:uppercase;}
.sidebar ul{list-style:none;padding:20px 0;flex:1;overflow-y:auto;}
.sidebar ul li a{color:var(--text-dim);text-decoration:none;padding:14px 25px;display:flex;align-items:center;font-size:14px;transition:.3s;}
.sidebar ul li a i{margin-right:15px;color:var(--accent);}
.sidebar ul li a:hover{background:var(--hover);color:#fff;padding-left:35px;}
.sidebar ul li a.active{background:rgba(230,126,34,.15);border-left:4px solid var(--accent);color:#fff;}
.btn-sair{background:#c0392b!important;color:#fff!important;font-weight:bold;justify-content:center;margin:20px;border-radius:4px;}
.btn-sair:hover{background:#e74c3c!important;}

/* MAIN */
.main-content{flex:1;display:flex;flex-direction:column;overflow-y:auto;}
.top-bar{height:70px;background:var(--sidebar-bg);border-bottom:1px solid #333;display:flex;justify-content:space-between;align-items:center;padding:0 30px;}
.top-bar h2{font-size:20px;font-weight:600;}
.user-info{font-size:13px;color:var(--text-dim);}
.user-info strong{color:var(--accent);}

/* TABLE */
.container{padding:30px;}
.card{background:var(--card);padding:20px;border-radius:12px;border:1px solid #333;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th,td{padding:12px;border-bottom:1px solid #333;text-align:left;}
th{background:#1c1c1c;color:#ccc;text-transform:uppercase;font-size:11px;}
tr:hover{background:#1f1f1f;}
.status-ativo{color:#2ecc71;font-weight:bold;}
.status-inativo{color:#e74c3c;font-weight:bold;}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="sidebar-header">
<img src="assets/logo-vilcon.png">
<h2>Sistema Integrado</h2>
</div>

<ul>
<li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
<li><a href="modulo1_ativos.php"><i class="fa-solid fa-folder-tree"></i> Gestão Documental</a></li>
<li><a href="modulo2_oficina.php"><i class="fa-solid fa-screwdriver-wrench"></i> Oficina</a></li>
<li><a href="modulo3_transporte.php"><i class="fa-solid fa-truck"></i> Transporte</a></li>
<li><a href="modulo4_logistica.php"><i class="fa-solid fa-boxes"></i> Logística</a></li>

<li><a href="modulo7_utilizadores.php" class="active">
<i class="fa-solid fa-user-shield"></i> Utilizadores</a></li>

<li><a href="logout.php" class="btn-sair"><i class="fa-solid fa-power-off"></i> SAIR</a></li>
</ul>
</div>

<!-- MAIN -->
<div class="main-content">

<div class="top-bar">
<h2>Gestão de Utilizadores</h2>
<div class="user-info">
<i class="fa-regular fa-user"></i> 
<strong><?=$_SESSION['usuario_nome']?></strong> | Perfil: <?=$_SESSION['usuario_perfil']?>
</div>
</div>

<div class="container">
<div class="card">

<h3 style="margin-bottom:15px;">Lista de Utilizadores do Sistema</h3>

<table>
<thead>
<tr>
<th>ID</th>
<th>Nome</th>
<th>Email</th>
<th>Perfil</th>
<th>Estado</th>
<th>Data Criação</th>
</tr>
</thead>
<tbody>

<?php if($result && $result->num_rows > 0): ?>
<?php while($u = $result->fetch_assoc()): ?>
<tr>
<td><?= $u['id'] ?></td>
<td><?= $u['nome'] ?></td>
<td><?= $u['email'] ?></td>
<td><?= $u['perfil'] ?></td>
<td class="<?= $u['estado']=='Ativo'?'status-ativo':'status-inativo' ?>">
<?= $u['estado'] ?>
</td>
<td><?= date('d/m/Y H:i', strtotime($u['data_criacao'])) ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" style="text-align:center;color:#777;">Nenhum utilizador encontrado</td></tr>
<?php endif; ?>

</tbody>
</table>

</div>
</div>

</div>
</body>
</html>
