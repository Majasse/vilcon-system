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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');

    $sql = "SELECT * FROM usuarios
            WHERE email = :email
            AND senha = :senha
            AND status = 'Ativo'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
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
            registrarAcaoSistema($pdo, 'LOGIN FALHOU: ' . $email, 'auth', null);
        } catch (Throwable $e) {
            // Nao bloquear fluxo em falha de auditoria.
        }
        $erro = 'E-mail ou palavra-passe incorretos!';
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
</head>
<body>

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
                <label>E-mail</label>
                <input type="email" name="email" placeholder="nome@vilcon.com" required>
            </div>

            <div class="input-group">
                <label>Palavra-passe</label>
                <input type="password" name="senha" placeholder="********" required>
            </div>

            <button type="submit" class="btn-submit">
                Entrar agora
            </button>
        </form>
    </div>

</div>

</body>
</html>
