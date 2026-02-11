<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Acoes do Utilizador | Vilcon System';

$erro = null;
$usuarioId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$utilizador = null;
$acoes = [];

try {
    if ($usuarioId <= 0) {
        throw new RuntimeException('Utilizador invalido.');
    }

    $stmtUser = $pdo->prepare('SELECT id, nome, email, perfil, status FROM usuarios WHERE id = :id LIMIT 1');
    $stmtUser->execute(['id' => $usuarioId]);
    $utilizador = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$utilizador) {
        throw new RuntimeException('Utilizador nao encontrado.');
    }

    $stmtAcoes = $pdo->prepare(
        'SELECT acao, tabela_afetada, data_hora
         FROM auditoria
         WHERE usuario_id = :id
         ORDER BY data_hora DESC
         LIMIT 500'
    );
    $stmtAcoes->execute(['id' => $usuarioId]);
    $acoes = $stmtAcoes->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $erro = $e->getMessage();
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Acoes do Utilizador</h2>
        <div class="user-info">
            <i class="fa-regular fa-user"></i>
            <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong>
        </div>
    </div>

    <div class="dashboard-container">
        <style>
            body { background: #f4f7f6; color: #111827; }
            .main-content { background: #f4f7f6; }
            .top-bar { background: #ffffff; border-bottom: 1px solid #e5e7eb; }
            .user-info { color: #6b7280; }
            .user-info strong { color: #111827; }
            .card {
                background: #ffffff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 6px 16px rgba(17,24,39,0.08);
                border: 1px solid #e5e7eb;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }
            .table th,
            .table td {
                padding: 12px 10px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                vertical-align: middle;
            }
            .table th {
                color: #6b7280;
                font-size: 11px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            .muted { color: #6b7280; }
            .error {
                background: #c0392b;
                color: #fff;
                padding: 12px 14px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            .btn-back {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #111827;
                color: #fff;
                border: none;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 11px;
                font-weight: 700;
                text-decoration: none;
            }
        </style>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                <a href="index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Voltar aos utilizadores</a>
                <?php if ($utilizador): ?>
                    <div class="muted" style="font-size:12px;">
                        <?= htmlspecialchars((string)$utilizador['nome']) ?> (<?= htmlspecialchars((string)$utilizador['email']) ?>)
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($erro): ?>
                <div class="error"><?= htmlspecialchars($erro) ?></div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Acao</th>
                            <th>Contexto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($acoes) === 0): ?>
                            <tr>
                                <td colspan="3" class="muted">Sem acoes registadas para este utilizador.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($acoes as $acao): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($acao['data_hora'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($acao['acao'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($acao['tabela_afetada'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
