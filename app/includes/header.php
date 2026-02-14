<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

    <style>
        :root {
            --bg-dark: #121212;
            --sidebar-bg: #1e1e1e;
            --accent-orange: #e67e22;
            --text-main: #ffffff;
            --text-dim: #b3b3b3;
            --card-bg: #252525;
            --sidebar-hover: #2c2c2c;
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
            border-bottom: 1px solid #333;
        }

        .user-info { font-size: 14px; color: var(--text-dim); }
        .user-info strong { color: var(--accent-orange); }

        .dashboard-container { padding: 40px; }
    </style>
</head>
<body>
