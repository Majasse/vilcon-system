<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/core/access_control.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$page_title = 'Utilizadores | Vilcon System';

$erro = null;
$msg = null;
$utilizadores = [];
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$perfilSessao = (string)($_SESSION['usuario_perfil'] ?? '');
$podeGerirUtilizadores = usuarioPodeAcederModulo($perfilSessao, 'utilizadores');

function valorCampo($row, $campos, $padrao = '') {
    foreach ($campos as $campo) {
        if (isset($row[$campo]) && $row[$campo] !== '') {
            return $row[$campo];
        }
    }
    return $padrao;
}

function usernamePadraoPorNome(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $primeiroNome = $partes[0] ?? 'User';
    $primeiroNome = preg_replace('/[^a-z0-9]/i', '', $primeiroNome) ?: 'User';
    return 'V' . $primeiroNome;
}

try {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['acao_admin'])) {
        if (!$podeGerirUtilizadores) {
            throw new RuntimeException('Apenas administradores podem gerir contas.');
        }

        $acaoAdmin = trim((string)($_POST['acao_admin'] ?? ''));
        $alvoId = (int)($_POST['usuario_id'] ?? 0);
        if ($alvoId <= 0) {
            throw new RuntimeException('Utilizador alvo invalido.');
        }

        if ($acaoAdmin === 'toggle_status') {
            if ($alvoId === (int)($_SESSION['usuario_id'] ?? 0)) {
                throw new RuntimeException('Nao pode bloquear a sua propria conta.');
            }

            $st = $pdo->prepare('SELECT status FROM usuarios WHERE id = :id LIMIT 1');
            $st->execute([':id' => $alvoId]);
            $statusAtual = (string)($st->fetchColumn() ?: '');
            if ($statusAtual === '') {
                throw new RuntimeException('Utilizador nao encontrado.');
            }

            $novoStatus = (strtolower($statusAtual) === 'ativo') ? 'Inativo' : 'Ativo';
            $up = $pdo->prepare('UPDATE usuarios SET status = :status WHERE id = :id');
            $up->execute([
                ':status' => $novoStatus,
                ':id' => $alvoId,
            ]);

            $msg = 'Estado da conta atualizado para ' . $novoStatus . '.';
        }

        if ($acaoAdmin === 'alterar_senha') {
            $novaSenha = (string)($_POST['nova_senha'] ?? '');
            if (strlen($novaSenha) < 6) {
                throw new RuntimeException('A nova senha deve ter pelo menos 6 caracteres.');
            }

            $stExiste = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
            $stExiste->execute([':id' => $alvoId]);
            if (!$stExiste->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Utilizador nao encontrado para alterar senha.');
            }

            $up = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
            $up->execute([
                ':senha' => $novaSenha,
                ':id' => $alvoId,
            ]);

            $msg = 'Senha atualizada com sucesso.';
        }
    }

    $temUltimoLogin = false;
    $stmtCols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_login'");
    $temUltimoLogin = (bool)$stmtCols->fetch(PDO::FETCH_ASSOC);

    $colUsername = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'username'")->fetch(PDO::FETCH_ASSOC);
    if (!$colUsername) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN username VARCHAR(80) NULL AFTER nome");
    }
    $idxUsername = $pdo->query("SHOW INDEX FROM usuarios WHERE Key_name = 'idx_usuarios_username'")->fetch(PDO::FETCH_ASSOC);
    if (!$idxUsername) {
        $pdo->exec("CREATE UNIQUE INDEX idx_usuarios_username ON usuarios (username)");
    }

    $semUsername = $pdo->query("SELECT id, nome FROM usuarios WHERE username IS NULL OR username = ''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $updUsername = $pdo->prepare("UPDATE usuarios SET username = :username WHERE id = :id");
    foreach ($semUsername as $u) {
        $base = usernamePadraoPorNome((string)($u['nome'] ?? ''));
        $candidato = $base;
        $n = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :u AND id <> :id");
            $chk->execute([':u' => $candidato, ':id' => (int)$u['id']]);
            if ((int)$chk->fetchColumn() === 0) {
                break;
            }
            $n++;
            $candidato = $base . $n;
        }
        $updUsername->execute([
            ':username' => $candidato,
            ':id' => (int)$u['id'],
        ]);
    }

    $whereSql = '';
    $params = [];
    if ($q !== '') {
        $whereSql = ' WHERE u.nome LIKE :q OR u.username LIKE :q OR u.email LIKE :q OR u.perfil LIKE :q ';
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
            .table th:last-child,
            .table td:last-child {
                min-width: 360px;
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
            .acoes-wrap {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                min-width: 280px;
                position: relative;
                white-space: nowrap;
            }
            .acoes-topo {
                display: flex;
                gap: 6px;
                flex-wrap: nowrap;
            }
            .acao-form-inline {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .input-nova-senha {
                padding: 7px 9px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-size: 11px;
                min-width: 120px;
            }
            .senha-card {
                display: none;
                position: absolute;
                right: 0;
                top: 66px;
                z-index: 30;
                width: 250px;
                background: #ffffff;
                border: 1px solid #d1d5db;
                border-radius: 10px;
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
                padding: 10px;
            }
            .senha-card.open {
                display: block;
            }
            .senha-card-title {
                font-size: 11px;
                color: #475569;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .senha-card-actions {
                display: flex;
                justify-content: flex-end;
                gap: 6px;
                margin-top: 8px;
            }
            .btn-ghost {
                border: 1px solid #d1d5db;
                background: #f8fafc;
                color: #334155;
                padding: 7px 10px;
                border-radius: 8px;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
            }
        </style>

        <div class="card">
            <?php if ($msg): ?>
                <div style="background:#ecfdf5;color:#166534;border:1px solid #86efac;padding:12px 14px;border-radius:8px;margin-bottom:15px;font-size:12px;font-weight:700;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px; flex-wrap:wrap;">
                <form method="GET" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div style="display:flex; align-items:center; gap:8px; background:#1e1e1e; border:1px solid #2a2a2a; padding:8px 12px; border-radius:999px;">
                        <i class="fa-solid fa-magnifying-glass" style="color:#999;"></i>
                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($q) ?>"
                            placeholder="Pesquisar por nome, username ou perfil..."
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
                            <th>Username</th>
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
                                $username = valorCampo($u, ['username'], '-');
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
                                <td><strong><?= htmlspecialchars((string)$username) ?></strong></td>
                                <td><?= htmlspecialchars((string)$perfil) ?></td>
                                <td>
                                    <span class="badge <?= $classeStatus ?>">
                                        <?= htmlspecialchars($status !== '' ? (string)$status : '-') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string)$ultimoLogin ?: '-') ?></td>
                                <td>
                                    <div class="acoes-wrap">
                                        <div class="acoes-topo">
                                            <a class="btn-action" href="<?= htmlspecialchars($urlAcoes) ?>">
                                                <i class="fa-solid fa-clock-rotate-left"></i> Ver acoes
                                            </a>
                                            <?php if ($podeGerirUtilizadores): ?>
                                                <form method="post" action="" class="acao-form-inline">
                                                    <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string)$id) ?>">
                                                    <input type="hidden" name="acao_admin" value="toggle_status">
                                                    <button type="submit" class="btn-action" style="background:#7f1d1d;">
                                                        <i class="fa-solid fa-ban"></i>
                                                        <?= $classeStatus === 'ativo' ? 'Bloquear' : 'Desbloquear' ?>
                                                    </button>
                                                </form>
                                                <button
                                                    type="button"
                                                    class="btn-action"
                                                    style="background:#1d4ed8;"
                                                    data-open-senha-card="<?= htmlspecialchars((string)$id) ?>"
                                                >
                                                    <i class="fa-solid fa-key"></i> Alterar senha
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($podeGerirUtilizadores): ?>
                                            <form method="post" action="" class="senha-card" data-senha-card="<?= htmlspecialchars((string)$id) ?>">
                                                <input type="hidden" name="usuario_id" value="<?= htmlspecialchars((string)$id) ?>">
                                                <input type="hidden" name="acao_admin" value="alterar_senha">
                                                <div class="senha-card-title">Nova senha</div>
                                                <input
                                                    type="text"
                                                    name="nova_senha"
                                                    placeholder="Digite a nova senha"
                                                    required
                                                    class="input-nova-senha"
                                                    style="width:100%;"
                                                >
                                                <div class="senha-card-actions">
                                                    <button type="button" class="btn-ghost" data-close-senha-card>Fechar</button>
                                                    <button type="submit" class="btn-action" style="background:#1d4ed8;">
                                                        <i class="fa-solid fa-floppy-disk"></i> Salvar
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    function fecharTodosCards() {
        document.querySelectorAll('[data-senha-card]').forEach(function (card) {
            card.classList.remove('open');
        });
    }

    document.querySelectorAll('[data-open-senha-card]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            var id = btn.getAttribute('data-open-senha-card');
            var card = document.querySelector('[data-senha-card="' + id + '"]');
            if (!card) return;

            var aberto = card.classList.contains('open');
            fecharTodosCards();
            if (!aberto) {
                card.classList.add('open');
            }
        });
    });

    document.querySelectorAll('[data-close-senha-card]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('[data-senha-card]');
            if (card) {
                card.classList.remove('open');
            }
        });
    });

    document.addEventListener('click', function () {
        fecharTodosCards();
    });

    document.querySelectorAll('[data-senha-card], [data-open-senha-card]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
