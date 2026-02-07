<?php 
session_start();
require_once('config/db.php'); 
 
if (!isset($_SESSION['usuario_id'])) {
   header("Location: login.php"); 
    exit(); 
}
 
$tab = $_GET['tab'] ?? 'ordens_servico'; 
$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum'; 
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SIOV | Módulo de Oficina - Vilcon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --vilcon-black: #1a1a1a; 
            --vilcon-orange: #f39c12; 
            --bg-white: #f4f7f6; 
             --border: #e1e8ed;
             --status-pendente: #e67e22;
             --status-oficina: #3498db;
            --status-concluido: #27ae60;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background: var(--vilcon-black); overflow: hidden; }

        .sidebar { width: 280px; background: var(--vilcon-black); display: flex; flex-direction: column; height: 100vh; flex-shrink: 0; border-right: 1px solid #333; }
        .sidebar-logo { padding: 30px 20px; text-align: center; }
        .sidebar-logo img { width: 160px; }
        
        .nav-menu { flex: 1; overflow-y: auto; }
        .nav-link { padding: 14px 25px; color: #b3b3b3; text-decoration: none; display: flex; align-items: center; font-size: 13px; transition: 0.3s; border-left: 4px solid transparent; }
        .nav-link i { margin-right: 15px; color: var(--vilcon-orange); width: 20px; text-align: center; }
       .nav-link:hover, .nav-link.active { background: #252525; color: #fff; border-left-color: var(--vilcon-orange); }

        .main-content { flex: 1; background: var(--bg-white); display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
       .header-section { padding: 25px 40px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        
        .tab-menu { display: flex; gap: 8px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid var(--vilcon-orange); background: #fff; color: var(--vilcon-orange); transition: 0.3s; display: flex; align-items: center; gap: 6px; text-transform: uppercase; }
       .tab-btn:hover, .tab-btn.active { background: var(--vilcon-orange); color: #fff; }

        .container { padding: 30px 40px; }
        .white-card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid var(--border); margin-bottom: 20px; }

        h3 { border-left: 5px solid var(--vilcon-black); padding-left: 15px; margin-bottom: 25px; color: var(--vilcon-black); font-size: 18px; text-transform: uppercase; }
        .section-title { grid-column: span 3; background: var(--vilcon-black); padding: 10px 15px; font-size: 11px; font-weight: bold; border-radius: 4px; color: #fff; text-transform: uppercase; margin: 10px 0; }

        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 11px; font-weight: 800; color: #555; margin-bottom: 6px; text-transform: uppercase; }
        input, select, textarea { padding: 11px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; }
        
        .btn-save { background: var(--vilcon-orange); color: white; border: none; padding: 15px 40px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 20px; text-transform: uppercase; }
        
        .table-os { width: 100%; border-collapse: collapse; margin-top: 20px; }
       .table-os th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 11px; color: #666; border-bottom: 2px solid var(--border); }
        .table-os td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; color: #fff; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo"><img src="assets/logo-vilcon.png" alt="VILCON"><p>Oficina & Manutenção</p></div>
    <div class="nav-menu">
        <a href="index.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="modulo1_ativos.php" class="nav-link"><i class="fas fa-folder-open"></i> Gestão Documental</a>
        <a href="modulo2_oficina.php" class="nav-link active"><i class="fas fa-tools"></i> Módulo de Oficina</a>
        <a href="modulo3_transporte.php" class="nav-link"><i class="fas fa-truck"></i> Módulo de Transporte</a>
    </div>
</div>

<div class="main-content">
    <div class="header-section">
        <div class="tab-menu">
            <a href="?tab=ordens_servico" class="tab-btn <?= $tab == 'ordens_servico' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> OS</a>
            <a href="?tab=solicitacao_pecas" class="tab-btn <?= $tab == 'solicitacao_pecas' ? 'active' : '' ?>"><i class="fas fa-box-open"></i> Peças</a>
            <a href="?tab=checklist" class="tab-btn <?= $tab == 'checklist' ? 'active' : '' ?>"><i class="fas fa-check-double"></i> Checklist</a>
            <a href="?tab=gestao_ferramentas" class="tab-btn <?= $tab == 'gestao_ferramentas' ? 'active' : '' ?>"><i class="fas fa-toolbox"></i> Ferramentas</a>
            <a href="?tab=prontuario" class="tab-btn <?= $tab == 'prontuario' ? 'active' : '' ?>"><i class="fas fa-history"></i> Prontuário</a>
            <a href="?tab=atividades" class="tab-btn <?= $tab == 'atividades' ? 'active' : '' ?>"><i class="fas fa-user-clock"></i> Atividades</a>
        </div>
    </div>

    <div class="container">
        <div class="white-card">

            <?php if(isset($_GET['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 13px;">✅ Operação realizada com sucesso!</div>
            <?php endif; ?>
            
            <?php if($tab == 'ordens_servico'): ?>
                <h3>Abertura de Ordem de Serviço Técnica</h3>
                <form class="form-grid" method="POST" action="processar_oficina.php">
                    <input type="hidden" name="acao" value="abrir_os">
                    <div class="section-title">Dados do Equipamento e Diagnóstico (Rastreabilidade)</div>
                    <div class="form-group"><label>Ativo (Matrícula/Chassi)</label><input type="text" name="ativo_matricula" placeholder="Ex: ABC-123-MC" required></div>
                    <div class="form-group"><label>Setor Técnico</label>
                        <select name="setor_tecnico">
                            <option>Mecânica Geral</option>
                            <option>Elétrica</option>
                            <option>Hidráulica</option>
                            <option>Pneumática</option>
                            <option>Ferreiro/Soldadura</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Tipo de Intervenção</label>
                        <select name="tipo_intervencao">
                            <option>Preventiva</option>
                            <option>Curativa (Avaria)</option>
                            <option>Externa (Pedido à Logística)</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;"><label>Descrição da Avaria/Serviço</label><textarea name="descricao_avaria" rows="3" required></textarea></div>
                    <div class="form-group"><label>Quem Diagnosticou (Nome do Técnico)</label><input type="text" name="tecnico_diagnostico" placeholder="Nome do Mecânico"></div>
                    
                    <div class="section-title">Alocação e Validação</div>
                    <div class="form-group"><label>Projeto Destino</label>
                        <select name="projeto_destino">
                            <option>Trabalho Interno</option>
                            <option>Projeto Pemba</option>
                            <option>Cliente Externo X</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Horímetro/KM Atual</label><input type="number" name="km_horimetro"></div>
                    <div class="form-group"><label>Responsável pela Validação Final</label><input type="text" name="validador_final" placeholder="Engenheiro/Chefe de Oficina"></div>
                    <div style="grid-column: span 3;"><button type="submit" class="btn-save">Abrir Ordem de Serviço</button></div>
                </form>

                <div class="section-title" style="margin-top: 40px;">Ordens de Serviço em Aberto / Recentes</div>
                <table class="table-os">
                    <thead>
                        <tr>
                            <th>Nº OS</th>
                            <th>Data</th>
                            <th>Ativo</th>
                            <th>Técnico</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Alterado de $conn para $pdo
                        $stmt = $pdo->query("SELECT * FROM oficina_ordens_servico ORDER BY id DESC LIMIT 10");
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($results) > 0):
                            foreach($results as $row):
                        ?>
                            <tr>
                                <td><strong>#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($row['data_abertura'])) ?></td>
                                <td><?= htmlspecialchars($row['ativo_matricula']) ?></td>
                                <td><?= htmlspecialchars($row['tecnico_diagnostico']) ?></td>
                                <td><span class="status-badge" style="background: <?= $row['status_os'] == 'Em Aberto' ? 'var(--status-oficina)' : 'var(--status-concluido)' ?>"><?= $row['status_os'] ?></span></td>
                                <td>
                                    <a href="visualizar_os.php?id=<?= $row['id'] ?>" title="Ver Detalhes"><i class="fas fa-eye" style="color:#333; margin-right:10px;"></i></a>
                                    <?php if($row['status_os'] != 'Concluído'): ?>
                                        <a href="finalizar_os.php?id=<?= $row['id'] ?>" title="Finalizar"><i class="fas fa-check-circle" style="color:var(--status-concluido);"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center;">Nenhuma OS pendente.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif($tab == 'solicitacao_pecas'): ?>
                <h3>Pedido de Peças à Logística</h3>
                <form class="form-grid" method="POST" action="processar_oficina.php">
                    <input type="hidden" name="acao" value="solicitar_pecas">
                    <div class="section-title">Requisição de Material</div>
                    <div class="form-group"><label>OS Referente</label><input type="text" name="os_referente" placeholder="Nº da OS"></div>
                    <div class="form-group"><label>Mecânico Solicitante</label><input type="text" name="solicitante" placeholder="Identificação Nominal"></div>
                    <div class="form-group"><label>Urgência</label><select name="urgencia"><option>Normal</option><option>Máquina Parada (Crítico)</option></select></div>
                    <div class="form-group" style="grid-column: span 3;"><label>Lista de Peças / Referências</label><textarea name="lista_pecas" rows="4" placeholder="Ex: 2x Filtro de Óleo CAT..."></textarea></div>
                    <div style="grid-column: span 3;"><button type="submit" class="btn-save" style="background:#2980b9;">Enviar para Logística</button></div>
                </form>

            <?php elseif($tab == 'checklist'): ?>
                <h3>Checklist de Entrada e Saída (Controlo de Danos)</h3>
                <form class="form-grid" method="POST" action="processar_oficina.php" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="salvar_checklist">
                    <div class="section-title">Inspeção Obrigatória</div>
                    <div class="form-group"><label>Viatura/Matrícula</label><input type="text" name="viatura" required></div>
                    <div class="form-group"><label>Operação</label><select name="tipo_operacao"><option value="Entrada">Entrada na Oficina</option><option value="Saída">Saída da Oficina</option></select></div>
                    <div class="form-group"><label>Fotos de Conservação</label><input type="file" name="fotos[]" multiple></div>
                    <div class="form-group" style="grid-column: span 3;"><label>Observações de Danos Existentes</label><textarea name="obs" rows="2"></textarea></div>
                    <div style="grid-column: span 3;"><button type="submit" class="btn-save">Registrar Checklist</button></div>
                </form>

            <?php elseif($tab == 'gestao_ferramentas'): ?>
                <h3>Uso e Atribuição de Ferramentas</h3>
                <form class="form-grid" method="POST" action="processar_oficina.php">
                    <input type="hidden" name="acao" value="registrar_ferramenta">
                    <div class="section-title">Registo de Utilização Diária</div>
                    <div class="form-group"><label>Data de Uso</label><input type="date" name="data_uso" value="<?= date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>Técnico (Utilizador)</label><input type="text" name="tecnico" placeholder="Nome do Técnico"></div>
                    <div class="form-group"><label>OS Associada</label><input type="text" name="os_referente" placeholder="Nº da OS ou Serviço"></div>
                    <div class="form-group"><label>Ferramenta Utilizada</label>
                        <select name="ferramenta">
                            <option>Scanner Diagnóstico</option><option>Torquímetro</option><option>Pistola Pneumática</option><option>Multímetro Digital</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Estado de Devolução</label><select name="estado_devolucao"><option>Bom Estado</option><option>Avaria/Dano</option></select></div>
                    <div class="form-group" style="grid-column: span 3;"><label>Serviço Executado</label><textarea name="servico_executado" rows="2"></textarea></div>
                    <div style="grid-column: span 3;"><button type="submit" class="btn-save" style="background:var(--vilcon-black);">Registar Uso</button></div>
                </form>

            <?php elseif($tab == 'prontuario'): ?>
                <h3>Prontuário (Histórico por Equipamento)</h3>
                <form method="GET" style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="hidden" name="tab" value="prontuario">
                    <input type="text" name="busca_matricula" placeholder="Digite a Matrícula..." value="<?= htmlspecialchars($_GET['busca_matricula'] ?? '') ?>" style="flex:1; padding:11px; border:1px solid var(--border); border-radius:6px;">
                    <button type="submit" class="tab-btn active">Buscar Histórico</button>
                </form>
                <?php if(isset($_GET['busca_matricula'])): ?>
                    <table class="table-os">
                        <thead><tr><th>Data</th><th>OS nº</th><th>Trabalho</th><th>Técnico</th><th>Status</th><th>Relatório</th></tr></thead>
                        <tbody>
                            <?php 
                            $busca = "%".$_GET['busca_matricula']."%";
                            // Corrigido para Sintaxe PDO
                            $stmt = $pdo->prepare("SELECT * FROM oficina_ordens_servico WHERE ativo_matricula LIKE ? ORDER BY id DESC");
                            $stmt->execute([$busca]);
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['data_abertura'])) ?></td>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars(substr($row['descricao_avaria'], 0, 40)) ?>...</td>
                                    <td><?= htmlspecialchars($row['tecnico_diagnostico']) ?></td>
                                    <td><span class="status-badge" style="background:<?= $row['status_os']=='Concluído'?'var(--status-concluido)':'var(--status-oficina)' ?>"><?= htmlspecialchars($row['status_os']) ?></span></td>
                                    <td><a href="visualizar_os.php?id=<?= $row['id'] ?>" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php elseif($tab == 'atividades'): ?>
                <h3>Relatórios de Atividades (Produtividade)</h3>
                <table class="table-os">
                    <thead><tr><th>Técnico</th><th>Ativo</th><th>Atividade</th><th>Horas</th><th>Data</th></tr></thead>
                    <tbody>
                        <?php 
                        $stmt_at = $pdo->query("SELECT * FROM oficina_atividades ORDER BY id DESC");
                        while($at = $stmt_at->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?= htmlspecialchars($at['tecnico_nome']) ?></td>
                                <td><?= htmlspecialchars($at['ativo_matricula']) ?></td>
                                <td><?= htmlspecialchars($at['atividade']) ?></td>
                                <td><?= htmlspecialchars($at['horas_gastas']) ?></td>
                                <td><?= date('d/m/Y', strtotime($at['data_atividade'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>