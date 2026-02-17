<?php
if (!defined('VILCON_USER_PROFILE_WIDGET_LOADED')) {
    define('VILCON_USER_PROFILE_WIDGET_LOADED', true);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    function vilconProfileWidgetPdo(): ?PDO {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }
        static $tentouCarregar = false;
        if (!$tentouCarregar) {
            $tentouCarregar = true;
            $dbFile = dirname(__DIR__) . '/config/db.php';
            if (is_file($dbFile)) {
                require_once $dbFile;
                if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                    return $GLOBALS['pdo'];
                }
            }
        }
        return null;
    }

    function vilconEnsureUserColumns(PDO $pdo): void {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $map = [];
            foreach ($cols as $c) {
                $map[strtolower((string)($c['Field'] ?? ''))] = true;
            }
            if (!isset($map['foto_perfil'])) {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_perfil VARCHAR(255) NULL AFTER status");
            }
            if (!isset($map['idioma'])) {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN idioma VARCHAR(20) NOT NULL DEFAULT 'pt' AFTER foto_perfil");
            }
        } catch (Throwable $e) {
            // Nao interromper telas por causa de alteracao estrutural.
        }
    }

    function vilconCurrentPageUrl(): string {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $query = [];
        if (!empty($_GET)) {
            $query = $_GET;
            unset($query['perfil_msg']);
        }
        $qs = http_build_query($query);
        return $script . ($qs !== '' ? ('?' . $qs) : '');
    }

    function vilconProfilePhotoUrl(?string $foto): string {
        $f = trim((string)$foto);
        if ($f === '') {
            return '';
        }
        if (strpos($f, '/') === 0) {
            return $f;
        }
        return '/vilcon-systemon/public/uploads/usuarios/' . rawurlencode($f);
    }

    function vilconLoadUserProfileData(): array {
        $base = [
            'id' => (int)($_SESSION['usuario_id'] ?? 0),
            'nome' => (string)($_SESSION['usuario_nome'] ?? 'Utilizador'),
            'email' => '',
            'perfil' => (string)($_SESSION['usuario_perfil'] ?? ''),
            'status' => '',
            'foto_perfil' => '',
            'idioma' => (string)($_SESSION['usuario_idioma'] ?? 'pt'),
        ];

        $pdo = vilconProfileWidgetPdo();
        if ($pdo === null || $base['id'] <= 0) {
            return $base;
        }

        vilconEnsureUserColumns($pdo);

        try {
            $stmt = $pdo->prepare("SELECT id, nome, email, perfil, status, foto_perfil, idioma FROM usuarios WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $base['id']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($u)) {
                $base['nome'] = (string)($u['nome'] ?? $base['nome']);
                $base['email'] = (string)($u['email'] ?? '');
                $base['perfil'] = (string)($u['perfil'] ?? '');
                $base['status'] = (string)($u['status'] ?? '');
                $base['foto_perfil'] = (string)($u['foto_perfil'] ?? '');
                $base['idioma'] = (string)($u['idioma'] ?? $base['idioma']);
            }
        } catch (Throwable $e) {
            // Ignorar e seguir com dados de sessao.
        }

        $_SESSION['usuario_nome'] = $base['nome'];
        $_SESSION['usuario_idioma'] = $base['idioma'] !== '' ? $base['idioma'] : 'pt';

        return $base;
    }

    function vilconHandleProfileSubmit(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!isset($_POST['__user_profile_form']) || (string)$_POST['__user_profile_form'] !== '1') {
            return;
        }

        $pdo = vilconProfileWidgetPdo();
        $uid = (int)($_SESSION['usuario_id'] ?? 0);
        $redirect = vilconCurrentPageUrl();

        if ($pdo === null || $uid <= 0) {
            $_SESSION['perfil_flash'] = ['type' => 'error', 'msg' => 'Sessao invalida para atualizar perfil.'];
            header('Location: ' . $redirect);
            exit;
        }

        vilconEnsureUserColumns($pdo);
        $acao = trim((string)($_POST['perfil_acao'] ?? ''));

        try {
            if ($acao === 'atualizar_info') {
                $nome = trim((string)($_POST['nome'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                if ($nome === '' || $email === '') {
                    throw new RuntimeException('Nome e e-mail sao obrigatorios.');
                }

                $fotoSalvar = null;
                if (isset($_FILES['foto_perfil']) && is_array($_FILES['foto_perfil'])) {
                    $erroUpload = (int)($_FILES['foto_perfil']['error'] ?? 4);
                    if ($erroUpload !== 0 && $erroUpload !== 4) {
                        throw new RuntimeException('Falha no upload da foto. Verifique o tamanho e tente novamente.');
                    }
                }
                if (isset($_FILES['foto_perfil']) && is_array($_FILES['foto_perfil']) && (int)($_FILES['foto_perfil']['error'] ?? 4) === 0) {
                    $tmp = (string)($_FILES['foto_perfil']['tmp_name'] ?? '');
                    $orig = (string)($_FILES['foto_perfil']['name'] ?? '');
                    $size = (int)($_FILES['foto_perfil']['size'] ?? 0);
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($ext, $permitidos, true)) {
                        throw new RuntimeException('Foto invalida. Use JPG, PNG ou WEBP.');
                    }
                    if ($size > 5 * 1024 * 1024) {
                        throw new RuntimeException('A foto excede 5MB.');
                    }
                    $dir = dirname(__DIR__, 2) . '/public/uploads/usuarios';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }
                    $nomeArq = 'user_' . $uid . '_' . date('YmdHis') . '.' . $ext;
                    $dest = $dir . '/' . $nomeArq;
                    $okMove = @move_uploaded_file($tmp, $dest);
                    if (!$okMove && is_file($tmp)) {
                        $okMove = @copy($tmp, $dest);
                    }
                    if (!$okMove) {
                        throw new RuntimeException('Nao foi possivel salvar a foto.');
                    }
                    $fotoSalvar = $nomeArq;
                }

                $sql = "UPDATE usuarios SET nome = :nome, email = :email";
                $params = ['nome' => $nome, 'email' => $email, 'id' => $uid];
                if ($fotoSalvar !== null) {
                    $sql .= ", foto_perfil = :foto";
                    $params['foto'] = $fotoSalvar;
                }
                $sql .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['perfil_flash'] = ['type' => 'ok', 'msg' => 'Informacoes atualizadas com sucesso.'];
            } elseif ($acao === 'mudar_senha') {
                $atual = (string)($_POST['senha_atual'] ?? '');
                $nova = (string)($_POST['senha_nova'] ?? '');
                $conf = (string)($_POST['senha_confirmar'] ?? '');

                if ($nova === '' || strlen($nova) < 4) {
                    throw new RuntimeException('A nova senha deve ter pelo menos 4 caracteres.');
                }
                if ($nova !== $conf) {
                    throw new RuntimeException('Confirmacao da senha nao confere.');
                }

                $st = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id LIMIT 1");
                $st->execute(['id' => $uid]);
                $senhaDb = (string)$st->fetchColumn();
                if ($senhaDb === '' || $senhaDb !== $atual) {
                    throw new RuntimeException('Senha atual incorreta.');
                }

                $up = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
                $up->execute(['senha' => $nova, 'id' => $uid]);
                $_SESSION['perfil_flash'] = ['type' => 'ok', 'msg' => 'Senha alterada com sucesso.'];
            } elseif ($acao === 'atualizar_preferencias') {
                $idioma = trim((string)($_POST['idioma'] ?? 'pt'));
                $permitidos = ['pt', 'en', 'es'];
                if (!in_array($idioma, $permitidos, true)) {
                    $idioma = 'pt';
                }
                $up = $pdo->prepare("UPDATE usuarios SET idioma = :idioma WHERE id = :id");
                $up->execute(['idioma' => $idioma, 'id' => $uid]);
                $_SESSION['usuario_idioma'] = $idioma;
                $_SESSION['perfil_flash'] = ['type' => 'ok', 'msg' => 'Preferencias guardadas com sucesso.'];
            } else {
                throw new RuntimeException('Acao de perfil invalida.');
            }
        } catch (Throwable $e) {
            $_SESSION['perfil_flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        }

        header('Location: ' . $redirect);
        exit;
    }

    vilconHandleProfileSubmit();

    function renderUserProfileWidget(): void {
        $u = vilconLoadUserProfileData();
        $flash = $_SESSION['perfil_flash'] ?? null;
        unset($_SESSION['perfil_flash']);

        $fotoUrl = vilconProfilePhotoUrl((string)($u['foto_perfil'] ?? ''));
        $iniciais = 'US';
        $nomeBase = trim((string)($u['nome'] ?? ''));
        if ($nomeBase !== '') {
            $p = preg_split('/\s+/', $nomeBase);
            $a = strtoupper(substr((string)($p[0] ?? 'U'), 0, 1));
            $b = strtoupper(substr((string)($p[count($p) - 1] ?? 'S'), 0, 1));
            $iniciais = $a . $b;
        }
        ?>
        <style>
            .user-profile-fab {
                position: fixed;
                top: 16px;
                right: 18px;
                z-index: 2000;
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
                width: 38px;
                height: 38px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
                display: none;
            }
            .user-profile-fab.show { display: inline-flex; }
            .user-profile-fab i { font-size: 15px; }
            .user-info.user-menu-ready {
                cursor: pointer;
                border: 1px solid #e5e7eb;
                border-radius: 999px;
                padding: 7px 11px;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                background: #ffffff;
            }
            .user-profile-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 2100;
                display: none;
            }
            .user-profile-overlay.open { display: block; }
            .user-profile-modal {
                max-width: 760px;
                margin: 40px auto;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.24);
            }
            .user-profile-head {
                padding: 12px 14px;
                background: #111827;
                color: #ffffff;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .user-profile-head h4 { margin: 0; font-size: 13px; text-transform: uppercase; letter-spacing: .2px; }
            .user-profile-close {
                border: 1px solid #fca5a5;
                background: #fee2e2;
                color: #b91c1c;
                border-radius: 7px;
                font-size: 11px;
                font-weight: 700;
                padding: 6px 10px;
                cursor: pointer;
            }
            .user-profile-body { padding: 14px; display: grid; grid-template-columns: 230px 1fr; gap: 14px; }
            .user-profile-card {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 12px;
                background: #f8fafc;
            }
            .user-avatar {
                width: 96px;
                height: 96px;
                border-radius: 999px;
                overflow: hidden;
                background: #111827;
                color: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 28px;
                margin-bottom: 10px;
            }
            .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
            .user-profile-card .line { font-size: 12px; color: #334155; margin-bottom: 4px; }
            .user-profile-card .line strong { color: #0f172a; }
            .user-forms { display: grid; gap: 10px; }
            .user-form {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 12px;
                background: #ffffff;
            }
            .user-form h5 { margin: 0 0 8px 0; font-size: 12px; text-transform: uppercase; color: #111827; }
            .user-form .row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
            .user-form label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 4px; }
            .user-form input,
            .user-form select {
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
                background: #ffffff;
            }
            .user-form .btn {
                margin-top: 8px;
                border: none;
                background: #111827;
                color: #ffffff;
                border-radius: 8px;
                padding: 8px 12px;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
            }
            .user-flash {
                margin-bottom: 8px;
                border-radius: 8px;
                padding: 8px 10px;
                font-size: 12px;
                border: 1px solid transparent;
            }
            .user-flash.ok { background: #ecfdf3; border-color: #bbf7d0; color: #166534; }
            .user-flash.error { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
            @media (max-width: 900px) {
                .user-profile-fab { top: 10px; right: 10px; }
                .user-profile-modal { margin: 10px; }
                .user-profile-body { grid-template-columns: 1fr; }
                .user-form .row { grid-template-columns: 1fr; }
            }
        </style>

        <button type="button" class="user-profile-fab" id="globalUserFab" title="Perfil">
            <i class="fa-regular fa-user"></i>
        </button>

        <div class="user-profile-overlay" id="userProfileOverlay" aria-hidden="true">
            <div class="user-profile-modal" role="dialog" aria-modal="true" aria-label="Perfil do utilizador">
                <div class="user-profile-head">
                    <h4>Perfil do Utilizador</h4>
                    <button type="button" class="user-profile-close" id="userProfileClose">Fechar</button>
                </div>
                <div class="user-profile-body">
                    <div class="user-profile-card">
                        <div class="user-avatar">
                            <?php if ($fotoUrl !== ''): ?>
                                <img src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil">
                            <?php else: ?>
                                <?= htmlspecialchars($iniciais, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                        <div class="line"><strong>Nome:</strong> <?= htmlspecialchars((string)$u['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="line"><strong>E-mail:</strong> <?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="line"><strong>Perfil:</strong> <?= htmlspecialchars((string)$u['perfil'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="line"><strong>Status:</strong> <?= htmlspecialchars((string)$u['status'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="line"><strong>Idioma:</strong> <?= htmlspecialchars((string)$u['idioma'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>

                    <div class="user-forms">
                        <?php if (is_array($flash) && isset($flash['msg'])): ?>
                            <div class="user-flash <?= (($flash['type'] ?? '') === 'ok') ? 'ok' : 'error' ?>"><?= htmlspecialchars((string)$flash['msg'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form class="user-form" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="__user_profile_form" value="1">
                            <input type="hidden" name="perfil_acao" value="atualizar_info">
                            <h5>Informacoes e Foto</h5>
                            <div class="row">
                                <div>
                                    <label>Nome</label>
                                    <input type="text" name="nome" value="<?= htmlspecialchars((string)$u['nome'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div>
                                    <label>E-mail</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                            <div style="margin-top:8px;">
                                <label>Foto de Perfil (JPG/PNG/WEBP)</label>
                                <input type="file" name="foto_perfil" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                            <button type="submit" class="btn">Guardar Informacoes</button>
                        </form>

                        <form class="user-form" method="post">
                            <input type="hidden" name="__user_profile_form" value="1">
                            <input type="hidden" name="perfil_acao" value="mudar_senha">
                            <h5>Mudar Senha</h5>
                            <div class="row">
                                <div>
                                    <label>Senha Atual</label>
                                    <input type="password" name="senha_atual" required>
                                </div>
                                <div>
                                    <label>Nova Senha</label>
                                    <input type="password" name="senha_nova" required>
                                </div>
                            </div>
                            <div style="margin-top:8px;">
                                <label>Confirmar Nova Senha</label>
                                <input type="password" name="senha_confirmar" required>
                            </div>
                            <button type="submit" class="btn">Alterar Senha</button>
                        </form>

                        <form class="user-form" method="post">
                            <input type="hidden" name="__user_profile_form" value="1">
                            <input type="hidden" name="perfil_acao" value="atualizar_preferencias">
                            <h5>Preferencias</h5>
                            <div>
                                <label>Idioma</label>
                                <select name="idioma">
                                    <option value="pt" <?= (string)$u['idioma'] === 'pt' ? 'selected' : '' ?>>Portugues</option>
                                    <option value="en" <?= (string)$u['idioma'] === 'en' ? 'selected' : '' ?>>English</option>
                                    <option value="es" <?= (string)$u['idioma'] === 'es' ? 'selected' : '' ?>>Espanol</option>
                                </select>
                            </div>
                            <button type="submit" class="btn">Guardar Preferencias</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var overlay = document.getElementById('userProfileOverlay');
            var fab = document.getElementById('globalUserFab');
            var closeBtn = document.getElementById('userProfileClose');
            if (!overlay || !fab || !closeBtn) return;

            function openModal() {
                overlay.classList.add('open');
                overlay.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                overlay.classList.remove('open');
                overlay.setAttribute('aria-hidden', 'true');
            }

            fab.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeModal();
            });

            function bindUserInfoAndFab() {
                var userInfos = Array.prototype.slice.call(document.querySelectorAll('.user-info'));
                if (userInfos.length > 0) {
                    fab.classList.remove('show');
                } else {
                    fab.classList.add('show');
                }

                userInfos.forEach(function(el) {
                    if (el.dataset.userMenuBound === '1') {
                        return;
                    }
                    el.dataset.userMenuBound = '1';
                    el.classList.add('user-menu-ready');
                    if (!el.querySelector('i')) {
                        var ic = document.createElement('i');
                        ic.className = 'fa-regular fa-user';
                        el.insertBefore(ic, el.firstChild);
                    }
                    el.addEventListener('click', openModal);
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindUserInfoAndFab);
            } else {
                bindUserInfoAndFab();
            }
            window.addEventListener('load', bindUserInfoAndFab);

            if (window.MutationObserver) {
                var obs = new MutationObserver(function() {
                    bindUserInfoAndFab();
                });
                obs.observe(document.body, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }
}
