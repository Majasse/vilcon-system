<?php
session_start();
require_once('config/db.php');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID da Ordem de Serviço não fornecido.");
}

try {
    // Busca os detalhes da OS
    $stmt = $pdo->prepare("SELECT * FROM oficina_ordens_servico WHERE id = ?");
    $stmt->execute([$id]);
    $os = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        die("Ordem de Serviço não encontrada.");
    }
} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório OS #<?= $os['id'] ?> | SIOV</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f0f0; padding: 20px; }
        .report-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
        .logo-area img { width: 150px; }
        .info-area { text-align: right; }
        .info-area h2 { margin: 0; color: #f39c12; }
        
        .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .data-box { border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .label { font-size: 10px; font-weight: bold; color: #777; text-transform: uppercase; display: block; }
        .value { font-size: 14px; font-weight: 600; color: #333; }
        
        .description-box { border: 1px solid #eee; padding: 15px; margin-bottom: 30px; min-height: 100px; }
        
        .footer-signature { margin-top: 50px; display: flex; justify-content: space-between; }
        .sign { border-top: 1px solid #333; width: 45%; text-align: center; padding-top: 10px; font-size: 12px; }

        @media print {
            .no-print { display: none; }
            body { background: #fff; padding: 0; }
            .report-container { border: none; box-shadow: none; width: 100%; }
        }
        
        .btn-print { background: #333; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
    </style>
</head>
<body>

<div class="no-print" style="max-width: 800px; margin: 0 auto 10px; text-align: right;">
    <a href="modulo2_oficina.php" class="btn-print" style="background: #777;">Voltar</a>
    <button onclick="window.print()" class="btn-print">Imprimir Relatório</button>
</div>

<div class="report-container">
    <div class="header">
        <div class="logo-area">
            <img src="assets/logo-vilcon.png" alt="Vilcon">
            <p style="font-size: 10px; margin-top: 5px;"><?= NOME_SISTEMA ?></p>
        </div>
        <div class="info-area">
            <h2>ORDEM DE SERVIÇO</h2>
            <p><strong>Nº #<?= str_pad($os['id'], 5, '0', STR_PAD_LEFT) ?></strong></p>
            <p>Status: <?= strtoupper($os['status_os']) ?></p>
        </div>
    </div>

    <div class="grid-info">
        <div class="data-box">
            <span class="label">Ativo / Equipamento</span>
            <span class="value"><?= htmlspecialchars($os['ativo_matricula']) ?></span>
        </div>
        <div class="data-box">
            <span class="label">Data de Abertura</span>
            <span class="value"><?= date('d/m/Y H:i', strtotime($os['data_abertura'])) ?></span>
        </div>
        <div class="data-box">
            <span class="label">Setor Responsável</span>
            <span class="value"><?= htmlspecialchars($os['setor_tecnico']) ?></span>
        </div>
        <div class="data-box">
            <span class="label">Tipo de Intervenção</span>
            <span class="value"><?= htmlspecialchars($os['tipo_intervencao']) ?></span>
        </div>
        <div class="data-box">
            <span class="label">Técnico que Diagnosticou</span>
            <span class="value"><?= htmlspecialchars($os['tecnico_diagnostico']) ?></span>
        </div>
        <div class="data-box">
            <span class="label">KM/Horímetro</span>
            <span class="value"><?= htmlspecialchars($os['km_horimetro']) ?></span>
        </div>
    </div>

    <span class="label">Descrição Detalhada do Serviço / Avaria</span>
    <div class="description-box">
        <?= nl2br(htmlspecialchars($os['descricao_avaria'])) ?>
    </div>

    <div class="grid-info">
        <div class="data-box">
            <span class="label">Projeto / Destino Alocado</span>
            <span class="value"><?= htmlspecialchars($os['projeto_destino']) ?></span>
        </div>
        <div class="data-box">
            <span class="label">Data de Conclusão</span>
            <span class="value"><?= $os['data_conclusao'] ? date('d/m/Y H:i', strtotime($os['data_conclusao'])) : 'Pendente' ?></span>
        </div>
    </div>

    <div class="footer-signature">
        <div class="sign">
            Responsável Técnico<br>
            <strong><?= htmlspecialchars($os['validador_final']) ?></strong>
        </div>
        <div class="sign">
            Visto da Operação<br>
            (Assinatura/Carimbo)
        </div>
    </div>
</div>

</body>
</html>