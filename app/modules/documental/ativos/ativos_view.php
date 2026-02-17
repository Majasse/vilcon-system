<?php
$ativos = [];
$erro_ativos = null;

$ativo_id = isset($_GET['ativo_id']) ? (int)$_GET['ativo_id'] : 0;
$aplicar_filtro = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';
$pesquisa = trim((string)($_GET['q'] ?? ''));
$tipo_filtro = trim((string)($_GET['tipo'] ?? 'todos'));
$mostrar_lista = $aplicar_filtro || $ativo_id > 0;
$ativo_detalhe = null;
$ativo_docs = [];
$ativos_filtrados = [];

function ativoFotoUrl(array $ativo): ?string {
    $id = (int)($ativo['id'] ?? 0);
    $matricula = trim((string)($ativo['matricula'] ?? ''));
    $slugMatricula = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $matricula));
    $slugMatricula = trim($slugMatricula, '-');

    $baseRoot = dirname(__DIR__, 4) . '/public/uploads/';
    $dirs = ['ativos', 'activos', 'frota'];
    $candidatos = [
        (string)$id . '.jpg',
        (string)$id . '.jpeg',
        (string)$id . '.png',
        (string)$id . '.webp',
        $slugMatricula . '.jpg',
        $slugMatricula . '.jpeg',
        $slugMatricula . '.png',
        $slugMatricula . '.webp',
    ];

    foreach ($dirs as $dir) {
        foreach ($candidatos as $file) {
            if ($file === '.jpg' || $file === '.jpeg' || $file === '.png' || $file === '.webp') {
                continue;
            }
            $path = $baseRoot . $dir . '/' . $file;
            if (is_file($path)) {
                return '/vilcon-systemon/public/uploads/' . $dir . '/' . $file;
            }
        }
    }

    return null;
}

function yesNoLabel($valor): string {
    return ((int)$valor === 1) ? 'Sim' : 'Nao';
}

try {
    $sql = "
        SELECT *
        FROM activos
        ORDER BY id ASC
    ";
    $stmt = $pdo->query($sql);
    $ativos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($mostrar_lista) {
        foreach ($ativos as $a) {
            $textoBusca = strtolower(
                (string)($a['matricula'] ?? '') . ' ' .
                (string)($a['quadro'] ?? '') . ' ' .
                (string)($a['marca'] ?? '') . ' ' .
                (string)($a['equipamento'] ?? '')
            );
            $okBusca = $pesquisa === '' || strpos($textoBusca, strtolower($pesquisa)) !== false;
            $okTipo = $tipo_filtro === 'todos' || stripos((string)($a['equipamento'] ?? ''), $tipo_filtro) !== false;
            if ($okBusca && $okTipo) {
                $ativos_filtrados[] = $a;
            }
        }
    }

    if ($ativo_id > 0) {
        $stmtAtivo = $pdo->prepare("SELECT * FROM activos WHERE id = :id LIMIT 1");
        $stmtAtivo->execute(['id' => $ativo_id]);
        $ativo = $stmtAtivo->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($ativo) {
            $stmtDet = $pdo->prepare("SELECT * FROM ativos_detalhes WHERE ativo_id = :id LIMIT 1");
            $stmtDet->execute(['id' => $ativo_id]);
            $det = $stmtDet->fetch(PDO::FETCH_ASSOC) ?: [];

            $stmtDoc = $pdo->prepare("
                SELECT tipo_documento, validade
                FROM activos_documentos
                WHERE activo_id = :id
                ORDER BY id DESC
            ");
            $stmtDoc->execute(['id' => $ativo_id]);
            $ativo_docs = $stmtDoc->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $ativo_detalhe = [
                'base' => $ativo,
                'extra' => $det,
                'foto_url' => ativoFotoUrl($ativo),
            ];
        }
    }
} catch (Throwable $e) {
    $erro_ativos = 'Nao foi possivel carregar os ativos.';
}
?>
<div data-mode-scope>
    <style>
        .ativos-table-wrap {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
        }
        .ativos-table-wrap .list-table {
            min-width: 1900px;
            margin: 0;
        }
        .ativos-table-wrap .list-table th,
        .ativos-table-wrap .list-table td {
            white-space: nowrap;
            padding: 8px 9px;
            font-size: 12px;
        }
        .ativos-table-wrap .list-table th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            z-index: 1;
        }
        .ativo-link {
            color: #0f172a;
            font-weight: 700;
            text-decoration: none;
        }
        .ativo-link:hover { text-decoration: underline; }
        .ativo-detalhe-wrap {
            margin-top: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
            padding: 14px;
        }
        .ativo-detalhe-wrap.minimized .ativo-detalhe-grid {
            display: none;
        }
        .btn-detail {
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-detail.warn {
            border-color: #fb923c;
            color: #9a3412;
            background: #fff7ed;
        }
        .ativo-modal-backdrop {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 280px;
            background: rgba(15, 23, 42, 0.45);
            z-index: 9998;
        }
        .ativo-modal {
            position: fixed;
            top: 18px;
            left: calc(280px + 14px);
            transform: none;
            width: calc(100vw - 280px - 28px);
            max-width: 1200px;
            max-height: calc(100vh - 36px);
            overflow: hidden;
            z-index: 9999;
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.35);
        }
        .ativo-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .title-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .ativo-detalhe-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 14px;
        }
        .ativo-foto {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            min-height: 190px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
        }
        .ativo-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .ativo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 999px;
            background: #111827;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: 800;
        }
        .ativo-info h4 {
            margin: 0 0 6px 0;
            color: #111827;
        }
        .sec-title {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: .3px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 6px 8px;
            margin: 14px 0 6px;
        }
        .ativo-kv {
            display: grid;
            grid-template-columns: repeat(4, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .ativo-kv .item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
        }
        .ativo-kv .item .k {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .ativo-kv .item .v {
            font-size: 12px;
            color: #0f172a;
            font-weight: 700;
            word-break: break-word;
        }
        .compact-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .btn-compact {
            border: 1px solid #d1d5db;
            background: #f8fafc;
            color: #0f172a;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .compact-panel {
            display: none;
        }
        .compact-panel.open {
            display: block;
        }
        @media (max-width: 980px) {
            .ativo-modal-backdrop { left: 0; }
            .ativo-modal {
                left: 10px;
                width: calc(100vw - 20px);
            }
            .ativo-detalhe-grid { grid-template-columns: 1fr; }
            .ativo-kv { grid-template-columns: repeat(2, minmax(120px, 1fr)); }
        }
    </style>
    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-truck"></i> Gestao de Ativos</h3>
            <p>Clique num ativo para ver foto e detalhes completos.</p>
        </div>
    </div>

    <div class="module-entry">
        <button type="button" class="module-entry-btn lista" data-open-module-modal="ativos-modal-lista"><i class="fas fa-list"></i> Lista</button>
        <button type="button" class="module-entry-btn form" data-open-module-modal="ativos-modal-form"><i class="fas fa-plus"></i> Adicionar</button>
    </div>

    <?php if (!$mostrar_lista): ?>
        <div class="filter-container" style="margin-top:8px;">
            <span style="font-size:12px; color:#6b7280;">A lista de ativos so aparece apos aplicar os filtros.</span>
        </div>
    <?php endif; ?>

    <div class="module-modal <?= $mostrar_lista ? 'open' : '' ?>" id="ativos-modal-lista">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Ativos - Lista e Filtros</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="filter-container" method="get" action="">
                    <input type="hidden" name="view" value="ativos">
                    <input type="hidden" name="aplicar" value="1">
                    <div class="form-group" style="flex:1;">
                        <label><i class="fas fa-magnifying-glass"></i> Pesquisar</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Matricula, chassi, marca...">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-filter"></i> Filtrar por tipo</label>
                        <select name="tipo">
                            <option value="todos" <?= $tipo_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="Bulldozer" <?= $tipo_filtro === 'Bulldozer' ? 'selected' : '' ?>>Bulldozer</option>
                            <option value="Escavadora" <?= $tipo_filtro === 'Escavadora' ? 'selected' : '' ?>>Escavadora</option>
                            <option value="Retroescavadora" <?= $tipo_filtro === 'Retroescavadora' ? 'selected' : '' ?>>Retroescavadora</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
                    <a href="?view=ativos" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
                </form>
                <div class="module-tools">
                    <button type="button" class="btn-export" data-export-format="excel"><i class="fas fa-file-excel"></i> Baixar Excel</button>
                    <button type="button" class="btn-export" data-export-format="pdf"><i class="fas fa-file-pdf"></i> Baixar PDF</button>
                </div>

                <div id="ativos-lista" class="panel-view <?= $mostrar_lista ? '' : 'hidden' ?>">
        <?php if ($ativo_detalhe !== null): ?>
            <?php
                $base = $ativo_detalhe['base'];
                $extra = $ativo_detalhe['extra'];
                $foto = $ativo_detalhe['foto_url'];
                $sigla = strtoupper(substr((string)($base['equipamento'] ?? 'AT'), 0, 2));
                $filtrosUrl = '&aplicar=1&q=' . urlencode($pesquisa) . '&tipo=' . urlencode($tipo_filtro);
            ?>
            <a class="ativo-modal-backdrop" href="?view=ativos<?= $filtrosUrl ?>" aria-label="Fechar detalhe do ativo"></a>
            <div class="ativo-detalhe-wrap ativo-modal">
                <div class="ativo-modal-head">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <h3 style="margin:0;"><i class="fa-solid fa-truck-front" style="color:#ea580c;"></i> Detalhes do Ativo #<?= (int)$base['id'] ?></h3>
                        <span class="title-chip"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars((string)($base['estado'] ?? '-')) ?></span>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <button type="button" class="btn-detail warn" id="btn-minimizar-ativo">
                            <i class="fas fa-window-minimize"></i> Minimizar
                        </button>
                        <a href="?view=ativos<?= $filtrosUrl ?>" class="btn-detail">
                            <i class="fas fa-times"></i> Fechar tela
                        </a>
                    </div>
                </div>
                <div class="ativo-detalhe-grid">
                    <div class="ativo-foto">
                        <?php if ($foto !== null): ?>
                            <img src="<?= htmlspecialchars((string)$foto) ?>" alt="Foto do ativo">
                        <?php else: ?>
                            <div class="ativo-placeholder"><?= htmlspecialchars($sigla) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="ativo-info">
                        <h4><?= htmlspecialchars((string)($base['equipamento'] ?? '-')) ?> - <?= htmlspecialchars((string)($base['matricula'] ?? '-')) ?></h4>
                        <div class="ativo-kv">
                            <div class="item"><div class="k">Marca</div><div class="v"><?= htmlspecialchars((string)($base['marca'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">Modelo</div><div class="v"><?= htmlspecialchars((string)($base['modelo'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">Estado</div><div class="v"><?= htmlspecialchars((string)($base['estado'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">Propriedade</div><div class="v"><?= htmlspecialchars((string)($base['propriedade'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">Motor</div><div class="v"><?= htmlspecialchars((string)($base['motor'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">Quadro</div><div class="v"><?= htmlspecialchars((string)($base['quadro'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">LIVRETE</div><div class="v"><?= htmlspecialchars((string)($base['livrete'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">SEGUROS</div><div class="v"><?= htmlspecialchars((string)($base['seguros'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">INSPECCAO</div><div class="v"><?= htmlspecialchars((string)($base['inspeccao'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">MANIFESTO</div><div class="v"><?= htmlspecialchars((string)($base['manifesto'] ?? '-')) ?></div></div>
                            <div class="item"><div class="k">Radio</div><div class="v"><?= htmlspecialchars(yesNoLabel($base['radio'] ?? 0)) ?></div></div>
                            <div class="item"><div class="k">SPEAL KIT</div><div class="v"><?= htmlspecialchars(yesNoLabel($base['speal_kit'] ?? 0)) ?></div></div>
                            <div class="item"><div class="k">Extintor</div><div class="v"><?= htmlspecialchars(yesNoLabel($base['extintor'] ?? 0)) ?></div></div>
                            <div class="item"><div class="k">Reflectores</div><div class="v"><?= htmlspecialchars(yesNoLabel($base['reflectores'] ?? 0)) ?></div></div>
                            <div class="item"><div class="k">Macaco</div><div class="v"><?= htmlspecialchars(yesNoLabel($base['macaco'] ?? 0)) ?></div></div>
                            <div class="item"><div class="k">Chave de Roda</div><div class="v"><?= htmlspecialchars(yesNoLabel($base['chave_roda'] ?? 0)) ?></div></div>
                        </div>

                        <div class="compact-actions">
                            <button type="button" class="btn-compact" data-toggle-panel="panel-extra">
                                <i class="fa-solid fa-screwdriver-wrench"></i> Detalhes adicionais
                            </button>
                            <button type="button" class="btn-compact" data-toggle-panel="panel-docs">
                                <i class="fa-solid fa-folder-open"></i> Documentos
                            </button>
                        </div>
                        <div id="panel-extra" class="compact-panel">
                            <div class="sec-title"><i class="fa-solid fa-screwdriver-wrench"></i> Detalhes Adicionais</div>
                            <div class="ativo-kv">
                                <div class="item"><div class="k">Chassi</div><div class="v"><?= htmlspecialchars((string)($extra['chassi'] ?? '-')) ?></div></div>
                                <div class="item"><div class="k">Ano Fabrico</div><div class="v"><?= htmlspecialchars((string)($extra['ano_fabrico'] ?? '-')) ?></div></div>
                                <div class="item"><div class="k">Valor Compra</div><div class="v"><?= htmlspecialchars((string)($extra['valor_compra'] ?? '-')) ?></div></div>
                                <div class="item"><div class="k">Data Aquisicao</div><div class="v"><?= htmlspecialchars((string)($extra['data_aquisicao'] ?? '-')) ?></div></div>
                                <div class="item"><div class="k">Proxima Inspecao</div><div class="v"><?= htmlspecialchars((string)($extra['proxima_inspecao'] ?? '-')) ?></div></div>
                                <div class="item"><div class="k">Venc. Seguro</div><div class="v"><?= htmlspecialchars((string)($extra['vencimento_seguro'] ?? '-')) ?></div></div>
                                <div class="item"><div class="k">Venc. Manifesto</div><div class="v"><?= htmlspecialchars((string)($extra['manifesto_vencimento'] ?? '-')) ?></div></div>
                            </div>
                        </div>
                        <div id="panel-docs" class="compact-panel">
                            <div class="sec-title"><i class="fa-solid fa-folder-open"></i> Documentos</div>
                            <table class="list-table" style="min-width: 420px;">
                                <thead>
                                    <tr>
                                        <th>Tipo Documento</th>
                                        <th>Validade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($ativo_docs) === 0): ?>
                                        <tr><td colspan="2">Sem documentos associados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($ativo_docs as $doc): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)($doc['tipo_documento'] ?? '-')) ?></td>
                                                <td><?= htmlspecialchars((string)($doc['validade'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                (function() {
                    var btn = document.getElementById('btn-minimizar-ativo');
                    if (!btn) return;
                    btn.addEventListener('click', function() {
                        var wrap = btn.closest('.ativo-detalhe-wrap');
                        if (!wrap) return;
                        wrap.classList.toggle('minimized');
                        var minimizado = wrap.classList.contains('minimized');
                        btn.innerHTML = minimizado
                            ? '<i class="fas fa-window-maximize"></i> Restaurar'
                            : '<i class="fas fa-window-minimize"></i> Minimizar';
                    });
                    document.querySelectorAll('[data-toggle-panel]').forEach(function(toggleBtn) {
                        toggleBtn.addEventListener('click', function() {
                            var id = toggleBtn.getAttribute('data-toggle-panel');
                            var panel = document.getElementById(id);
                            if (!panel) return;
                            panel.classList.toggle('open');
                        });
                    });
                })();
            </script>
        <?php endif; ?>

        <div class="ativos-table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>id</th>
                        <th>equipamento</th>
                        <th>matricula</th>
                        <th>marca</th>
                        <th>propriedade</th>
                        <th>modelo</th>
                        <th>motor</th>
                        <th>quadro</th>
                        <th>livrete</th>
                        <th>seguros</th>
                        <th>inspeccao</th>
                        <th>manifesto</th>
                        <th>radio</th>
                        <th>speal_kit</th>
                        <th>extintor</th>
                        <th>reflectores</th>
                        <th>macaco</th>
                        <th>chave_roda</th>
                        <th>estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($erro_ativos !== null): ?>
                        <tr>
                            <td colspan="19"><?= htmlspecialchars($erro_ativos) ?></td>
                        </tr>
                    <?php elseif (empty($ativos_filtrados)): ?>
                        <tr>
                            <td colspan="19">Sem registos para os filtros aplicados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ativos_filtrados as $ativo): ?>
                            <?php $idA = (int)($ativo['id'] ?? 0); ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($ativo['id'] ?? '-')) ?></td>
                                <td>
                                    <a class="ativo-link" href="?view=ativos&ativo_id=<?= $idA ?>&aplicar=1&q=<?= urlencode($pesquisa) ?>&tipo=<?= urlencode($tipo_filtro) ?>">
                                        <?= htmlspecialchars((string)($ativo['equipamento'] ?? '-')) ?>
                                    </a>
                                </td>
                                <td>
                                    <a class="ativo-link" href="?view=ativos&ativo_id=<?= $idA ?>&aplicar=1&q=<?= urlencode($pesquisa) ?>&tipo=<?= urlencode($tipo_filtro) ?>">
                                        <?= htmlspecialchars((string)($ativo['matricula'] ?? '-')) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars((string)($ativo['marca'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['propriedade'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['modelo'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['motor'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['quadro'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['livrete'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['seguros'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['inspeccao'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['manifesto'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars(yesNoLabel($ativo['radio'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars(yesNoLabel($ativo['speal_kit'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars(yesNoLabel($ativo['extintor'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars(yesNoLabel($ativo['reflectores'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars(yesNoLabel($ativo['macaco'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars(yesNoLabel($ativo['chave_roda'] ?? 0)) ?></td>
                                <td><?= htmlspecialchars((string)($ativo['estado'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
                </div>
            </div>
        </div>
    </div>

    <div class="module-modal" id="ativos-modal-form">
        <div class="module-modal-window">
            <div class="module-modal-header">
                <h4>Ativos - Adicionar</h4>
                <div class="module-modal-actions">
                    <button type="button" class="module-modal-btn" data-minimizar-modal>Minimizar</button>
                    <button type="button" class="module-modal-btn" data-fechar-modal>Fechar</button>
                </div>
            </div>
            <div class="module-modal-body">
                <form class="form-grid" enctype="multipart/form-data">
            <div class="section-title">Informacao Base e Alocacao</div>
            <div class="form-group"><label>Matricula / Codigo Interno</label><input type="text" placeholder="Ex: ABC-123-MC"></div>

            <div class="form-group">
                <label>Projeto / Localizacao Atual</label>
                <select>
                    <option>TRABALHO INTERNO (SEDE/OFICINA)</option>
                    <option>PROJETO X (EXEMPLO)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Atividade Atual</label>
                <input type="text" placeholder="Ex: Escavacao, Carga, Em manutencao">
            </div>

            <div class="form-group"><label>No de Chassi</label><input type="text" placeholder="Ex: 9BWZZZ... "></div>
            <div class="form-group">
                <label>Tipo de Ativo</label>
                <select onchange="checkNovo(this, 'novo_tipo')">
                    <option>Bulldozer</option><option>Escavadora</option><option>Retroescavadora</option>
                    <option value="novo">-- Adicionar Novo --</option>
                </select>
                <input type="text" id="novo_tipo" class="input-novo" placeholder="Escreva o tipo aqui">
            </div>
            <div class="form-group">
                <label>Marca</label>
                <select onchange="checkNovo(this, 'nova_marca')">
                    <option>Caterpillar</option><option>Volvo</option><option>Komatsu</option>
                    <option value="novo">-- Adicionar Nova --</option>
                </select>
                <input type="text" id="nova_marca" class="input-novo" placeholder="Escreva a marca aqui">
            </div>

            <div class="section-title">Documentos e Validades Individuais</div>
            <div class="form-group"><label>Vencimento Seguro</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Inspecao Periodica</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Taxas de Radio</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div class="form-group"><label>Manifestos</label><div class="doc-control"><input type="date"><label class="btn-upload"><i class="fas fa-paperclip"></i> Anexo <input type="file" style="display:none"></label></div></div>
            <div style="grid-column: span 3;"><button class="btn-save">Salvar Registro</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
