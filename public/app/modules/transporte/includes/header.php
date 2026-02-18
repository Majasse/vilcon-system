<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 3) . '/includes/user_profile_widget.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIOV | Vilcon Operations</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/vilcon-bias-theme.css">
    <style>
        :root {
            --vilcon-black: #1a1a1a;
            --vilcon-orange: #f39c12;
            --bg-white: #f4f7f6;
            --border: #e1e8ed;
            --danger: #e74c3c;
            --success: #27ae60;
            --info: #3498db;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background: var(--vilcon-black); overflow: hidden; }
        .sidebar { width: 260px; background: var(--vilcon-black); flex-shrink: 0; border-right: 1px solid #333; }
        .sidebar-logo { padding: 25px; text-align: center; border-bottom: 1px solid #333; }
        .main-content { flex: 1; background: var(--bg-white); display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        .header-section { padding: 20px 40px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
        .tab-menu { display: flex; gap: 8px; }
        .tab-btn { padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid #ddd; color: #666; text-transform: uppercase; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .tab-btn.active { background: var(--vilcon-orange); color: #fff; border-color: var(--vilcon-orange); }
        .sub-tab-container { background: #eee; padding: 8px; border-radius: 8px; margin: 20px 40px 10px 40px; display: flex; gap: 5px; flex-wrap: wrap; }
        .sub-tab-btn { padding: 8px 18px; border-radius: 5px; text-decoration: none; font-weight: 700; font-size: 10px; color: #555; text-transform: uppercase; transition: 0.2s; }
        .sub-tab-btn.active { background: #fff; color: var(--vilcon-black); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .inner-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #ddd; }
        .mode-selector { display: flex; gap: 10px; }
        .btn-mode { padding: 8px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; text-decoration: none; text-transform: uppercase; border: 1px solid #ddd; color: #666; background: #fff; }
        .btn-mode.active { background: var(--vilcon-black); color: #fff; border-color: var(--vilcon-black); }
        .container { padding: 10px 40px 40px 40px; }
        .white-card { background: #fff; border-radius: 12px; padding: 30px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .section-title { grid-column: span 4; background: #f8f9fa; padding: 12px; font-size: 11px; font-weight: 800; border-left: 5px solid var(--vilcon-orange); margin: 15px 0 5px 0; text-transform: uppercase; }
        .form-group { display: flex; flex-direction: column; }
        label { font-size: 10px; font-weight: 800; color: #444; margin-bottom: 5px; text-transform: uppercase; }
        input, select, textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 13px; outline: none; }
        .btn-save { padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 11px; border:none; color:white; }
        .history-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .history-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid var(--border); text-transform: uppercase; color: #777; font-size: 10px; }
        .history-table td { padding: 12px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
<?php renderUserProfileWidget(); ?>
