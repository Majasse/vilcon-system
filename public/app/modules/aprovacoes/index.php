<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Aprovações | Vilcon System';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Módulo de Aprovações</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong>
        </div>
    </div>

    <div class="dashboard-container aprov-wrapper">
        <style>
            .aprov-wrapper {
                padding: 18px;
            }
            .aprov-hero {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 18px;
                box-shadow: 0 6px 16px rgba(17,24,39,0.06);
                margin-bottom: 14px;
            }
            .aprov-hero h3 {
                margin: 0 0 6px;
                font-size: 18px;
                color: #111827;
            }
            .aprov-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 13px;
            }
            .aprov-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(220px, 1fr));
                gap: 12px;
            }
            .aprov-card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(17,24,39,0.05);
            }
            .aprov-card h4 {
                margin: 0 0 8px;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: .3px;
                color: #111827;
            }
            .aprov-card p {
                margin: 0;
                color: #4b5563;
                line-height: 1.5;
                font-size: 13px;
            }
            .aprov-flow {
                margin-top: 14px;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(17,24,39,0.05);
            }
            .aprov-flow h4 {
                margin: 0 0 10px;
                font-size: 13px;
                text-transform: uppercase;
                color: #111827;
            }
            .aprov-flow ol {
                margin: 0;
                padding-left: 18px;
                color: #374151;
                font-size: 13px;
            }
            .aprov-flow li {
                margin-bottom: 6px;
            }
            @media (max-width: 980px) {
                .aprov-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="aprov-hero">
            <h3>Central de Aprovações e Contraprova</h3>
            <p>Estrutura única para autorizações de custo e venda, com rastreio documental completo.</p>
        </div>

        <div class="aprov-grid">
            <section class="aprov-card">
                <h4>Funil Centralizador</h4>
                <p>Única porta de saída para autorização de qualquer custo ou venda.</p>
            </section>

            <section class="aprov-card">
                <h4>Formulários Profissionais</h4>
                <p>Geração de PDF com Logo da Empresa, Justificativa Técnica, Valor, Projeto e Quadro de Cotações.</p>
            </section>

            <section class="aprov-card">
                <h4>Protocolo de Contraprova</h4>
                <p>Diretor aprova, o sistema gera Guia de Autorização de Compra, o fornecedor assina e o logístico re-anexa como prova do preço autorizado.</p>
            </section>
        </div>

        <section class="aprov-flow">
            <h4>Fluxo Operacional</h4>
            <ol>
                <li>Submissão da solicitação com justificativa técnica e quadro comparativo de cotações.</li>
                <li>Aprovação do Diretor com emissão da Guia de Autorização de Compra.</li>
                <li>Assinatura do fornecedor na guia emitida pelo sistema.</li>
                <li>Re-anexo pelo logístico para contraprova do preço final praticado.</li>
            </ol>
        </section>
    </div>
</div>
