<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /vilcon-systemon/public/login.php");
    exit;
}

/* =========================
   CONTROLES DO MÃ“DULO OFICINA
========================= */
$tab = $_GET['tab'] ?? 'oficina';
$view = $_GET['view'] ?? 'ordens_servico';
$mode = $_GET['mode'] ?? 'list';

$proximo_os = "OS-OF-" . date('Y') . "-001";

$pedidos_reparacao = [];
$erro_pedidos = null;
$msg_pedidos = null;

function normalizarStatusPedido($valor) {
    $v = strtolower(trim((string)$valor));
    if ($v === '' || $v === 'pendente' || $v === 'aberto') return 'pendente';
    if ($v === 'aceito' || $v === 'aceite') return 'aceito';
    if ($v === 'em andamento' || $v === 'em progresso' || $v === 'andamento') return 'em_andamento';
    if ($v === 'resolvido' || $v === 'fechado' || $v === 'concluido') return 'resolvido';
    if ($v === 'aguardando logistica externa' || $v === 'encaminhado logistica' || $v === 'externo') return 'logistica_externa';
    return 'pendente';
}

function statusPedidoLabel($statusNormalizado) {
    if ($statusNormalizado === 'aceito') return 'Aceito';
    if ($statusNormalizado === 'em_andamento') return 'Em andamento';
    if ($statusNormalizado === 'resolvido') return 'Resolvido';
    if ($statusNormalizado === 'logistica_externa') return 'Aguardando Logistica Externa';
    return 'Pendente';
}

function encontrarPrimeiraColuna(array $colunas, array $candidatas) {
    foreach ($candidatas as $nome) {
        if (isset($colunas[$nome])) {
            return $nome;
        }
    }
    return null;
}
if ($view === 'pedidos_reparacao' && $mode === 'list') {
    $colunas_pedidos = [];
    try {
        $stmtCols = $pdo->query("SHOW COLUMNS FROM oficina_pedidos_reparacao");
        foreach ($stmtCols->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (isset($col['Field'])) {
                $colunas_pedidos[(string)$col['Field']] = true;
            }
        }
    } catch (PDOException $e) {
        // Segue o fluxo mesmo se nao conseguir ler colunas.
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['id'])) {
        $acao = $_POST['acao'];
        $id = (int)$_POST['id'];
        try {
            if ($acao === 'aceitar') {
                $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Aceito' WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $msg_pedidos = "Pedido #{$id} aceito.";
            } elseif ($acao === 'resolver') {
                $stmt = $pdo->prepare("UPDATE oficina_pedidos_reparacao SET status = 'Resolvido' WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $msg_pedidos = "Pedido #{$id} marcado como resolvido.";
            }
        } catch (PDOException $e) {
            $erro_pedidos = "NÃ£o foi possÃ­vel atualizar o pedido.";
        }
    }

    try {
        $stmt = $pdo->query("SELECT * FROM oficina_pedidos_reparacao ORDER BY id DESC");
        $pedidos_reparacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erro_pedidos = "NÃ£o foi possÃ­vel carregar pedidos de reparaÃ§Ã£o.";
    }
}
function campo($row, $keys, $default = 'â€”') {
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return $default;
}

function badgeClassePrioridade($valor) {
    $v = strtolower(trim((string)$valor));
    if ($v === 'urgente') return 'warn';
    if ($v === 'alta') return 'warn';
    if ($v === 'normal') return 'ok';
    return 'info';
}

function badgeClasseStatus($valor) {
    $v = strtolower(trim((string)$valor));
    if ($v === 'resolvido' || $v === 'fechado') return 'ok';
    if ($v === 'aceito') return 'info';
    if ($v === 'em andamento' || $v === 'em progresso') return 'info';
    if ($v === 'pendente' || $v === 'aberto') return 'warn';
    return 'info';
}
?>
<?php include 'includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/tabs.php'; ?>

    <div class="container">
        <style>
            .export-tools { display:flex; gap:8px; }
            .btn-export {
                border:1px solid #d1d5db;
                background:#ffffff;
                color:#111827;
                padding:6px 10px;
                border-radius:20px;
                font-size:11px;
                font-weight:700;
                cursor:pointer;
            }
            .btn-export i { margin-right:6px; }
        </style>
        <div class="white-card">
            <div class="inner-nav">
                <div class="mode-selector">
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=list" class="btn-mode <?= $mode == 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i> Ver Lista</a>
                    <a href="?tab=<?= $tab ?>&view=<?= $view ?>&mode=form" class="btn-mode <?= $mode == 'form' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Adicionar Novo</a>
                </div>
                <?php if ($mode == 'list'): ?>
                <div class="list-tools">
                    <div class="search-group">
                        <i class="fas fa-search"></i>
                        <input class="search-input" type="text" placeholder="Pesquisar...">
                    </div>
                    <select class="filter-select">
                        <?php if ($view == 'pedidos_reparacao'): ?>
                            <option value="">Filtrar por status</option>
                            <option>Pendente</option>
                            <option>Em andamento</option>
                            <option>Resolvido</option>
                        <?php else: ?>
                            <option value="">Filtrar por status</option>
                            <option>Aberto</option>
                            <option>Em andamento</option>
                            <option>Fechado</option>
                        <?php endif; ?>
                    </select>
                    <div class="export-tools">
                        <button type="button" class="btn-export" data-export-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn-export" data-export-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($mode == 'form'): ?>
                <?php if ($view == 'ordens_servico'): ?>
                    <h3>Nova Ordem de ServiÃ§o</h3>
                    <p style="font-size:11px;color:var(--info);">OS NÂº <?= $proximo_os ?></p>

                    <form class="form-grid" method="POST" action="salvar_os_oficina.php">
                        <div class="section-title">Equipamento</div>

                        <div class="form-group">
                            <label>MatrÃ­cula</label>
                            <input type="text" name="matricula">
                        </div>

                        <div class="form-group">
                            <label>Equipamento</label>
                            <input type="text" name="equipamento">
                        </div>

                        <div class="form-group">
                            <label>Operador</label>
                            <input type="text" name="operador">
                        </div>

                        <div class="form-group">
                            <label>Data Entrada</label>
                            <input type="datetime-local" name="data_entrada" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="section-title">DescriÃ§Ã£o</div>

                        <div class="form-group" style="grid-column:span 4;">
                            <textarea name="descricao" rows="4"></textarea>
                        </div>

                        <div style="grid-column:span 4;">
                            <button class="btn-save" style="background:var(--vilcon-black);width:100%;">Enviar OS</button>
                        </div>
                    </form>
                <?php elseif ($view == 'pedidos_reparacao'): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <div>
                            <h3>Pedido de ReparaÃ§Ã£o</h3>
                            <p style="font-size:12px; color:#6b7280; margin-top:4px;">Registe o pedido com prioridade, local e sintomas para acelerar o atendimento.</p>
                        </div>
                        <div class="pill warn">Nova solicitaÃ§Ã£o</div>
                    </div>

                    <form class="form-grid" method="POST" action="salvar_pedido_reparacao.php">
                        <div class="section-title">Solicitante</div>

                        <div class="form-group">
                            <label>Nome do Solicitante</label>
                            <input type="text" name="solicitante" placeholder="Ex: JoÃ£o Mendes">
                        </div>

                        <div class="form-group">
                            <label>Departamento</label>
                            <input type="text" name="departamento" placeholder="Ex: Frota / LogÃ­stica">
                        </div>

                        <div class="form-group">
                            <label>Data Pedido</label>
                            <input type="date" name="data_pedido" value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="prioridade">
                                <option>Normal</option>
                                <option>Alta</option>
                                <option>Urgente</option>
                            </select>
                        </div>

                        <div class="section-title">Equipamento</div>

                        <div class="form-group">
                            <label>MatrÃ­cula / TAG</label>
                            <input type="text" name="matricula" placeholder="Ex: AHH-532-MP">
                        </div>

                        <div class="form-group">
                            <label>Equipamento</label>
                            <input type="text" name="equipamento" placeholder="Ex: Toyota Coaster">
                        </div>

                        <div class="form-group">
                            <label>LocalizaÃ§Ã£o</label>
                            <input type="text" name="localizacao" placeholder="Ex: Estaleiro Vilankulos">
                        </div>

                        <div class="form-group">
                            <label>Contacto</label>
                            <input type="text" name="contacto" placeholder="Ex: +258 84 000 0000">
                        </div>

                        <div class="section-title">DescriÃ§Ã£o do Problema</div>

                        <div class="form-group" style="grid-column:span 2;">
                            <label>Tipo de Falha</label>
                            <select name="tipo_falha">
                                <option>MecÃ¢nica</option>
                                <option>ElÃ©trica</option>
                                <option>HidrÃ¡ulica</option>
                                <option>Outra</option>
                            </select>
                        </div>

                        <div class="form-group" style="grid-column:span 2;">
                            <label>Sintoma Principal</label>
                            <input type="text" name="sintoma" placeholder="Ex: ruÃ­do, vazamento, perda de forÃ§a">
                        </div>

                        <div class="form-group" style="grid-column:span 4;">
                            <label>DescriÃ§Ã£o Detalhada</label>
                            <textarea name="descricao_problema" rows="4" placeholder="Descreva o problema e as condiÃ§Ãµes em que ocorreu..."></textarea>
                        </div>

                        <div class="form-group" style="grid-column:span 4;">
                            <label>Anexos (fotos/documentos)</label>
                            <input type="file" name="anexos[]" multiple>
                        </div>

                        <div style="grid-column:span 4; display:flex; gap:10px;">
                            <button class="btn-save" style="background:var(--danger); flex:1;">Enviar Pedido</button>
                            <button type="reset" class="btn-save" style="background:#9ca3af; width:180px;">Limpar</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($view == 'pedidos_reparacao'): ?>
                    <h3>Lista de Pedidos de ReparaÃ§Ã£o</h3>
                    <?php if ($erro_pedidos): ?>
                        <p style="color:#b91c1c; font-size:12px;"><?= htmlspecialchars($erro_pedidos) ?></p>
                    <?php endif; ?>
                    <?php if ($msg_pedidos): ?>
                        <p style="color:#16a34a; font-size:12px;"><?= htmlspecialchars($msg_pedidos) ?></p>
                    <?php endif; ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Solicitante</th>
                                <th>MatrÃ­cula</th>
                                <th>Equipamento</th>
                                <th>LocalizaÃ§Ã£o</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>AÃ§Ãµes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pedidos_reparacao) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pedidos_reparacao as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)campo($p, ['id', 'pedido_id'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['solicitante', 'solicitante_nome', 'nome_solicitante'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['ativo_matricula', 'matricula', 'matricula_tag'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['tipo_equipamento', 'equipamento', 'nome_equipamento'])) ?></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['localizacao'])) ?></td>
                                        <?php
                                            $prioridade = campo($p, ['prioridade']);
                                            $status = campo($p, ['status']);
                                        ?>
                                        <td><span class="pill <?= badgeClassePrioridade($prioridade) ?>"><?= htmlspecialchars((string)$prioridade) ?></span></td>
                                        <td><span class="pill <?= badgeClasseStatus($status) ?>"><?= htmlspecialchars((string)$status) ?></span></td>
                                        <td><?= htmlspecialchars((string)campo($p, ['data_pedido', 'created_at', 'data_registo'])) ?></td>
                                        <td>
                                            <form method="POST" style="display:flex; gap:6px;">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars((string)campo($p, ['id', 'pedido_id'])) ?>">
                                                <?php if (strtolower((string)$status) === 'pendente'): ?>
                                                    <button type="submit" name="acao" value="aceitar" class="btn-save" style="background:#2563eb; padding:6px 10px;">Aceitar</button>
                                                <?php endif; ?>
                                                <?php if (strtolower((string)$status) !== 'resolvido'): ?>
                                                    <button type="submit" name="acao" value="resolver" class="btn-save" style="background:#16a34a; padding:6px 10px;">Resolvido</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <h3>Lista de Registos</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th><th>Equipamento</th><th>Data</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para mostrar.</td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function tabelaVisivelOficina(root) {
    var tabelas = root.querySelectorAll('table');
    for (var i = 0; i < tabelas.length; i++) {
        var t = tabelas[i];
        if (t.offsetParent !== null) return t;
    }
    return null;
}

function normalizarTextoOficina(valor) {
    return String(valor || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function indiceColunaStatusOficina(tabela) {
    var colunas = tabela.querySelectorAll('thead th');
    for (var i = 0; i < colunas.length; i++) {
        if (normalizarTextoOficina(colunas[i].textContent) === 'status') {
            return i;
        }
    }
    return -1;
}

function aplicarFiltroOficina(card) {
    var tabela = tabelaVisivelOficina(card);
    if (!tabela) return;

    var campoBusca = card.querySelector('.search-input');
    var filtroStatus = card.querySelector('.filter-select');
    var corpo = tabela.querySelector('tbody');
    if (!corpo) return;

    var termo = normalizarTextoOficina(campoBusca ? campoBusca.value : '');
    var statusSelecionado = normalizarTextoOficina(filtroStatus ? filtroStatus.value : '');
    var indiceStatus = indiceColunaStatusOficina(tabela);
    var linhas = Array.prototype.slice.call(corpo.querySelectorAll('tr'));
    var linhasDados = linhas.filter(function(linha) {
        var celulas = linha.querySelectorAll('td');
        return celulas.length > 0 && !linha.classList.contains('js-filter-empty') &&
            normalizarTextoOficina(linha.textContent).indexOf('sem registos para mostrar') === -1;
    });

    if (linhasDados.length === 0) return;

    var visiveis = 0;
    linhasDados.forEach(function(linha) {
        var textoLinha = normalizarTextoOficina(linha.textContent);
        var celulas = linha.querySelectorAll('td');
        var statusLinha = '';

        if (indiceStatus >= 0 && celulas[indiceStatus]) {
            statusLinha = normalizarTextoOficina(celulas[indiceStatus].textContent);
        }

        var okBusca = !termo || textoLinha.indexOf(termo) !== -1;
        var okStatus = !statusSelecionado || statusLinha.indexOf(statusSelecionado) !== -1;
        var mostrar = okBusca && okStatus;

        linha.style.display = mostrar ? '' : 'none';
        if (mostrar) visiveis++;
    });

    var linhaSemResultado = corpo.querySelector('tr.js-filter-empty');
    if (!linhaSemResultado && visiveis === 0) {
        linhaSemResultado = document.createElement('tr');
        linhaSemResultado.className = 'js-filter-empty';
        var td = document.createElement('td');
        var totalColunas = tabela.querySelectorAll('thead th').length || 1;
        td.colSpan = totalColunas;
        td.style.textAlign = 'center';
        td.style.color = '#6b7280';
        td.style.padding = '12px';
        td.textContent = 'Nenhum registo corresponde ao filtro.';
        linhaSemResultado.appendChild(td);
        corpo.appendChild(linhaSemResultado);
    }

    if (linhaSemResultado && visiveis > 0) {
        linhaSemResultado.remove();
    }
}

function inicializarFiltrosOficina() {
    document.querySelectorAll('.white-card').forEach(function(card) {
        var campoBusca = card.querySelector('.search-input');
        var filtroStatus = card.querySelector('.filter-select');

        if (campoBusca) {
            campoBusca.addEventListener('input', function() {
                aplicarFiltroOficina(card);
            });
        }

        if (filtroStatus) {
            filtroStatus.addEventListener('change', function() {
                aplicarFiltroOficina(card);
            });
        }
    });
}

function nomeArquivoOficina(base, ext) {
    var data = new Date();
    var y = data.getFullYear();
    var m = String(data.getMonth() + 1).padStart(2, '0');
    var d = String(data.getDate()).padStart(2, '0');
    return base + '_' + y + m + d + '.' + ext;
}

function exportarExcelOficina(tabela, base) {
    var html = '<html><head><meta charset="UTF-8"></head><body>' + tabela.outerHTML + '</body></html>';
    var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = nomeArquivoOficina(base, 'xls');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportarPdfOficina(tabela, titulo) {
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
        var card = btn.closest('.white-card');
        if (!card) return;
        var tabela = tabelaVisivelOficina(card);
        if (!tabela) {
            alert('Nao ha lista visivel para exportar.');
            return;
        }
        var viewAtual = '<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>';
        var base = 'oficina_' + (viewAtual || 'lista').toLowerCase().replace(/\s+/g, '_');
        if (btn.getAttribute('data-export-format') === 'excel') {
            exportarExcelOficina(tabela, base);
        } else {
            exportarPdfOficina(tabela, base.toUpperCase());
        }
    });
});

inicializarFiltrosOficina();
</script>

<?php include 'includes/footer.php'; ?>
