<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Documental | Vilcon System';

$view = $_GET['view'] ?? 'ativos';
$views = [
    'ativos' => 'ativos/ativos_view.php',
    'projetos' => 'projetos/projetos_view.php',
    'pessoal' => 'pessoal/pessoal_view.php',
    'seguranca' => 'seguranca/seguranca_view.php',
    'elevacao' => 'elevacao/elevacao_view.php',
    'compra_venda' => 'compra_venda/compra_venda_view.php',
];

$nivel_usuario = $_SESSION['usuario_nivel'] ?? 'comum';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Documental</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong>
        </div>
    </div>

    <div class="dashboard-container">
        <style>
            body { background: #f4f7f6; color: #111827; }
            .main-content { background: #f4f7f6; margin-left: 0; }
            .top-bar { background: #ffffff; border-bottom: 1px solid #e5e7eb; }
            .user-info { color: #6b7280; }
            .user-info strong { color: #111827; }
            .tabs {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }
            .tab {
                padding: 8px 14px;
                border-radius: 999px;
                background: #ffffff;
                color: #6b7280;
                text-decoration: none;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                border: 1px solid #e5e7eb;
            }
            .tab.active {
                background: #f59e0b;
                color: #fff;
                border-color: #f59e0b;
            }
            .card {
                background: #ffffff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 6px 16px rgba(17,24,39,0.08);
                border: 1px solid #e5e7eb;
            }
            .form-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
            .section-title { grid-column: span 4; background:#f8fafc; padding:10px; font-size:11px; font-weight:800; border-left:5px solid #f59e0b; text-transform:uppercase; }
            .form-group { display:flex; flex-direction:column; }
            label { font-size:10px; font-weight:800; color:#6b7280; margin-bottom:5px; text-transform:uppercase; }
            input, select, textarea { padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; background:#ffffff; color:#111827; }
            .btn-save { padding:10px 14px; border-radius:6px; font-weight:700; font-size:11px; border:none; color:#fff; background:#111827; cursor:pointer; }
            .btn-upload { background:#f3f4f6; border:1px solid #e5e7eb; padding:8px 10px; border-radius:6px; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; color:#111827; }
            .doc-control { display:flex; align-items:center; gap:8px; }
            .input-novo { margin-top:6px; display:none; }

            .tool-header {
                display:flex;
                justify-content:space-between;
                gap:16px;
                align-items:flex-start;
                margin-bottom:18px;
                padding-bottom:12px;
                border-bottom:1px solid #eef2f7;
                flex-wrap:wrap;
            }
            .tool-title h3 { margin:0 0 6px 0; }
            .tool-title p { margin:0; font-size:12px; color:#6b7280; line-height:1.45; }
            .tool-actions { display:flex; gap:10px; flex-wrap:wrap; }
            .btn-mode {
                border:1px solid #d1d5db;
                background:#ffffff;
                color:#111827;
                padding:9px 12px;
                border-radius:8px;
                font-size:12px;
                font-weight:700;
                cursor:pointer;
                min-height:36px;
            }
            .btn-mode i { margin-right:6px; }
            .btn-mode.active { background:#111827; color:#ffffff; border-color:#111827; }

            .filter-container {
                display:flex;
                gap:12px;
                row-gap:10px;
                align-items:flex-end;
                flex-wrap:wrap;
                margin-bottom:18px;
                padding:14px;
                border:1px solid #e5e7eb;
                border-radius:10px;
                background:#f9fafb;
            }
            .filter-container .form-group { min-width:220px; margin:0; }
            .filter-container .btn-save { min-height:38px; white-space:nowrap; }
            .panel-view.hidden { display:none; }
            .list-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
            .list-table th { text-align:left; font-size:11px; text-transform:uppercase; color:#6b7280; padding:10px; border-bottom:1px solid #e5e7eb; }
            .list-table td { padding:10px; border-bottom:1px solid #f1f5f9; }
            .list-table tr:hover { background:#f8fafc; }
            .pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
            .pill.ok { background:#ecfdf3; color:#16a34a; border:1px solid #bbf7d0; }
            .pill.warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
            .pill.info { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
            .card-tools {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                margin-bottom: 18px;
                padding-bottom: 12px;
                border-bottom: 1px solid #eef2f7;
                flex-wrap: wrap;
            }
            .btn-export {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
                min-height: 36px;
            }
            .btn-export i { margin-right: 6px; }

            @media (max-width: 900px) {
                .tool-actions,
                .card-tools { width: 100%; justify-content: flex-start; }
                .filter-container .form-group { min-width: 100%; }
            }
        </style>

        <div class="tabs">
            <a class="tab <?= $view === 'ativos' ? 'active' : '' ?>" href="?view=ativos">Ativos</a>
            <a class="tab <?= $view === 'projetos' ? 'active' : '' ?>" href="?view=projetos">Projetos</a>
            <a class="tab <?= $view === 'pessoal' ? 'active' : '' ?>" href="?view=pessoal">Pessoal</a>
            <a class="tab <?= $view === 'seguranca' ? 'active' : '' ?>" href="?view=seguranca">Seguranca</a>
            <a class="tab <?= $view === 'elevacao' ? 'active' : '' ?>" href="?view=elevacao">Elevacao</a>
            <a class="tab <?= $view === 'compra_venda' ? 'active' : '' ?>" href="?view=compra_venda">Compra/Venda</a>
        </div>

        <div class="card">
            <div class="card-tools">
                <button type="button" class="btn-export" data-export-format="excel">
                    <i class="fas fa-file-excel"></i> Baixar Excel
                </button>
                <button type="button" class="btn-export" data-export-format="pdf">
                    <i class="fas fa-file-pdf"></i> Baixar PDF
                </button>
            </div>
            <?php
                if (isset($views[$view])) {
                    include __DIR__ . '/' . $views[$view];
                } else {
                    echo '<p>Vista invalida.</p>';
                }
            ?>
        </div>

        <script>
        function checkNovo(selectEl, inputId) {
            var input = document.getElementById(inputId);
            if (!input) return;
            if (selectEl && selectEl.value === 'novo') {
                input.style.display = 'block';
                input.focus();
            } else {
                input.style.display = 'none';
                input.value = '';
            }
        }

        document.querySelectorAll('.btn-mode').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var scope = btn.closest('[data-mode-scope]');
                if (!scope) return;

                scope.querySelectorAll('.btn-mode').forEach(function(item) {
                    item.classList.remove('active');
                });

                scope.querySelectorAll('.panel-view').forEach(function(panel) {
                    panel.classList.add('hidden');
                });

                btn.classList.add('active');
                var targetId = btn.getAttribute('data-target');
                var target = document.getElementById(targetId);
                if (target) {
                    target.classList.remove('hidden');
                }
            });
        });

        function limparTexto(valor) {
            return (valor || '').replace(/\s+/g, ' ').trim();
        }

        function tabelaVisivel(root) {
            var tabelas = root.querySelectorAll('table');
            for (var i = 0; i < tabelas.length; i++) {
                var t = tabelas[i];
                if (!t.closest('.hidden') && t.offsetParent !== null) {
                    return t;
                }
            }
            return null;
        }

        function nomeArquivo(base, ext) {
            var data = new Date();
            var y = data.getFullYear();
            var m = String(data.getMonth() + 1).padStart(2, '0');
            var d = String(data.getDate()).padStart(2, '0');
            return base + '_' + y + m + d + '.' + ext;
        }

        function exportarExcel(tabela, base) {
            var html = '<html><head><meta charset="UTF-8"></head><body>' + tabela.outerHTML + '</body></html>';
            var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = nomeArquivo(base, 'xls');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function exportarPdf(tabela, titulo) {
            var janela = window.open('', '_blank');
            if (!janela) return;
            var logoUrl = window.location.origin + '/vilcon-systemon/public/assets/img/logo-vilcon.png';
            var dataAtual = new Date().toLocaleString('pt-PT');
            var tabelaHtml = tabela.outerHTML;
            var html = `
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>${titulo}</title>
                    <style>
                        @page { margin: 18mm 12mm; }
                        body { font-family: Arial, sans-serif; color: #111111; }
                        .pdf-wrap { width: 100%; }
                        .pdf-header { border: 2px solid #111111; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
                        .pdf-strip { height: 10px; background: #f4b400; }
                        .pdf-head-content { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #ffffff; }
                        .pdf-brand { display: flex; align-items: center; gap: 12px; }
                        .pdf-brand img { width: 130px; height: auto; object-fit: contain; }
                        .pdf-brand h1 { margin: 0; font-size: 18px; color: #111111; letter-spacing: 0.4px; }
                        .pdf-meta { text-align: right; font-size: 11px; color: #333333; }
                        .pdf-meta strong { display: block; color: #111111; margin-bottom: 4px; }
                        h2 { margin: 0 0 10px 0; color: #111111; font-size: 14px; text-transform: uppercase; }
                        table { width: 100%; border-collapse: collapse; }
                        thead th {
                            background: #111111 !important;
                            color: #f4b400 !important;
                            border: 1px solid #111111;
                            padding: 8px;
                            text-align: left;
                            font-size: 11px;
                            text-transform: uppercase;
                        }
                        tbody td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; color: #111111; }
                        tbody tr:nth-child(even) td { background: #fff8e1; }
                    </style>
                </head>
                <body>
                    <div class="pdf-wrap">
                        <div class="pdf-header">
                            <div class="pdf-strip"></div>
                            <div class="pdf-head-content">
                                <div class="pdf-brand">
                                    <img src="${logoUrl}" alt="Vilcon">
                                    <h1>VILCON</h1>
                                </div>
                                <div class="pdf-meta">
                                    <strong>${titulo}</strong>
                                    <span>Emitido em: ${dataAtual}</span>
                                </div>
                            </div>
                        </div>
                        <h2>Relatorio</h2>
                        ${tabelaHtml}
                    </div>
                </body>
                </html>
            `;
            janela.document.write(html);
            janela.document.close();
            janela.focus();
            janela.print();
        }

        document.querySelectorAll('.btn-export').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var card = btn.closest('.card');
                if (!card) return;
                var tabela = tabelaVisivel(card);
                if (!tabela) {
                    alert('Nao ha lista visivel para exportar.');
                    return;
                }
                var viewAtual = '<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>';
                var base = 'documental_' + limparTexto(viewAtual || 'lista').toLowerCase().replace(/\s+/g, '_');
                if (btn.getAttribute('data-export-format') === 'excel') {
                    exportarExcel(tabela, base);
                } else {
                    exportarPdf(tabela, base.toUpperCase());
                }
            });
        });
        </script>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
