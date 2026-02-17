<?php
$pessoal_lista = [];
$erro_pessoal = null;

$perfil_filtro = trim((string)($_GET['perfil'] ?? ''));
$pesquisa = trim((string)($_GET['q'] ?? ''));
$cargo_filtro = trim((string)($_GET['cargo'] ?? 'todos'));
$aplicar_filtro = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';

$cargos_por_perfil = [
    'oficina' => [
        'Electricista Auto',
        'Pintor Auto',
        'Mecanico',
        'Ajudante Mecanico',
        'Gestor da Oficina',
    ],
    'transporte' => [
        'Motorista',
        'Operador de Maquinas/Motorista',
        'Operador de Maquinas',
        'Ajudante Camiao',
        'Motorista Mini Bus',
        'Gestor de Transporte',
        'Riger',
    ],
];

if (!isset($cargos_por_perfil[$perfil_filtro])) {
    $perfil_filtro = '';
}

if ($perfil_filtro !== '' && $cargo_filtro !== 'todos' && !in_array($cargo_filtro, $cargos_por_perfil[$perfil_filtro], true)) {
    $cargo_filtro = 'todos';
}

if ($aplicar_filtro && $perfil_filtro !== '') {
    try {
        $where = [];
        $params = [];

        if ($pesquisa !== '') {
            $where[] = "(p.nome LIKE :pesquisa OR CAST(p.numero AS CHAR) LIKE :pesquisa OR c.nome LIKE :pesquisa OR pd.tipo_documento LIKE :pesquisa)";
            $params[':pesquisa'] = '%' . $pesquisa . '%';
        }

        $cargos_permitidos = $cargos_por_perfil[$perfil_filtro];
        if (!empty($cargos_permitidos)) {
            $placeholders = [];
            foreach ($cargos_permitidos as $idx => $cargo_nome) {
                $ph = ':cargo_perfil_' . $idx;
                $placeholders[] = $ph;
                $params[$ph] = $cargo_nome;
            }
            $where[] = 'c.nome IN (' . implode(', ', $placeholders) . ')';
        }

        if ($cargo_filtro !== '' && $cargo_filtro !== 'todos') {
            $where[] = 'c.nome = :cargo_especifico';
            $params[':cargo_especifico'] = $cargo_filtro;
        }

        $sql = "
            SELECT
                p.id AS pessoal_id,
                p.numero,
                p.nome,
                c.nome AS cargo_nome,
                p.estado,
                pd.tipo_documento,
                pd.data_emissao,
                pd.data_vencimento,
                pd.created_at AS documento_created_at
            FROM pessoal p
            LEFT JOIN cargos c
                ON c.id = p.cargo_id
            LEFT JOIN pessoal_documentos pd
                ON pd.pessoal_id = p.id
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.nome ASC, pd.id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pessoal_lista = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $erro_pessoal = 'Nao foi possivel carregar os funcionarios.';
    }
}

function tituloPerfilPessoal(string $perfil): string {
    if ($perfil === 'oficina') {
        return 'Oficina';
    }
    if ($perfil === 'transporte') {
        return 'Transporte';
    }
    return 'Perfil';
}
?>
<div data-mode-scope>
    <style>
        .pessoal-entry {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .btn-entry {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            min-height: 36px;
        }
        .btn-entry[data-open-pessoal-modal="oficina"] {
            background: #dbeafe;
            color: #1e3a8a;
            border-color: #93c5fd;
        }
        .btn-entry[data-open-pessoal-modal="transporte"] {
            background: #ffedd5;
            color: #9a3412;
            border-color: #fdba74;
        }
        .pessoal-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1100;
            padding: 22px;
            overflow: auto;
        }
        .pessoal-modal.open {
            display: block;
        }
        .pessoal-modal-window {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }
        .pessoal-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .pessoal-modal.perfil-oficina .pessoal-modal-header {
            background: #1d4ed8;
            border-bottom-color: #1e40af;
        }
        .pessoal-modal.perfil-transporte .pessoal-modal-header {
            background: #ea580c;
            border-bottom-color: #c2410c;
        }
        .pessoal-modal-header h4 {
            margin: 0;
            font-size: 14px;
            color: #111827;
        }
        .pessoal-modal.perfil-oficina .pessoal-modal-header h4,
        .pessoal-modal.perfil-transporte .pessoal-modal-header h4 {
            color: #ffffff;
        }
        .pessoal-modal-actions {
            display: flex;
            gap: 8px;
        }
        .pessoal-modal-btn {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .pessoal-modal-btn[data-minimizar-modal] {
            background: #fef3c7;
            border-color: #fbbf24;
            color: #92400e;
        }
        .pessoal-modal-btn[data-fechar-modal] {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
        .pessoal-modal-body {
            padding: 14px;
        }
        .pessoal-modal.minimized .pessoal-modal-body {
            display: none;
        }
        .pessoal-tools {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .pessoal-tools .btn-export[data-export-format="excel"] {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }
        .pessoal-tools .btn-export[data-export-format="pdf"] {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        @media (max-width: 900px) {
            .pessoal-modal {
                padding: 8px;
            }
        }
    </style>

    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-users"></i> Pessoal</h3>
            <p>Selecione Oficina ou Transporte para abrir a tela de filtragem e lista documental.</p>
        </div>
    </div>

    <div class="pessoal-entry">
        <button type="button" class="btn-entry" data-open-pessoal-modal="oficina"><i class="fas fa-screwdriver-wrench"></i> Oficina</button>
        <button type="button" class="btn-entry" data-open-pessoal-modal="transporte"><i class="fas fa-truck"></i> Transporte</button>
    </div>

    <?php if (!$aplicar_filtro): ?>
        <div class="filter-container" style="margin-top:8px;">
            <span style="font-size:12px; color:#6b7280;">A lista de funcionarios so aparece apos aplicar os filtros dentro de Oficina ou Transporte.</span>
        </div>
    <?php endif; ?>

    <?php foreach ($cargos_por_perfil as $perfil => $listaCargos): ?>
        <?php
            $modalAberto = ($perfil_filtro === $perfil && $aplicar_filtro);
            $cargoAtual = ($perfil_filtro === $perfil) ? $cargo_filtro : 'todos';
            $pesquisaAtual = ($perfil_filtro === $perfil) ? $pesquisa : '';
            $mostrarLista = ($perfil_filtro === $perfil && $aplicar_filtro);
        ?>
        <div class="pessoal-modal perfil-<?= htmlspecialchars($perfil) ?> <?= $modalAberto ? 'open' : '' ?>" id="pessoal-modal-<?= htmlspecialchars($perfil) ?>">
            <div class="pessoal-modal-window">
                <div class="pessoal-modal-header">
                    <h4>Documental Pessoal - <?= htmlspecialchars(tituloPerfilPessoal($perfil)) ?></h4>
                    <div class="pessoal-modal-actions">
                        <button type="button" class="pessoal-modal-btn" data-minimizar-modal>Minimizar</button>
                        <button type="button" class="pessoal-modal-btn" data-fechar-modal>Fechar</button>
                    </div>
                </div>

                <div class="pessoal-modal-body">
                    <form class="filter-container" method="get" action="">
                        <input type="hidden" name="view" value="pessoal">
                        <input type="hidden" name="perfil" value="<?= htmlspecialchars($perfil) ?>">
                        <input type="hidden" name="aplicar" value="1">

                        <div class="form-group" style="flex:1;">
                            <label><i class="fas fa-magnifying-glass"></i> Pesquisa</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($pesquisaAtual) ?>" placeholder="Nome, numero, cargo, tipo documento...">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Cargo</label>
                            <select name="cargo">
                                <option value="todos">Todos os cargos</option>
                                <?php foreach ($listaCargos as $cargoNome): ?>
                                    <option value="<?= htmlspecialchars($cargoNome) ?>" <?= $cargoAtual === $cargoNome ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cargoNome) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
                        <a href="?view=pessoal&perfil=<?= urlencode($perfil) ?>" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
                    </form>

                    <div class="pessoal-tools">
                        <button type="button" class="btn-export" data-export-format="excel">
                            <i class="fas fa-file-excel"></i> Baixar Excel
                        </button>
                        <button type="button" class="btn-export" data-export-format="pdf">
                            <i class="fas fa-file-pdf"></i> Baixar PDF
                        </button>
                    </div>

                    <div class="panel-view <?= $mostrarLista ? '' : 'hidden' ?>">
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>Numero</th>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Tipo Documento</th>
                                    <th>Data Emissao</th>
                                    <th>Data Vencimento</th>
                                    <th>Criado Em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($mostrarLista && $erro_pessoal !== null): ?>
                                    <tr>
                                        <td colspan="7"><?= htmlspecialchars($erro_pessoal) ?></td>
                                    </tr>
                                <?php elseif ($mostrarLista && empty($pessoal_lista)): ?>
                                    <tr>
                                        <td colspan="7">Sem registos para os filtros aplicados.</td>
                                    </tr>
                                <?php elseif ($mostrarLista): ?>
                                    <?php foreach ($pessoal_lista as $item): ?>
                                        <?php
                                        $emissao = trim((string)($item['data_emissao'] ?? ''));
                                        $vencimento = trim((string)($item['data_vencimento'] ?? ''));
                                        $criadoEm = trim((string)($item['documento_created_at'] ?? ''));
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)($item['numero'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['nome'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['cargo_nome'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['tipo_documento'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars($emissao !== '' ? $emissao : '-') ?></td>
                                            <td><?= htmlspecialchars($vencimento !== '' ? $vencimento : '-') ?></td>
                                            <td><?= htmlspecialchars($criadoEm !== '' ? $criadoEm : '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">Aplique os filtros para ver a lista de funcionarios.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function() {
    function abrirModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('open');
    }

    function fecharModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.classList.remove('minimized');
    }

    document.querySelectorAll('[data-open-pessoal-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var perfil = btn.getAttribute('data-open-pessoal-modal');
            abrirModal('pessoal-modal-' + perfil);
        });
    });

    document.querySelectorAll('[data-fechar-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            fecharModal(btn.closest('.pessoal-modal'));
        });
    });

    document.querySelectorAll('[data-minimizar-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = btn.closest('.pessoal-modal');
            if (!modal) return;
            modal.classList.toggle('minimized');
            btn.textContent = modal.classList.contains('minimized') ? 'Restaurar' : 'Minimizar';
        });
    });

    document.querySelectorAll('.pessoal-modal').forEach(function(modal) {
        modal.addEventListener('click', function(ev) {
            if (ev.target === modal) {
                fecharModal(modal);
            }
        });
    });
})();
</script>
