<?php
session_start();
require_once(__DIR__ . '/../app/config/db.php');

function garantirColunaUltimoLogin($pdo) {
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $verificado = true;

    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_login'");
    $coluna = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coluna) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ultimo_login DATETIME NULL AFTER status");
    }
}

function gerarUsernamePadrao($nome) {
    $partes = preg_split('/\s+/', trim((string)$nome)) ?: [];
    $primeiroNome = $partes[0] ?? 'User';
    $primeiroNome = preg_replace('/[^a-z0-9]/i', '', $primeiroNome) ?: 'User';
    return 'V' . $primeiroNome;
}

function garantirColunaUsername($pdo) {
    static $verificadoUsername = false;
    if ($verificadoUsername) {
        return;
    }
    $verificadoUsername = true;

    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'username'");
    $coluna = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coluna) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN username VARCHAR(80) NULL AFTER nome");
    }

    $idx = $pdo->query("SHOW INDEX FROM usuarios WHERE Key_name = 'idx_usuarios_username'")->fetch(PDO::FETCH_ASSOC);
    if (!$idx) {
        $pdo->exec("CREATE UNIQUE INDEX idx_usuarios_username ON usuarios (username)");
    }

    $semUsername = $pdo->query("SELECT id, nome FROM usuarios WHERE username IS NULL OR username = ''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $upd = $pdo->prepare("UPDATE usuarios SET username = :username WHERE id = :id");
    foreach ($semUsername as $u) {
        $base = gerarUsernamePadrao((string)($u['nome'] ?? ''));
        $cand = $base;
        $n = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username AND id <> :id");
            $chk->execute([
                'username' => $cand,
                'id' => (int)$u['id'],
            ]);
            if ((int)$chk->fetchColumn() === 0) {
                break;
            }
            $n++;
            $cand = $base . $n;
        }
        $upd->execute([
            'username' => $cand,
            'id' => (int)$u['id'],
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $usernameInput = $username;

    garantirColunaUsername($pdo);

    $sql = "SELECT * FROM usuarios
            WHERE username = :username
            AND senha = :senha
            AND status = 'Ativo'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'username' => $username,
        'senha' => $senha,
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nome'] = $user['nome'];
        $_SESSION['usuario_perfil'] = $user['perfil'];

        try {
            garantirColunaUltimoLogin($pdo);
            $up = $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id');
            $up->execute(['id' => (int)$user['id']]);
        } catch (Throwable $e) {
            // Nao bloquear login por causa de coluna/auditoria.
        }

        try {
            registrarAcaoSistema($pdo, 'LOGIN: utilizador autenticado', 'auth', (int)$user['id']);
        } catch (Throwable $e) {
            // Nao bloquear login por causa de auditoria.
        }

        header('Location: index.php');
        exit();
    } else {
        try {
            $usuarioFalhouId = null;
            if ($username !== '') {
                $stmtUser = $pdo->prepare('SELECT id FROM usuarios WHERE username = :username LIMIT 1');
                $stmtUser->execute(['username' => $username]);
                $usuarioFalhouId = (int)($stmtUser->fetchColumn() ?: 0);
                if ($usuarioFalhouId <= 0) {
                    $usuarioFalhouId = null;
                }
            }
            registrarAcaoSistema($pdo, 'LOGIN FALHOU: ' . $username, 'auth', $usuarioFalhouId);
        } catch (Throwable $e) {
            // Nao bloquear fluxo em falha de auditoria.
        }
        $erro = 'Username ou palavra-passe incorretos!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceder | SIOV Vilcon</title>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body, html {
            height: 100%;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overflow: hidden;
        }

        .login-screen {
            background:
                linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),
                url('/vilcon-systemon/public/assets/img/vilcon-truck.jpg');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10%;
        }

        .welcome-side {
            color: white;
            max-width: 500px;
        }

        .welcome-side h1 {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.1;
        }

        .welcome-side p {
            font-size: 18px;
            line-height: 1.5;
            opacity: 0.9;
        }

        .login-box {
            width: 380px;
            color: white;
        }

        .logo-container img {
            width: 380px;
            margin-bottom: 15px;
        }

        .login-box h2 {
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
            color: #eee;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border: none;
            background: white;
            border-radius: 4px;
            font-size: 16px;
            color: #333;
        }
        .password-wrap {
            position: relative;
        }
        .password-wrap input {
            padding-right: 46px;
        }
        .toggle-password {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 6px;
            background: #f1f5f9;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .toggle-password:hover {
            background: #e2e8f0;
        }

        .btn-submit {
            background: #e67e22;
            color: white;
            border: none;
            padding: 15px 45px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #d35400;
            transform: scale(1.02);
        }

        .error-msg {
            background: #c0392b;
            color: white;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/global-loader.css">
</head>
<body>

<div id="vilcon-global-loader" class="vilcon-loader-overlay" aria-live="polite" aria-busy="true" aria-label="A processar">
    <div class="vilcon-loader-spinner" role="status" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</div>

<div class="login-screen">

    <div class="welcome-side">
        <h1>Bem-vindo ao Sistema Integrado da Vilcon!</h1>
        <p>
            Gestao completa de frota, oficina e logistica
            com controlo total e rastreabilidade.
        </p>
    </div>

    <div class="login-box">
        <div class="logo-container">
            <img src="/vilcon-systemon/public/assets/img/logo-vilcon.png" alt="Vilcon Logo">
        </div>

        <h2>ACEDER</h2>

        <?php if (isset($erro)): ?>
            <div class="error-msg"><?= $erro ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Ex: VMichael" required value="<?= htmlspecialchars((string)($usernameInput ?? '')) ?>">
            </div>

            <div class="input-group">
                <label>Palavra-passe</label>
                <div class="password-wrap">
                    <input id="senha-input" type="password" name="senha" placeholder="********" required>
                    <button type="button" class="toggle-password" id="toggle-password" aria-label="Mostrar senha" aria-pressed="false">
                        <span id="toggle-password-icon">👁</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Entrar agora
            </button>
        </form>
    </div>

</div>

    <script>
        (function () {
            var senhaInput = document.getElementById('senha-input');
            var toggleBtn = document.getElementById('toggle-password');
            var icon = document.getElementById('toggle-password-icon');
            if (!senhaInput || !toggleBtn || !icon) return;

            toggleBtn.addEventListener('click', function () {
                var isPassword = senhaInput.type === 'password';
                senhaInput.type = isPassword ? 'text' : 'password';
                toggleBtn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', isPassword ? 'Ocultar senha' : 'Mostrar senha');
                icon.textContent = isPassword ? '🙈' : '👁';
            });
        })();
    </script>
    <script src="/vilcon-systemon/public/assets/js/global-loader.js"></script>
</body>
</html>

