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

    function vilconEnsureNotificationsTable(PDO $pdo): void {
        static $ready = false;
        if ($ready) {
            return;
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS usuarios_notificacoes (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    titulo VARCHAR(160) NOT NULL,
                    mensagem TEXT NOT NULL,
                    tipo VARCHAR(20) NOT NULL DEFAULT 'info',
                    link VARCHAR(255) NULL,
                    lida_em DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_notif_usuario (usuario_id),
                    INDEX idx_notif_usuario_lida (usuario_id, lida_em),
                    INDEX idx_notif_data (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $ready = true;
        } catch (Throwable $e) {
            // Nao interromper telas por causa de alteracao estrutural.
        }
    }

    function vilconFetchUserNotifications(PDO $pdo, int $uid, int $limit = 12): array {
        if ($uid <= 0) {
            return ['unread_count' => 0, 'items' => []];
        }
        vilconEnsureNotificationsTable($pdo);
        $limit = max(1, min(30, $limit));
        try {
            $stCount = $pdo->prepare("SELECT COUNT(*) FROM usuarios_notificacoes WHERE usuario_id = :uid AND lida_em IS NULL");
            $stCount->execute(['uid' => $uid]);
            $unread = (int)$stCount->fetchColumn();

            $stList = $pdo->prepare("
                SELECT id, titulo, mensagem, tipo, link, lida_em, created_at
                FROM usuarios_notificacoes
                WHERE usuario_id = :uid
                ORDER BY id DESC
                LIMIT {$limit}
            ");
            $stList->execute(['uid' => $uid]);
            $items = $stList->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return ['unread_count' => $unread, 'items' => $items];
        } catch (Throwable $e) {
            return ['unread_count' => 0, 'items' => []];
        }
    }

    function vilconMarkNotificationsAsRead(PDO $pdo, int $uid): void {
        if ($uid <= 0) {
            return;
        }
        vilconEnsureNotificationsTable($pdo);
        try {
            $st = $pdo->prepare("UPDATE usuarios_notificacoes SET lida_em = NOW() WHERE usuario_id = :uid AND lida_em IS NULL");
            $st->execute(['uid' => $uid]);
        } catch (Throwable $e) {
            // Ignorar.
        }
    }

    function vilconCreateUserNotification(int $usuarioId, string $titulo, string $mensagem, string $tipo = 'info', string $link = ''): bool {
        $pdo = vilconProfileWidgetPdo();
        if ($pdo === null || $usuarioId <= 0) {
            return false;
        }
        vilconEnsureNotificationsTable($pdo);
        $tiposPermitidos = ['info', 'success', 'warning', 'error'];
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, $tiposPermitidos, true)) {
            $tipo = 'info';
        }
        try {
            $st = $pdo->prepare("
                INSERT INTO usuarios_notificacoes (usuario_id, titulo, mensagem, tipo, link, created_at)
                VALUES (:uid, :titulo, :mensagem, :tipo, :link, NOW())
            ");
            return $st->execute([
                'uid' => $usuarioId,
                'titulo' => trim($titulo) !== '' ? trim($titulo) : 'Notificacao',
                'mensagem' => trim($mensagem) !== '' ? trim($mensagem) : 'Sem detalhes.',
                'tipo' => $tipo,
                'link' => trim($link),
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    function vilconCreateNotificationForAllUsers(string $titulo, string $mensagem, string $tipo = 'info', string $link = ''): int {
        $pdo = vilconProfileWidgetPdo();
        if ($pdo === null) {
            return 0;
        }
        vilconEnsureNotificationsTable($pdo);
        try {
            $usuarios = $pdo->query("SELECT id FROM usuarios")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $total = 0;
            foreach ($usuarios as $uid) {
                if (vilconCreateUserNotification((int)$uid, $titulo, $mensagem, $tipo, $link)) {
                    $total++;
                }
            }
            return $total;
        } catch (Throwable $e) {
            return 0;
        }
    }

    function vilconHandleNotificationsApi(): void {
        if ((string)($_GET['__user_notifications_api'] ?? '') !== '1') {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        $uid = (int)($_SESSION['usuario_id'] ?? 0);
        $pdo = vilconProfileWidgetPdo();
        if ($uid <= 0 || $pdo === null) {
            echo json_encode(['ok' => false, 'unread_count' => 0, 'items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ((string)($_GET['mark_read'] ?? '') === '1') {
            vilconMarkNotificationsAsRead($pdo, $uid);
        }

        $payload = vilconFetchUserNotifications($pdo, $uid, 15);
        echo json_encode(
            [
                'ok' => true,
                'unread_count' => (int)($payload['unread_count'] ?? 0),
                'items' => $payload['items'] ?? [],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
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

    vilconHandleNotificationsApi();
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
        $notificacoesIniciais = ['unread_count' => 0, 'items' => []];
        $pdo = vilconProfileWidgetPdo();
        $uid = (int)($u['id'] ?? 0);
        if ($pdo !== null && $uid > 0) {
            $notificacoesIniciais = vilconFetchUserNotifications($pdo, $uid, 15);
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
            .user-bell-btn,
            .user-notif-fab {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #0f172a;
                width: 32px;
                height: 32px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                position: relative;
                box-shadow: 0 3px 10px rgba(15, 23, 42, 0.12);
            }
            .user-bell-btn i,
            .user-notif-fab i { font-size: 13px; }
            .user-notif-fab {
                position: fixed;
                top: 16px;
                right: 62px;
                z-index: 2000;
                display: none;
            }
            .user-notif-fab.show { display: inline-flex; }
            .user-notif-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 999px;
                background: #dc2626;
                color: #ffffff;
                font-size: 10px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #ffffff;
            }
            .user-notif-badge.hidden { display: none; }
            .user-notif-panel {
                position: fixed;
                width: min(360px, calc(100vw - 16px));
                max-height: 450px;
                border: 1px solid #dbe3ee;
                border-radius: 12px;
                background: #ffffff;
                box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
                z-index: 2200;
                display: none;
                overflow: hidden;
            }
            .user-notif-panel.open { display: block; }
            .user-notif-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                padding: 10px 12px;
                border-bottom: 1px solid #e5e7eb;
                background: #f8fafc;
            }
            .user-notif-head h5 {
                margin: 0;
                font-size: 12px;
                text-transform: uppercase;
                color: #0f172a;
            }
            .user-notif-read {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #334155;
                border-radius: 8px;
                font-size: 10px;
                font-weight: 700;
                padding: 5px 7px;
                cursor: pointer;
            }
            .user-notif-list {
                list-style: none;
                margin: 0;
                padding: 0;
                overflow-y: auto;
                max-height: 390px;
            }
            .user-notif-empty {
                padding: 14px 12px;
                font-size: 12px;
                color: #64748b;
            }
            .user-notif-item {
                padding: 10px 12px;
                border-bottom: 1px solid #eef2f7;
                display: grid;
                gap: 4px;
            }
            .user-notif-item.unread { background: #f8fbff; }
            .user-notif-title {
                font-size: 12px;
                font-weight: 800;
                color: #0f172a;
            }
            .user-notif-msg {
                font-size: 12px;
                color: #334155;
                line-height: 1.35;
            }
            .user-notif-meta {
                font-size: 10px;
                color: #64748b;
                display: flex;
                justify-content: space-between;
            }
            .user-notif-toast-wrap {
                position: fixed;
                right: 12px;
                bottom: 12px;
                z-index: 2300;
                display: grid;
                gap: 8px;
            }
            .user-notif-toast {
                background: #111827;
                color: #ffffff;
                border-radius: 10px;
                padding: 10px 12px;
                font-size: 12px;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.24);
            }
            .user-profile-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 2100;
                display: none;
                overflow-y: auto;
            }
            .user-profile-overlay.open {
                display: block;
            }
            .user-profile-modal {
                position: fixed;
                top: 10px;
                left: 50%;
                transform: translateX(-50%);
                max-width: 760px;
                width: min(760px, 100%);
                margin: 0;
                max-height: calc(100vh - 20px);
                overflow-y: auto;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
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
                .user-notif-fab { top: 10px; right: 54px; }
                .user-profile-modal {
                    top: 6px;
                    left: 6px;
                    right: 6px;
                    transform: none;
                    width: auto;
                    max-height: calc(100vh - 12px);
                }
                .user-profile-body { grid-template-columns: 1fr; }
                .user-form .row { grid-template-columns: 1fr; }
            }
        </style>

        <button type="button" class="user-profile-fab" id="globalUserFab" title="Perfil">
            <i class="fa-regular fa-user"></i>
        </button>

        <button type="button" class="user-notif-fab" id="globalUserNotifFab" title="Notificacoes">
            <i class="fa-regular fa-bell"></i>
            <span class="user-notif-badge <?= ((int)($notificacoesIniciais['unread_count'] ?? 0) > 0) ? '' : 'hidden' ?>" id="globalNotifBadge"><?= (int)($notificacoesIniciais['unread_count'] ?? 0) ?></span>
        </button>

        <div class="user-notif-panel" id="userNotifPanel" aria-hidden="true">
            <div class="user-notif-head">
                <h5>Notificacoes</h5>
                <button type="button" class="user-notif-read" id="userNotifReadAll">Marcar lidas</button>
            </div>
            <ul class="user-notif-list" id="userNotifList"></ul>
            <div class="user-notif-empty" id="userNotifEmpty">Sem notificacoes para mostrar.</div>
        </div>

        <div class="user-notif-toast-wrap" id="userNotifToastWrap"></div>

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
            var notifFab = document.getElementById('globalUserNotifFab');
            var globalNotifBadge = document.getElementById('globalNotifBadge');
            var notifPanel = document.getElementById('userNotifPanel');
            var notifListEl = document.getElementById('userNotifList');
            var notifEmptyEl = document.getElementById('userNotifEmpty');
            var notifReadAllBtn = document.getElementById('userNotifReadAll');
            var notifToastWrap = document.getElementById('userNotifToastWrap');
            var closeBtn = document.getElementById('userProfileClose');
            var notificationButtons = [];
            var lastUnreadCount = 0;
            var firstFetchDone = false;
            var initialPayload = <?= json_encode($notificacoesIniciais, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            if (!overlay || !fab || !closeBtn || !notifFab || !notifPanel || !notifListEl || !notifEmptyEl || !notifReadAllBtn || !notifToastWrap || !globalNotifBadge) return;

            function openModal() {
                closeNotifPanel();
                overlay.classList.add('open');
                overlay.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                overlay.classList.remove('open');
                overlay.setAttribute('aria-hidden', 'true');
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatDateIso(value) {
                if (!value) return '';
                var d = new Date(value.replace(' ', 'T'));
                if (Number.isNaN(d.getTime())) return value;
                var day = String(d.getDate()).padStart(2, '0');
                var month = String(d.getMonth() + 1).padStart(2, '0');
                var year = d.getFullYear();
                var hh = String(d.getHours()).padStart(2, '0');
                var mm = String(d.getMinutes()).padStart(2, '0');
                return day + '/' + month + '/' + year + ' ' + hh + ':' + mm;
            }

            function setUnreadBadge(count) {
                var value = parseInt(count || 0, 10);
                if (Number.isNaN(value) || value < 0) value = 0;
                var text = value > 99 ? '99+' : String(value);
                if (value > 0) {
                    globalNotifBadge.textContent = text;
                    globalNotifBadge.classList.remove('hidden');
                } else {
                    globalNotifBadge.textContent = '0';
                    globalNotifBadge.classList.add('hidden');
                }

                notificationButtons.forEach(function(btn) {
                    var b = btn.querySelector('.user-notif-badge');
                    if (!b) return;
                    if (value > 0) {
                        b.textContent = text;
                        b.classList.remove('hidden');
                    } else {
                        b.textContent = '0';
                        b.classList.add('hidden');
                    }
                });
            }

            function renderNotifications(items) {
                var list = Array.isArray(items) ? items : [];
                if (list.length === 0) {
                    notifListEl.innerHTML = '';
                    notifEmptyEl.style.display = 'block';
                    return;
                }

                notifEmptyEl.style.display = 'none';
                var html = list.map(function(item) {
                    var isUnread = !item.lida_em;
                    var title = escapeHtml(item.titulo || 'Notificacao');
                    var msg = escapeHtml(item.mensagem || '');
                    var link = String(item.link || '').trim();
                    var dateText = escapeHtml(formatDateIso(item.created_at || ''));
                    var type = escapeHtml(String(item.tipo || 'info').toUpperCase());
                    var openLink = '';
                    if (link !== '') {
                        openLink = ' <a href="' + escapeHtml(link) + '" style="font-weight:700; color:#0f172a; text-decoration:none;">Abrir</a>';
                    }
                    return ''
                        + '<li class="user-notif-item ' + (isUnread ? 'unread' : '') + '">'
                        + '<div class="user-notif-title">' + title + '</div>'
                        + '<div class="user-notif-msg">' + msg + openLink + '</div>'
                        + '<div class="user-notif-meta"><span>' + type + '</span><span>' + dateText + '</span></div>'
                        + '</li>';
                }).join('');
                notifListEl.innerHTML = html;
            }

            function showToast(text) {
                var toast = document.createElement('div');
                toast.className = 'user-notif-toast';
                toast.textContent = text;
                notifToastWrap.appendChild(toast);
                window.setTimeout(function() {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 4500);
            }

            function notificationsApiUrl(markRead) {
                var url = window.location.pathname + '?__user_notifications_api=1';
                if (markRead) {
                    url += '&mark_read=1';
                }
                return url;
            }

            function applyNotificationPayload(payload) {
                if (!payload || payload.ok !== true) return;
                var unread = parseInt(payload.unread_count || 0, 10);
                if (Number.isNaN(unread) || unread < 0) unread = 0;
                if (firstFetchDone && unread > lastUnreadCount) {
                    showToast('Nova notificacao recebida.');
                }
                lastUnreadCount = unread;
                firstFetchDone = true;
                setUnreadBadge(unread);
                renderNotifications(payload.items || []);
            }

            function fetchNotifications(markRead) {
                return fetch(notificationsApiUrl(markRead), { credentials: 'same-origin', cache: 'no-store' })
                    .then(function(resp) { return resp.json(); })
                    .then(function(payload) {
                        applyNotificationPayload(payload);
                        return payload;
                    })
                    .catch(function() {
                        return null;
                    });
            }

            function positionNotifPanel(anchor) {
                var target = anchor || notifFab;
                var rect = target.getBoundingClientRect();
                var width = Math.min(360, window.innerWidth - 16);
                var left = Math.max(8, Math.min(window.innerWidth - width - 8, rect.right - width));
                notifPanel.style.width = width + 'px';
                notifPanel.style.top = (rect.bottom + 8) + 'px';
                notifPanel.style.left = left + 'px';
            }

            function openNotifPanel(anchor) {
                closeModal();
                positionNotifPanel(anchor || notifFab);
                notifPanel.classList.add('open');
                notifPanel.setAttribute('aria-hidden', 'false');
                fetchNotifications(true);
            }

            function closeNotifPanel() {
                notifPanel.classList.remove('open');
                notifPanel.setAttribute('aria-hidden', 'true');
            }

            function toggleNotifPanel(anchor) {
                if (notifPanel.classList.contains('open')) {
                    closeNotifPanel();
                    return;
                }
                openNotifPanel(anchor || notifFab);
            }

            fab.addEventListener('click', openModal);
            notifFab.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleNotifPanel(notifFab);
            });
            notifReadAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetchNotifications(true);
            });
            closeBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeModal();
            });
            document.addEventListener('click', function(e) {
                if (notifPanel.classList.contains('open') && !notifPanel.contains(e.target) && !e.target.closest('.user-bell-btn') && e.target !== notifFab && !notifFab.contains(e.target)) {
                    closeNotifPanel();
                }
            });
            window.addEventListener('resize', function() {
                if (notifPanel.classList.contains('open')) {
                    positionNotifPanel(notificationButtons[0] || notifFab);
                }
            });

            function bindUserInfoAndFab() {
                var userInfos = Array.prototype.slice.call(document.querySelectorAll('.user-info'));
                if (userInfos.length > 0) {
                    fab.classList.remove('show');
                    notifFab.classList.remove('show');
                } else {
                    fab.classList.add('show');
                    notifFab.classList.add('show');
                }

                notificationButtons = [];
                userInfos.forEach(function(el) {
                    if (el.dataset.userMenuBound === '1') {
                        var existent = el.querySelector('.user-bell-btn');
                        if (existent) {
                            notificationButtons.push(existent);
                        }
                        return;
                    }
                    el.dataset.userMenuBound = '1';
                    el.classList.add('user-menu-ready');
                    if (!el.querySelector('i')) {
                        var ic = document.createElement('i');
                        ic.className = 'fa-regular fa-user';
                        el.insertBefore(ic, el.firstChild);
                    }

                    var bellBtn = document.createElement('button');
                    bellBtn.type = 'button';
                    bellBtn.className = 'user-bell-btn';
                    bellBtn.title = 'Notificacoes';
                    bellBtn.innerHTML = '<i class="fa-regular fa-bell"></i><span class="user-notif-badge hidden">0</span>';
                    bellBtn.addEventListener('click', function(ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        toggleNotifPanel(bellBtn);
                    });
                    el.appendChild(bellBtn);
                    notificationButtons.push(bellBtn);
                    el.addEventListener('click', openModal);
                });
                setUnreadBadge(lastUnreadCount);
            }

            applyNotificationPayload({
                ok: true,
                unread_count: initialPayload.unread_count || 0,
                items: initialPayload.items || []
            });

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

            window.setInterval(function() {
                fetchNotifications(false);
            }, 25000);
        })();
        </script>
        <?php
    }
}
