<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Ordens de Serviço | Oficina';
$ordens = [];
$erro = null;

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS oficina_ordens_servico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_os VARCHAR(40) UNIQUE,
            origem_tipo VARCHAR(40) NOT NULL DEFAULT 'MANUAL',
            origem_id INT NULL,
            ativo_matricula VARCHAR(50) NOT NULL,
            tipo_equipamento VARCHAR(150) NOT NULL,
            descricao_servico TEXT NOT NULL,
            data_abertura DATETIME NOT NULL,
            prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',
            status_os VARCHAR(30) NOT NULL DEFAULT 'Aberto',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->query("
        SELECT id, codigo_os, ativo_matricula, descricao_servico, status_os, data_abertura
        FROM oficina_ordens_servico
        ORDER BY id DESC
        LIMIT 200
    ");
    $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $erro = 'Não foi possível carregar ordens de serviço.';
}
?>
<?php require_once __DIR__ . '/../../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Oficina</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong>
        </div>
    </div>

    <div class="dashboard-container">
        <style>
            .card {
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 18px;
                box-shadow: 0 6px 16px rgba(17,24,39,0.08);
            }
            .form-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(180px, 1fr));
                gap: 10px;
                margin-bottom: 14px;
            }
            .form-grid input, .form-grid textarea {
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 10px;
                font-size: 13px;
            }
            .form-grid textarea { grid-column: span 3; min-height: 88px; resize: vertical; }
            .btn {
                border: 0;
                border-radius: 8px;
                background: #111827;
                color: #fff;
                padding: 10px 14px;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
            }
            .table-wrap {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                overflow-x: auto;
            }
            table {
                width: 100%;
                min-width: 860px;
                border-collapse: collapse;
                font-size: 12px;
            }
            th, td {
                padding: 10px 8px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                white-space: nowrap;
            }
            th {
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: .4px;
                color: #64748b;
                background: #f8fafc;
            }
            .desc {
                max-width: 320px;
                overflow: hidden;
                text-overflow: ellipsis;
                display: inline-block;
            }
            .pill {
                display: inline-block;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 700;
                background: #fff7ed;
                color: #c2410c;
                border: 1px solid #fed7aa;
            }
        </style>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:10px; flex-wrap:wrap;">
                <h3 style="margin:0;">Ordens de Serviço</h3>
                <a href="/vilcon-systemon/app/modules/oficina/index.php?view=ordens_servico&mode=list" style="font-size:12px; text-decoration:none; color:#111827;">Abrir módulo completo</a>
            </div>

            <?php if ($erro): ?>
                <div style="margin-bottom:10px; background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:10px; border-radius:8px; font-size:12px;">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="../../../processar_oficina.php" class="form-grid">
                <input type="hidden" name="acao" value="abrir_os">
                <input name="ativo_matricula" placeholder="Ativo / Matrícula" required>
                <input name="tecnico_diagnostico" placeholder="Técnico">
                <input name="equipamento" placeholder="Equipamento">
                <textarea name="descricao_avaria" placeholder="Descrição da avaria"></textarea>
                <div style="grid-column: span 3;">
                    <button class="btn" type="submit">Abrir OS</button>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Ativo</th>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ordens) === 0): ?>
                            <tr><td colspan="7" style="text-align:center;color:#64748b;">Sem ordens de serviço para mostrar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ordens as $os): ?>
                                <tr>
                                    <td>#<?= (int)$os['id'] ?></td>
                                    <td><?= htmlspecialchars((string)($os['codigo_os'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($os['ativo_matricula'] ?? '-')) ?></td>
                                    <td><span class="desc" title="<?= htmlspecialchars((string)($os['descricao_servico'] ?? '')) ?>"><?= htmlspecialchars((string)($os['descricao_servico'] ?? '-')) ?></span></td>
                                    <td><span class="pill"><?= htmlspecialchars((string)($os['status_os'] ?? 'Aberto')) ?></span></td>
                                    <td><?= htmlspecialchars((string)($os['data_abertura'] ?? '-')) ?></td>
                                    <td><a href="visualizar.php?id=<?= (int)$os['id'] ?>">Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
