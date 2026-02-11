<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Utilizadores | Vilcon System';

$erro = null;
$utilizadores = [];
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

function valorCampo($row, $campos, $padrao = '') {
    foreach ($campos as $campo) {
        if (isset($row[$campo]) && $row[$campo] !== '') {
            return $row[$campo];
        }
    }
    return $padrao;
}

try {
    $temUltimoLogin = false;
    $stmtCols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_login'");
    $temUltimoLogin = (bool)$stmtCols->fetch(PDO::FETCH_ASSOC);

    $whereSql = '';
    $params = [];
    if ($q !== '') {
        $whereSql = ' WHERE u.nome LIKE :q OR u.email LIKE :q OR u.perfil LIKE :q ';
        $params['q'] = '%' . $q . '%';
    }

    $selectUltimoLogin = $temUltimoLogin
        ? "COALESCE(u.ultimo_login, (SELECT MAX(a1.data_hora) FROM auditoria a1 WHERE a1.usuario_id = u.id AND a1.acao LIKE 'LOGIN:%')) AS ultimo_login"
        : "(SELECT MAX(a1.data_hora) FROM auditoria a1 WHERE a1.usuario_id = u.id AND a1.acao LIKE 'LOGIN:%') AS ultimo_login";

    $sqlUsuarios = "
        SELECT
            u.*,
            $selectUltimoLogin
        FROM usuarios u
        $whereSql
        ORDER BY u.id DESC
    ";

    $stmt = $pdo->prepare($sqlUsuarios);
    $stmt->execute($params);
    $utilizadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erro = 'Nao foi possivel carregar os utilizadores e a auditoria.';
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h2>Utilizadores</h2>
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
            .badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 700;
            }
            .badge.ativo { background: rgba(39, 174, 96, 0.2); color: #2ecc71; }
            .badge.inativo { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
            .muted { color: #6b7280; }
            .error {
                background: #c0392b;
                color: #fff;
                padding: 12px 14px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            .btn-action {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #111827;
                color: #fff;
                border: none;
                padding: 7px 10px;
                border-radius: 8px;
                font-size: 11px;
                font-weight: 700;
                text-decoration: none;
                white-space: nowrap;
            }
        </style>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px; flex-wrap:wrap;">
                <form method="GET" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:8px; background:#1e1e1e; border:1px solid #2a2a2a; padding:8px 12px; border-radius:999px;">
                        <i class="fa-solid fa-magnifying-glass" style="color:#999;"></i>
                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($q) ?>"
                            placeholder="Pesquisar utilizadores..."
                            style="background:transparent; border:none; outline:none; color:#fff; font-size:12px; width:220px;"
                        >
                    </div>
                    <button type="submit" style="background:#2a2a2a; color:#fff; border:none; padding:8px 12px; border-radius:8px; font-size:11px; font-weight:700;">
                        Pesquisar
                    </button>
                    <?php if ($q !== ''): ?>
                        <a href="index.php" style="color:#6b7280; font-size:11px; text-decoration:none;">Limpar</a>
                    <?php endif; ?>
                </form>
                <div class="muted" style="font-size:11px;">
                    <?= count($utilizadores) ?> resultado(s)
                </div>
            </div>

            <?php if ($erro): ?>
                <div class="error"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <?php if (!$erro && count($utilizadores) === 0): ?>
                <p class="muted">Sem utilizadores para mostrar<?= $q !== '' ? ' para a pesquisa atual' : '' ?>.</p>
            <?php elseif (!$erro): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Ultimo Login</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilizadores as $u): ?>
                            <?php
                                $id = (int)valorCampo($u, ['id', 'usuario_id'], 0);
                                $nome = valorCampo($u, ['nome', 'nome_completo', 'usuario', 'username']);
                                $email = valorCampo($u, ['email', 'usuario_email']);
                                $perfil = valorCampo($u, ['perfil', 'cargo', 'role']);
                                $status = valorCampo($u, ['status', 'ativo'], '');
                                $ultimoLogin = valorCampo($u, ['ultimo_login']);
                                $statusLower = strtolower((string)$status);
                                $classeStatus = ($statusLower === 'ativo' || $statusLower === '1') ? 'ativo' : 'inativo';
                                $urlAcoes = 'acoes.php?usuario_id=' . $id;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$id) ?></td>
                                <td><?= htmlspecialchars((string)$nome) ?></td>
                                <td><?= htmlspecialchars((string)$email) ?></td>
                                <td><?= htmlspecialchars((string)$perfil) ?></td>
                                <td>
                                    <span class="badge <?= $classeStatus ?>">
                                        <?= htmlspecialchars($status !== '' ? (string)$status : '-') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string)$ultimoLogin ?: '-') ?></td>
                                <td>
                                    <a class="btn-action" href="<?= htmlspecialchars($urlAcoes) ?>">
                                        <i class="fa-solid fa-clock-rotate-left"></i> Ver acoes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
