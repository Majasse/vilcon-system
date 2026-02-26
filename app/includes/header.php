<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/mojibake_fix.php';
vilcon_bootstrap_mojibake_fix();
require_once __DIR__ . '/user_profile_widget.php';
require_once dirname(__DIR__) . '/core/access_control.php';

if (isset($_SESSION['usuario_perfil'])) {
    garantirAcessoModuloAtual((string)($_SERVER['SCRIPT_NAME'] ?? ''), (string)$_SESSION['usuario_perfil']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Vilcon System'; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/vilcon-bias-theme.css">
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/global-loader.css">

    <style>
        :root {
            --bg-dark: #f3f4f6;
            --sidebar-bg: #ffffff;
            --accent-orange: #e67e22;
            --text-main: #111827;
            --text-dim: #6b7280;
            --card-bg: #ffffff;
            --sidebar-hover: #f3f4f6;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .top-bar {
            height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            background: var(--sidebar-bg);
            border-bottom: 1px solid #e5e7eb;
        }

        .user-info { font-size: 14px; color: var(--text-dim); }
        .user-info strong { color: var(--accent-orange); }

        .dashboard-container { padding: 40px; }
    </style>
</head>
<body>
<div id="vilcon-global-loader" class="vilcon-loader-overlay" aria-live="polite" aria-busy="true" aria-label="A processar">
    <div class="vilcon-loader-spinner" role="status" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</div>


