<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 3) . '/includes/user_profile_widget.php';
require_once dirname(__DIR__, 3) . '/core/access_control.php';

if (isset($_SESSION['usuario_perfil'])) {
    garantirAcessoModuloAtual((string)($_SERVER['SCRIPT_NAME'] ?? ''), (string)$_SESSION['usuario_perfil']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIOV | MÃ³dulo Oficina</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/vilcon-bias-theme.css">
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/global-loader.css">

    <style>
        :root {
            --vilcon-black:#1a1a1a;
            --vilcon-orange:#f39c12;
            --bg-white:#f4f7f6;
            --border:#e1e8ed;
            --danger:#e74c3c;
            --success:#27ae60;
            --info:#3498db;
            --text-dark:#1f2937;
            --text-muted:#6b7280;
            --card-bg:#ffffff;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family: Inter, sans-serif; }
        body { display:flex; height:100vh; background:var(--bg-white); color:var(--text-dark); overflow:hidden; }

        /* ===== MAIN ===== */
        .main-content { flex:1; background:var(--bg-white); display:flex; flex-direction:column; overflow-y:auto; }
        .header-section { padding:20px 40px; background:#ffffff; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:2; }
        .tab-menu { display:flex; gap:10px; }
        .tab-btn { padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:700; font-size:11px; border:1px solid #ddd; color:#666; text-transform:uppercase; }
        .tab-btn.active { background:var(--vilcon-orange); color:#fff; border-color:var(--vilcon-orange); }
        .sub-tab-container { background:#f3f4f6; padding:8px; border-radius:8px; margin:20px 40px; display:flex; gap:6px; flex-wrap:wrap; }
        .sub-tab-btn { padding:8px 15px; border-radius:5px; text-decoration:none; font-size:10px; font-weight:700; color:#374151; text-transform:uppercase; }
        .sub-tab-btn.active { background:#ffffff; color:#111827; box-shadow:0 2px 4px rgba(0,0,0,.08); }
        .container { padding:10px 40px 40px; }
        .white-card { background:var(--card-bg); border-radius:12px; padding:25px; border:1px solid var(--border); box-shadow:0 6px 16px rgba(17,24,39,.06); }
        .inner-nav { display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:1px dashed #e5e7eb; padding-bottom:10px; }
        .list-tools { display:flex; align-items:center; gap:8px; }
        .search-group { display:flex; align-items:center; gap:6px; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; padding:6px 10px; }
        .search-group i { color:#9ca3af; font-size:12px; }
        .search-input { border:none; outline:none; font-size:12px; padding:2px 4px; background:transparent; min-width:180px; }
        .filter-select { border:1px solid #e5e7eb; border-radius:20px; padding:6px 10px; font-size:12px; background:#ffffff; color:#111827; }
        .btn-mode { padding:6px 14px; border-radius:20px; font-size:11px; border:1px solid #e5e7eb; text-decoration:none; color:#6b7280; font-weight:700; background:#ffffff; }
        .btn-mode.active { background:#111827; color:#ffffff; border-color:#111827; }
        .form-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; }
        .section-title { grid-column:span 4; background:#f8f9fa; padding:10px; font-size:11px; font-weight:800; border-left:5px solid var(--vilcon-orange); margin-top:10px; text-transform:uppercase; }
        .form-group { display:flex; flex-direction:column; }
        label { font-size:10px; font-weight:800; color:#444; margin-bottom:4px; text-transform:uppercase; }
        input, select, textarea { padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; background:#ffffff; color:#111827; }
        .btn-save { padding:12px; border-radius:6px; font-weight:700; font-size:11px; border:none; color:#fff; cursor:pointer; text-transform:uppercase; }

        .table { width:100%; border-collapse:separate; border-spacing:0; font-size:12px; }
        .table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; padding:12px 10px; border-bottom:1px solid #e5e7eb; background:#f8fafc; }
        .table td { padding:12px 10px; border-bottom:1px solid #f1f5f9; color:#111827; }
        .table tr:hover { background:#f8fafc; }
        .pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
        .pill.warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
        .pill.ok { background:#ecfdf3; color:#16a34a; border:1px solid #bbf7d0; }
    </style>
</head>

<body>
<?php renderUserProfileWidget(); ?>
<div id="vilcon-global-loader" class="vilcon-loader-overlay" aria-live="polite" aria-busy="true" aria-label="A processar">
    <div class="vilcon-loader-spinner" role="status" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</div>


