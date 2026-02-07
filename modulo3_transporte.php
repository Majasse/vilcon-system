<?php 
session_start();
require_once('config/db.php'); 

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$tab = $_GET['tab'] ?? 'transporte'; 
$view = $_GET['view'] ?? 'entrada'; 
$proximo_id_os = "OS-" . date('Y') . "-0042"; 
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SIOV | Vilcon Operations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --vilcon-black: #1a1a1a; --vilcon-orange: #f39c12; --bg-white: #f4f7f6; --border: #e1e8ed; --danger: #e74c3c; --success: #27ae60; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background: var(--vilcon-black); overflow: hidden; }
        
        .sidebar { width: 260px; background: var(--vilcon-black); flex-shrink: 0; border-right: 1px solid #333; }
        .sidebar-logo { padding: 25px; text-align: center; border-bottom: 1px solid #333; }
        
        .main-content { flex: 1; background: var(--bg-white); display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        .header-section { padding: 20px 40px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        
        .tab-menu { display: flex; gap: 8px; }
        .tab-btn { padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid #ddd; color: #666; text-transform: uppercase; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .tab-btn.active { background: var(--vilcon-orange); color: #fff; border-color: var(--vilcon-orange); }

        .sub-tab-container { background: #eee; padding: 8px; border-radius: 8px; margin: 20px 40px 10px 40px; display: flex; gap: 5px; }
        .sub-tab-btn { padding: 8px 18px; border-radius: 5px; text-decoration: none; font-weight: 700; font-size: 10px; color: #555; text-transform: uppercase; transition: 0.2s; }
        .sub-tab-btn.active { background: #fff; color: var(--vilcon-black); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        .container { padding: 10px 40px 40px 40px; }
        .white-card { background: #fff; border-radius: 12px; padding: 30px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .section-title { grid-column: span 4; background: #f8f9fa; padding: 12px; font-size: 11px; font-weight: 800; border-left: 5px solid var(--vilcon-orange); margin: 15px 0 5px 0; text-transform: uppercase; display: flex; justify-content: space-between; }
        
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 10px; font-weight: 800; color: #444; margin-bottom: 5px; text-transform: uppercase; }
        input, select, textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; outline: none; }
        input:focus { border-color: var(--vilcon-orange); }

        .btn-save { grid-column: span 4; background: var(--vilcon-orange); color: white; border: none; padding: 15px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 12px; margin-top: 10px; }
        
        .history-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 15px; }
        .history-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid var(--border); text-transform: uppercase; color: #777; font-size: 10px; }
        .history-table td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .badge-servico { background: #e1f5fe; color: #01579b; }
        .badge-avaria { background: #ffebee; color: #c62828; }
        .badge-manutencao { background: #e8f5e9; color: #2e7d32; }
        .badge-pendente { background: #fff3cd; color: #856404; }
        .auto-id { color: var(--vilcon-orange); font-weight: bold; }
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
            <a href="?tab=transporte" class="tab-btn <?= $tab == 'transporte' ? 'active' : '' ?>"><i class="fas fa-route"></i> Transporte</a>
            <a href="?tab=gestao_frota" class="tab-btn <?= $tab == 'gestao_frota' ? 'active' : '' ?>"><i class="fas fa-shuttle-van"></i> Gestão de Frota</a>
            <a href="?tab=aluguer" class="tab-btn <?= $tab == 'aluguer' ? 'active' : '' ?>"><i class="fas fa-heavy-lifting"></i> Aluguer de Equipamentos</a>
        </div>
    </div>

    <div class="sub-tab-container">
        <a href="?tab=<?= $tab ?>&view=entrada" class="sub-tab-btn <?= $view == 'entrada' ? 'active' : '' ?>">
            <?= ($tab == 'transporte') ? 'Ordem de Serviço / Guia' : 'Formulário de Entrada' ?>
        </a>
        <a href="?tab=<?= $tab ?>&view=historico" class="sub-tab-btn <?= $view == 'historico' ? 'active' : '' ?>">Histórico de Registos</a>
        
        <?php if($tab == 'transporte'): ?>
            <a href="?tab=transporte&view=reporte_hibrido" class="sub-tab-btn <?= $view == 'reporte_hibrido' ? 'active' : '' ?>">Reporte Híbrido (Avarias)</a>
            <a href="?tab=transporte&view=plano_manutencao" class="sub-tab-btn <?= $view == 'plano_manutencao' ? 'active' : '' ?>">Plano de Manutenção</a>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="white-card">
            
            <?php if($tab == 'transporte' && $view == 'entrada'): ?>
                <h3>Transporte: Abertura de Ordem de Serviço</h3>
                <form class="form-grid">
                    <div class="section-title">Identificação do Serviço <span>ID: <span class="auto-id"><?= $proximo_id_os ?></span></span></div>
                    <div class="form-group"><label>Guia Nº</label><input type="text" placeholder="Manual"></div>
                    <div class="form-group"><label>P.O</label><input type="text"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Data e Hora de Solicitação</label><input type="datetime-local"></div>
                    <div class="form-group"><label>TAG (Matrícula)</label><input type="text"></div>
                    <div class="form-group" style="grid-column: span 3;"><label>Descrição do Equipamento</label><input type="text"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Empresa / Cliente</label><input type="text"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Local / Destino</label><input type="text"></div>
                    <div class="section-title">Guia de Marcha / Programação</div>
                    <div class="form-group"><label>Data de Saída</label><input type="date"></div>
                    <div class="form-group"><label>Hora de Saída</label><input type="time"></div>
                    <div class="form-group"><label>KM Inicial</label><input type="number" placeholder="Lido no painel"></div>
                    <div class="form-group"><label>Motorista</label><input type="text"></div>
                    <div class="form-group" style="grid-column: span 4;"><label>Descrição detalhada do serviço a ser realizado</label><textarea rows="3"></textarea></div>
                    <button class="btn-save">Emitir Guia e Notificar Frota</button>
                </form>

            <?php elseif($tab == 'transporte' && $view == 'reporte_hibrido'): ?>
                <h3>Reporte Híbrido: Registro de Incidência Técnica</h3>
                <form class="form-grid">
                    <div class="section-title">Dados da Avaria</div>
                    <div class="form-group"><label>Viatura / Ativo</label><input type="text" placeholder="Ex: T9-VILCON"></div>
                    <div class="form-group"><label>Tipo de Reporte</label><select><option>APP (Condutor)</option><option>Verbal (Gestor)</option></select></div>
                    <div class="form-group"><label>Urgência</label><select><option>Baixa</option><option>Média</option><option style="color:red; font-weight:bold;">Crítica (Viatura Parada)</option></select></div>
                    <div class="form-group"><label>Data do Reporte</label><input type="date" value="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group" style="grid-column: span 4;"><label>Descrição do Problema / Sintomas</label><textarea rows="4"></textarea></div>
                    <button class="btn-save" style="background:#e67e22;">Registrar e Enviar para Oficina</button>
                </form>

            <?php elseif($tab == 'transporte' && $view == 'plano_manutencao'): ?>
                <h3>Registo no Plano de Manutenção Preventiva</h3>
                <form class="form-grid">
                    <div class="section-title">Identificação do Veículo</div>
                    <div class="form-group"><label>Equipamento</label><input type="text" placeholder="Ex: Pickup Truck"></div>
                    <div class="form-group"><label>Matrícula (TAG)</label><input type="text" placeholder="AIJ-268-MP"></div>
                    <div class="form-group"><label>Marca</label><input type="text" placeholder="Nissan"></div>
                    <div class="form-group"><label>Responsável</label><input type="text"></div>

                    <div class="section-title">Detalhes da Intervenção</div>
                    <div class="form-group"><label>Local da Manutenção</label><input type="text" placeholder="VILCON ou Externo"></div>
                    <div class="form-group"><label>Tipo de Intervenção</label><select><option>Preventiva</option><option>Correctiva</option></select></div>
                    <div class="form-group"><label>Periodicidade</label><input type="text" placeholder="Ex: 10,000 KM"></div>
                    <div class="form-group"><label>Data da Intervenção</label><input type="date"></div>

                    <div class="section-title">Controle de Quilometragem</div>
                    <div class="form-group"><label>KM da Última Manutenção</label><input type="number" step="0.1"></div>
                    <div class="form-group"><label>KM da Próxima Manutenção</label><input type="number" step="0.1"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Observações</label><input type="text"></div>

                    <button class="btn-save" style="background:var(--success);">Registar no Plano de Manutenção</button>
                </form>

            <?php elseif($tab == 'transporte' && $view == 'historico'): ?>
                <h3>Histórico Consolidado de Registos (Relatório de Atividades)</h3>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Ref / ID</th>
                            <th>Ativo</th>
                            <th>Cliente / Descrição</th>
                            <th>Status</th>
                            <th>Acção</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>20/01/2026</td>
                            <td><span class="badge badge-servico">Serviço</span></td>
                            <td class="auto-id">OS-2026-0041</td>
                            <td>AAB-100-MC</td>
                            <td>Consultec - Transporte de Maquinaria</td>
                            <td><span class="badge badge-pendente">Aguardando Frota</span></td>
                            <td><a href="#" class="btn-save" style="padding:5px 10px; font-size:9px;">Ver</a></td>
                        </tr>
                        <tr>
                            <td>19/01/2026</td>
                            <td><span class="badge badge-manutencao">Manutenção</span></td>
                            <td class="auto-id">PM-AIJ-268</td>
                            <td>AIJ-268-MP</td>
                            <td>Manutenção Preventiva (10.000 KM)</td>
                            <td><span class="badge" style="background:#2e7d32; color:#fff;">Realizado</span></td>
                            <td><a href="#" class="btn-save" style="background:#2e7d32; padding:5px 10px; font-size:9px;">Ver</a></td>
                        </tr>
                        <tr>
                            <td>20/01/2026</td>
                            <td><span class="badge badge-avaria">Avaria</span></td>
                            <td class="auto-id">REP-0922</td>
                            <td>T9-VILCON</td>
                            <td>Problema no travão de mão</td>
                            <td><span class="badge" style="background:#000; color:#fff;">Oficina</span></td>
                            <td><a href="#" class="btn-save" style="background:#333; padding:5px 10px; font-size:9px;">Ver</a></td>
                        </tr>
                    </tbody>
                </table>

            <?php elseif($tab == 'gestao_frota' && $view == 'entrada'): ?>
                <h3>Gestão de Frota: Validação e Combustível</h3>
                <form class="form-grid">
                    <div class="section-title">Controlo de Abastecimento Diário</div>
                    <div class="form-group"><label>Matrícula</label><input type="text"></div>
                    <div class="form-group"><label>QNT Diesel (Lts)</label><input type="number" step="0.01"></div>
                    <button class="btn-save" style="background:var(--success);">Confirmar Abastecimento</button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>