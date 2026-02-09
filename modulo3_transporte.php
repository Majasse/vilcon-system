<?php 
session_start();
require_once('config/db.php'); 

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$tab = $_GET['tab'] ?? 'transporte'; 
$view = $_GET['view'] ?? 'entrada'; 
$mode = $_GET['mode'] ?? 'list'; 
$proximo_id_os = "OS-" . date('Y') . "-0042"; 
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SIOV | Vilcon Operations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --vilcon-black: #1a1a1a; --vilcon-orange: #f39c12; --bg-white: #f4f7f6; --border: #e1e8ed; --danger: #e74c3c; --success: #27ae60; --info: #3498db; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background: var(--vilcon-black); overflow: hidden; }
        .sidebar { width: 260px; background: var(--vilcon-black); flex-shrink: 0; border-right: 1px solid #333; }
        .sidebar-logo { padding: 25px; text-align: center; border-bottom: 1px solid #333; }
        .main-content { flex: 1; background: var(--bg-white); display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        .header-section { padding: 20px 40px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .tab-menu { display: flex; gap: 8px; }
        .tab-btn { padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid #ddd; color: #666; text-transform: uppercase; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .tab-btn.active { background: var(--vilcon-orange); color: #fff; border-color: var(--vilcon-orange); }
        .sub-tab-container { background: #eee; padding: 8px; border-radius: 8px; margin: 20px 40px 10px 40px; display: flex; gap: 5px; flex-wrap: wrap; }
        .sub-tab-btn { padding: 8px 18px; border-radius: 5px; text-decoration: none; font-weight: 700; font-size: 10px; color: #555; text-transform: uppercase; transition: 0.2s; }
        .sub-tab-btn.active { background: #fff; color: var(--vilcon-black); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .inner-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #ddd; }
        .mode-selector { display: flex; gap: 10px; }
        .btn-mode { padding: 8px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; text-decoration: none; text-transform: uppercase; border: 1px solid #ddd; color: #666; background: #fff; }
        .btn-mode.active { background: var(--vilcon-black); color: #fff; border-color: var(--vilcon-black); }
        .container { padding: 10px 40px 40px 40px; }
        .white-card { background: #fff; border-radius: 12px; padding: 30px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .section-title { grid-column: span 4; background: #f8f9fa; padding: 12px; font-size: 11px; font-weight: 800; border-left: 5px solid var(--vilcon-orange); margin: 15px 0 5px 0; text-transform: uppercase; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 10px; font-weight: 800; color: #444; margin-bottom: 5px; text-transform: uppercase; }
        input, select, textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; outline: none; }
        .btn-save { padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 11px; border:none; color:white; }
        .history-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .history-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid var(--border); text-transform: uppercase; color: #777; font-size: 10px; }
        .history-table td { padding: 12px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo">
        <img src="assets/logo-vilcon.png" style="width:140px;">
        <p style="color:#666; font-size:9px; margin-top:10px; letter-spacing:1px;">OPERATIONS SYSTEM</p>
    </div>
</div>

<div class="main-content">
    <div class="header-section">
        <div class="tab-menu">
            <a href="?tab=transporte" class="tab-btn active"><i class="fas fa-route"></i> Transporte</a>
            <a href="?tab=gestao_frota" class="tab-btn"><i class="fas fa-shuttle-van"></i> Frota</a>
        </div>
    </div>

    <div class="sub-tab-container">
        <a href="?tab=transporte&view=entrada&mode=list" class="sub-tab-btn <?= $view == 'entrada' ? 'active' : '' ?>">Ordem de Serviço</a>
        <a href="?tab=transporte&view=pedido_reparacao&mode=list" class="sub-tab-btn <?= $view == 'pedido_reparacao' ? 'active' : '' ?>">Pedido de Reparação</a>
        <a href="?tab=transporte&view=checklist&mode=list" class="sub-tab-btn <?= $view == 'checklist' ? 'active' : '' ?>">Checklist</a>
        <a href="?tab=transporte&view=plano_manutencao&mode=list" class="sub-tab-btn <?= $view == 'plano_manutencao' ? 'active' : '' ?>">Plano Manutenção</a>
        <a href="?tab=transporte&view=avarias&mode=list" class="sub-tab-btn <?= $view == 'avarias' ? 'active' : '' ?>">Registo Avarias</a>
        <a href="?tab=transporte&view=relatorio_atividades&mode=list" class="sub-tab-btn <?= $view == 'relatorio_atividades' ? 'active' : '' ?>">Relatório Atividades</a>
    </div>

    <div class="container">
        <div class="white-card">
            <div class="inner-nav">
                <div class="mode-selector">
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=list" class="btn-mode <?= $mode == 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i> Ver Lista</a>
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=form" class="btn-mode <?= $mode == 'form' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Adicionar Novo</a>
                </div>
            </div>

            <?php if($mode == 'form'): ?>
                
                <?php if($view == 'pedido_reparacao'): ?>
                    <h3>Novo Pedido de Reparação</h3>
                    <form class="form-grid">
                        <div class="section-title">Dados do Ativo</div>
                        <div class="form-group"><label>Matrícula</label><input type="text"></div>
                        <div class="form-group"><label>KM Atual</label><input type="number"></div>
                        <div class="form-group"><label>Urgência</label><select><option>Normal</option><option>Crítica</option></select></div>
                        <div class="form-group" style="grid-column: span 4;"><label>Trabalhos Requisitados</label><textarea rows="3"></textarea></div>
                        <button class="btn-save" style="background:var(--vilcon-orange); grid-column: span 4;">Enviar Pedido</button>
                    </form>

                <?php elseif($view == 'checklist'): ?>
                    <h3>Novo Checklist de Veículo</h3>
                    <form class="form-grid">
                        <div class="section-title">Inspeção Visual</div>
                        <div class="form-group"><label>Viatura</label><input type="text"></div>
                        <div class="form-group"><label>Tipo</label><select><option>Saída</option><option>Entrada</option></select></div>
                        <div class="form-group" style="grid-column: span 4; display:flex; gap:20px; padding:15px; background:#f9f9f9;">
                            <label><input type="checkbox"> Pneus</label> <label><input type="checkbox"> Óleo</label> <label><input type="checkbox"> Luzes</label> <label><input type="checkbox"> Travões</label>
                        </div>
                        <button class="btn-save" style="background:var(--info); grid-column: span 4;">Finalizar Checklist</button>
                    </form>

                <?php elseif($view == 'plano_manutencao'): ?>
                    <h3>Registo de Manutenção de Equipamentos</h3>
                    <form class="form-grid">
                        <div class="section-title">Intervenção Realizada</div>
                        <div class="form-group"><label>Equipamento</label><input type="text"></div>
                        <div class="form-group"><label>Tipo</label><select><option>Preventiva</option><option>Corretiva</option></select></div>
                        <div class="form-group"><label>Data Próxima</label><input type="date"></div>
                        <div class="form-group" style="grid-column: span 4;"><label>Peças Substituídas / Óleo</label><textarea rows="2"></textarea></div>
                        <button class="btn-save" style="background:var(--success); grid-column: span 4;">Registar Manutenção</button>
                    </form>

                <?php elseif($view == 'avarias'): ?>
                    <h3>Registo de Avaria / Incidência</h3>
                    <form class="form-grid">
                        <div class="section-title">Dados da Ocorrência</div>
                        <div class="form-group"><label>Equipamento</label><input type="text"></div>
                        <div class="form-group"><label>Motorista</label><input type="text"></div>
                        <div class="form-group" style="grid-column: span 4;"><label>Descrição da Falha</label><textarea rows="3"></textarea></div>
                        <button class="btn-save" style="background:var(--danger); grid-column: span 4;">Registar Avaria</button>
                    </form>

                <?php elseif($view == 'relatorio_atividades'): ?>
                    <h3>Relatório de Atividades Diárias</h3>
                    <form class="form-grid">
                        <div class="section-title">Resumo da Jornada</div>
                        <div class="form-group"><label>Data</label><input type="date" value="<?=date('Y-m-d')?>"></div>
                        <div class="form-group"><label>KM Total</label><input type="number"></div>
                        <div class="form-group" style="grid-column: span 4;"><label>Atividades Realizadas / Clientes</label><textarea rows="4"></textarea></div>
                        <button class="btn-save" style="background:var(--vilcon-black); grid-column: span 4;">Submeter Relatório</button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align:center; padding:60px; color:#cbd5e0;">
                    <i class="fas fa-history" style="font-size:40px; margin-bottom:15px;"></i>
                    <p>Sem registos recentes de <b><?= strtoupper(str_replace('_',' ',$view)) ?></b> para este mês.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>