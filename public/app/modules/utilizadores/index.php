<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/core/access_control.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: /vilcon-systemon/public/login.php'); exit; }

$page_title = 'Utilizadores | Vilcon System';
$erro = null; $msg = null; $utilizadores = [];
$q = trim((string)($_GET['q'] ?? ''));
$perfilFiltro = trim((string)($_GET['perfil'] ?? ''));
$statusFiltro = trim((string)($_GET['status'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'nome'));
$dir = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$perfilSessao = (string)($_SESSION['usuario_perfil'] ?? '');
$podeGerirUtilizadores = usuarioPodeAcederModulo($perfilSessao, 'utilizadores');

function v(array $row, array $keys, string $d=''): string { foreach ($keys as $k) { if (isset($row[$k]) && $row[$k] !== '') return (string)$row[$k]; } return $d; }
function baseUser(string $nome): string { $p = preg_split('/\s+/', trim($nome)) ?: []; $f = preg_replace('/[^a-z0-9]/i','', $p[0] ?? 'User') ?: 'User'; return 'V'.$f; }
function stClass(string $s): string { $s = strtolower(trim($s)); if ($s==='ativo'||$s==='1') return 'st-ativo'; if ($s==='suspenso') return 'st-suspenso'; return 'st-bloq'; }
function stLabel(string $s): string { $s = strtolower(trim($s)); if ($s==='ativo'||$s==='1') return 'Ativo'; if ($s==='suspenso') return 'Suspenso'; return 'Bloqueado'; }
function pfClass(string $p): string { $p = strtolower(trim($p)); if (strpos($p,'admin')!==false) return 'pf-admin'; if (strpos($p,'rh')!==false||strpos($p,'recursos')!==false) return 'pf-rh'; if (strpos($p,'log')!==false) return 'pf-log'; if (strpos($p,'transporte')!==false) return 'pf-tran'; return 'pf-ofi'; }
function avatar(string $n): string { $a = preg_split('/\s+/', trim($n)) ?: []; return strtoupper(substr($a[0] ?? 'U',0,1) . substr($a[1] ?? '',0,1)); }
function humanLogin(string $v): string { $v=trim($v); if($v==='') return '-'; $t=strtotime($v); if($t===false) return $v; $d=date('Y-m-d',$t); if($d===date('Y-m-d')) return 'Hoje, '.date('H:i',$t); if($d===date('Y-m-d',strtotime('-1 day'))) return 'Ontem, '.date('H:i',$t); return date('d/m/Y H:i',$t); }

try {
  if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['acao_admin'])) {
    if (!$podeGerirUtilizadores) throw new RuntimeException('Apenas administradores podem gerir contas.');
    $acao = trim((string)($_POST['acao_admin'] ?? '')); $id = (int)($_POST['usuario_id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('Utilizador invalido.');

    if ($acao === 'toggle_status') {
      if ($id === (int)($_SESSION['usuario_id'] ?? 0)) throw new RuntimeException('Nao pode bloquear a sua propria conta.');
      $st = $pdo->prepare('SELECT status FROM usuarios WHERE id=:id LIMIT 1'); $st->execute([':id'=>$id]);
      $atual = (string)($st->fetchColumn() ?: ''); if ($atual === '') throw new RuntimeException('Utilizador nao encontrado.');
      $novo = strtolower($atual)==='ativo' ? 'Inativo' : 'Ativo';
      $up = $pdo->prepare('UPDATE usuarios SET status=:s WHERE id=:id'); $up->execute([':s'=>$novo, ':id'=>$id]);
      $msg = 'Estado atualizado para '.$novo.'.';
    }

    if ($acao === 'alterar_senha') {
      $senha = (string)($_POST['nova_senha'] ?? ''); if (strlen($senha) < 6) throw new RuntimeException('Senha minima: 6 caracteres.');
      $up = $pdo->prepare('UPDATE usuarios SET senha=:s WHERE id=:id'); $up->execute([':s'=>$senha, ':id'=>$id]);
      $msg = 'Senha atualizada com sucesso.';
    }

    if ($acao === 'remover_usuario') {
      if ($id === (int)($_SESSION['usuario_id'] ?? 0)) throw new RuntimeException('Nao pode remover a sua propria conta.');
      $del = $pdo->prepare('DELETE FROM usuarios WHERE id=:id'); $del->execute([':id'=>$id]);
      $msg = 'Utilizador removido com sucesso.';
    }

    if ($acao === 'criar_usuario') {
      $nomeNovo = trim((string)($_POST['nome'] ?? ''));
      $emailNovo = trim((string)($_POST['email'] ?? ''));
      $perfilNovo = trim((string)($_POST['perfil'] ?? 'Comum'));
      $statusNovo = trim((string)($_POST['status'] ?? 'Ativo'));
      $senhaNova = (string)($_POST['nova_senha'] ?? '');
      $usernameNovo = trim((string)($_POST['username'] ?? ''));

      if ($nomeNovo === '' || $emailNovo === '' || $senhaNova === '') {
        throw new RuntimeException('Preencha nome, email e senha.');
      }
      if (strlen($senhaNova) < 6) {
        throw new RuntimeException('Senha minima: 6 caracteres.');
      }
      if ($usernameNovo === '') {
        $base = baseUser($nomeNovo);
        $usernameNovo = $base;
        $n = 1;
        while (true) {
          $chk = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username=:u");
          $chk->execute([':u' => $usernameNovo]);
          if ((int)$chk->fetchColumn() === 0) break;
          $n++;
          $usernameNovo = $base . $n;
        }
      }

      $chkMail = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email=:e");
      $chkMail->execute([':e' => $emailNovo]);
      if ((int)$chkMail->fetchColumn() > 0) throw new RuntimeException('Ja existe utilizador com este email.');

      $chkUser = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username=:u");
      $chkUser->execute([':u' => $usernameNovo]);
      if ((int)$chkUser->fetchColumn() > 0) throw new RuntimeException('Ja existe utilizador com este username.');

      $ins = $pdo->prepare("INSERT INTO usuarios (nome, email, username, senha, perfil, status) VALUES (:n,:e,:u,:s,:p,:st)");
      $ins->execute([
        ':n' => $nomeNovo,
        ':e' => $emailNovo,
        ':u' => $usernameNovo,
        ':s' => $senhaNova,
        ':p' => $perfilNovo,
        ':st' => $statusNovo,
      ]);
      $msg = 'Novo utilizador criado com sucesso.';
    }
  }

  if (!$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'username'")->fetch(PDO::FETCH_ASSOC)) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN username VARCHAR(80) NULL AFTER nome");
  }
  if (!$pdo->query("SHOW INDEX FROM usuarios WHERE Key_name='idx_usuarios_username'")->fetch(PDO::FETCH_ASSOC)) {
    $pdo->exec("CREATE UNIQUE INDEX idx_usuarios_username ON usuarios (username)");
  }
  $sem = $pdo->query("SELECT id,nome FROM usuarios WHERE username IS NULL OR username=''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $upd = $pdo->prepare("UPDATE usuarios SET username=:u WHERE id=:id");
  foreach ($sem as $u) {
    $base = baseUser((string)$u['nome']); $cand = $base; $n=1;
    while (true) { $chk=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username=:u AND id<>:id"); $chk->execute([':u'=>$cand,':id'=>(int)$u['id']]); if ((int)$chk->fetchColumn()===0) break; $n++; $cand=$base.$n; }
    $upd->execute([':u'=>$cand, ':id'=>(int)$u['id']]);
  }

  $hasLastLogin = (bool)$pdo->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_login'")->fetch(PDO::FETCH_ASSOC);
  $selectLast = $hasLastLogin
    ? "COALESCE(u.ultimo_login,(SELECT MAX(a1.data_hora) FROM auditoria a1 WHERE a1.usuario_id=u.id AND a1.acao LIKE 'LOGIN:%'))"
    : "(SELECT MAX(a1.data_hora) FROM auditoria a1 WHERE a1.usuario_id=u.id AND a1.acao LIKE 'LOGIN:%')";

  $where = []; $params = [];
  if ($q !== '') { $where[] = '(u.nome LIKE :q OR u.username LIKE :q OR u.email LIKE :q OR u.perfil LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
  if ($perfilFiltro !== '') { $where[] = 'u.perfil=:pf'; $params[':pf'] = $perfilFiltro; }
  if ($statusFiltro === 'ativo') $where[] = "LOWER(COALESCE(u.status,''))='ativo'";
  if ($statusFiltro === 'bloqueado') $where[] = "LOWER(COALESCE(u.status,'')) IN ('inativo','bloqueado','0')";
  if ($statusFiltro === 'suspenso') $where[] = "LOWER(COALESCE(u.status,''))='suspenso'";
  $whereSql = $where ? (' WHERE '.implode(' AND ', $where)) : '';

  $map = ['nome'=>'u.nome','username'=>'u.username','perfil'=>'u.perfil','status'=>'u.status','ultimo_login'=>'ultimo_login'];
  if (!isset($map[$sort])) $sort='nome';
  $orderBy = $map[$sort] . ' ' . strtoupper($dir);

  $cnt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u $whereSql"); foreach ($params as $k=>$v) $cnt->bindValue($k,$v,PDO::PARAM_STR); $cnt->execute();
  $totalRows = (int)$cnt->fetchColumn(); $totalPages = max(1, (int)ceil($totalRows / $perPage)); if ($page > $totalPages) $page = $totalPages; $offset = ($page-1)*$perPage;

  $sql = "SELECT u.*, $selectLast AS ultimo_login FROM usuarios u $whereSql ORDER BY $orderBy LIMIT :l OFFSET :o";
  $st = $pdo->prepare($sql); foreach ($params as $k=>$v) $st->bindValue($k,$v,PDO::PARAM_STR); $st->bindValue(':l',$perPage,PDO::PARAM_INT); $st->bindValue(':o',$offset,PDO::PARAM_INT); $st->execute();
  $utilizadores = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $stats = $pdo->query('SELECT status, perfil FROM usuarios')->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $totalAtivos=0; $totalBloqueados=0; $totalAdministradores=0;
  foreach ($stats as $s) { $stt=strtolower(trim((string)$s['status'])); $pf=strtolower(trim((string)$s['perfil'])); if($stt==='ativo'||$stt==='1')$totalAtivos++; if($stt==='inativo'||$stt==='bloqueado'||$stt==='0')$totalBloqueados++; if(strpos($pf,'admin')!==false)$totalAdministradores++; }

  $perfilOptions = $pdo->query("SELECT DISTINCT perfil FROM usuarios WHERE perfil IS NOT NULL AND perfil<>'' ORDER BY perfil")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $erro = 'Nao foi possivel carregar os utilizadores e a auditoria.';
  $totalRows = 0; $totalPages = 1; $totalAtivos = 0; $totalBloqueados = 0; $totalAdministradores = 0; $perfilOptions = [];
}

$queryBase = ['q'=>$q,'perfil'=>$perfilFiltro,'status'=>$statusFiltro,'sort'=>$sort,'dir'=>$dir];
$sortLink = static function(string $f) use ($queryBase,$sort,$dir): string { $nd = ($sort===$f && $dir==='asc') ? 'desc' : 'asc'; return '?' . http_build_query(array_merge($queryBase,['sort'=>$f,'dir'=>$nd,'page'=>1])); };
$pageLink = static function(int $p) use ($queryBase): string { return '?' . http_build_query(array_merge($queryBase,['page'=>$p])); };
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

  <div class="dashboard-container users-admin-page">
    <style>
      html,body{overflow-y:hidden}
      .users-admin-page{--bg:#f8f9fb;--surface:#fff;--line:#e2e8f0;--ink:#0f172a;--muted:#64748b;--primary:#0d3b88;--shadow:0 14px 30px rgba(15,23,42,.08);background:var(--bg);border:1px solid #edf2f7;border-radius:16px;padding:20px}
      .users-top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px}
      .users-title{margin:0;font-size:30px;line-height:1.1;color:var(--ink);letter-spacing:-.02em}.users-subtitle{margin:6px 0 0;color:var(--muted);font-size:13px}
      .users-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.search-wrap{position:relative}.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px}
      .search-input{min-height:40px;width:270px;max-width:100%;border:1px solid #d6deea;border-radius:10px;padding:0 12px 0 34px;font-size:13px;background:#fff}.search-input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
      .btn-primary,.btn-outline{min-height:40px;border-radius:10px;padding:0 14px;font-size:13px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px}
      .btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}.btn-primary:hover{background:#0a2f6e;border-color:#0a2f6e}.btn-outline{background:#fff;border-color:#d6deea;color:#334155}
      .avatar-chip{width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;font-weight:800;font-size:12px}
      .result-badge{display:inline-flex;align-items:center;gap:6px;min-height:28px;border-radius:999px;padding:0 12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;font-weight:700;margin-bottom:14px}
      .summary-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:12px;margin-bottom:14px}.summary-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:14px}
      .summary-label{margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;font-weight:700;color:var(--muted)}.summary-value{margin:0;font-size:32px;line-height:1;font-weight:800;color:var(--ink)}
      .summary-card.ativos{border-left:4px solid #16a34a}.summary-card.bloq{border-left:4px solid #dc2626}.summary-card.admins{border-left:4px solid #2563eb}
      .filters-panel{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:12px;margin-bottom:14px}.filters-form{display:flex;align-items:end;gap:10px;flex-wrap:wrap}
      .field{display:flex;flex-direction:column;gap:6px;min-width:180px}.field label{font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;letter-spacing:.04em}
      .field select{min-height:38px;border:1px solid #d6deea;border-radius:10px;padding:0 12px;font-size:13px}.field select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
      .table-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}.table-wrap{overflow-x:auto}
      .users-table{width:100%;min-width:980px;border-collapse:collapse}.users-table th,.users-table td{padding:12px 14px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:middle;color:#1e293b;font-size:13px}
      .users-table th a{color:#64748b;text-decoration:none;font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:700}.users-table th a:hover{color:#1d4ed8}
      .name-cell{display:flex;align-items:center;gap:10px}.row-avatar{width:34px;height:34px;border-radius:10px;background:#0f172a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
      .row-name{font-weight:700;color:#0f172a;margin-bottom:2px}.row-email{font-size:12px;color:#64748b}
      .tag{display:inline-flex;min-height:24px;align-items:center;border-radius:999px;padding:0 10px;font-size:11px;font-weight:700;border:1px solid transparent}
      .pf-admin{color:#1d4ed8;background:#eff6ff;border-color:#bfdbfe}.pf-rh{color:#7c3aed;background:#f5f3ff;border-color:#ddd6fe}.pf-log{color:#c2410c;background:#fff7ed;border-color:#fed7aa}.pf-ofi{color:#475569;background:#f8fafc;border-color:#e2e8f0}.pf-tran{color:#0f766e;background:#f0fdfa;border-color:#99f6e4}
      .st-ativo{color:#166534;background:#f0fdf4;border-color:#bbf7d0}.st-bloq{color:#991b1b;background:#fef2f2;border-color:#fecaca}.st-suspenso{color:#92400e;background:#fffbeb;border-color:#fde68a}
      .actions-menu-wrap{position:relative;display:inline-block}.menu-trigger{width:32px;height:32px;border-radius:8px;border:1px solid #d6deea;background:#fff;color:#334155;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
      .menu-panel{position:absolute;top:36px;right:0;min-width:190px;border:1px solid #d6deea;background:#fff;border-radius:10px;box-shadow:0 14px 30px rgba(15,23,42,.16);padding:6px;display:none;z-index:30}.menu-panel.open{display:block}
      .menu-item,.menu-link{width:100%;min-height:34px;border:none;background:#fff;border-radius:8px;color:#334155;font-size:12px;font-weight:600;padding:0 10px;display:flex;align-items:center;gap:8px;text-decoration:none;cursor:pointer;text-align:left}
      .menu-item:hover,.menu-link:hover{background:#f8fafc}.menu-item.danger{color:#991b1b}
      .empty-state{text-align:center;padding:36px 16px;color:#64748b}.empty-state i{font-size:34px;margin-bottom:10px;color:#94a3b8}
      .pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 14px;background:#f8fafc;border-top:1px solid #edf2f7}.pages{display:flex;gap:6px;flex-wrap:wrap}
      .page-link{min-width:34px;height:34px;border-radius:8px;border:1px solid #d6deea;color:#334155;background:#fff;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.page-link.current{background:#0d3b88;border-color:#0d3b88;color:#fff}
      .flash-ok,.flash-er{border-radius:10px;padding:10px 12px;font-size:13px;font-weight:700;margin-bottom:10px;border:1px solid transparent}.flash-ok{color:#166534;background:#f0fdf4;border-color:#bbf7d0}.flash-er{color:#991b1b;background:#fef2f2;border-color:#fecaca}
      .skeleton{display:none;border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff;margin-bottom:14px}.skeleton.visible{display:block}.sk-line{height:12px;border-radius:999px;margin-bottom:10px;background:linear-gradient(90deg,#e2e8f0 0,#f1f5f9 50%,#e2e8f0 100%);background-size:300px 100%;animation:shimmer 1.1s infinite linear}
      @keyframes shimmer{from{background-position:-300px 0}to{background-position:300px 0}}
      .modal{position:fixed;inset:0;z-index:1200;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(2,6,23,.5)}.modal.open{display:flex}
      .modal-card{width:min(460px,100%);background:#fff;border-radius:14px;border:1px solid #d6deea;box-shadow:0 18px 36px rgba(15,23,42,.22);overflow:hidden}.modal-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e2e8f0}
      .modal-head h4{margin:0;font-size:16px;color:#0f172a}.modal-close{width:30px;height:30px;border-radius:8px;border:1px solid #d6deea;background:#fff;color:#334155;cursor:pointer}
      .modal-body{padding:14px}.modal-body p{margin:0 0 10px;color:#475569;font-size:13px}.modal-input{width:100%;min-height:40px;border:1px solid #d6deea;border-radius:10px;padding:0 12px;font-size:13px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
      @media (max-width:1000px){.summary-grid{grid-template-columns:1fr}}@media (max-width:860px){.users-admin-page{padding:14px}.users-top{flex-direction:column}.users-actions,.search-wrap,.search-input{width:100%}}
    </style>

    <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="flash-er"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="users-top">
      <div>
        <h2 class="users-title">Gestao de Utilizadores</h2>
        <p class="users-subtitle">Administracao e controlo de acessos do sistema</p>
      </div>
      <div class="users-actions">
        <form method="get" class="search-wrap" id="searchForm">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="search-input" placeholder="Pesquisar utilizador">
          <input type="hidden" name="perfil" value="<?= htmlspecialchars($perfilFiltro) ?>">
          <input type="hidden" name="status" value="<?= htmlspecialchars($statusFiltro) ?>">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
        </form>
        <?php if ($podeGerirUtilizadores): ?>
          <button type="button" class="btn-primary" id="btnNovoUtilizador"><i class="fa-solid fa-plus"></i> Novo Utilizador</button>
        <?php endif; ?>
        <span class="avatar-chip"><?= htmlspecialchars(avatar((string)($_SESSION['usuario_nome'] ?? 'Michael'))) ?></span>
      </div>
    </div>

    <div class="result-badge"><i class="fa-solid fa-circle"></i> <?= (int)$totalRows ?> Utilizadores</div>

    <section class="summary-grid">
      <article class="summary-card ativos"><p class="summary-label">Ativos</p><p class="summary-value"><?= (int)$totalAtivos ?></p></article>
      <article class="summary-card bloq"><p class="summary-label">Bloqueados</p><p class="summary-value"><?= (int)$totalBloqueados ?></p></article>
      <article class="summary-card admins"><p class="summary-label">Administradores</p><p class="summary-value"><?= (int)$totalAdministradores ?></p></article>
    </section>

    <section class="filters-panel">
      <form method="get" class="filters-form" id="filtersForm">
        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
        <div class="field"><label>Perfil</label><select name="perfil"><option value="">Todos</option><?php foreach ($perfilOptions as $pf): ?><option value="<?= htmlspecialchars((string)$pf) ?>" <?= $perfilFiltro === (string)$pf ? 'selected' : '' ?>><?= htmlspecialchars((string)$pf) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Status</label><select name="status"><option value="" <?= $statusFiltro === '' ? 'selected' : '' ?>>Todos</option><option value="ativo" <?= $statusFiltro === 'ativo' ? 'selected' : '' ?>>Ativo</option><option value="bloqueado" <?= $statusFiltro === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option><option value="suspenso" <?= $statusFiltro === 'suspenso' ? 'selected' : '' ?>>Suspenso</option></select></div>
        <div style="display:flex;gap:8px;"><button type="submit" class="btn-primary">Aplicar</button><a href="index.php" class="btn-outline">Limpar</a></div>
      </form>
    </section>

    <div class="skeleton" id="searchSkeleton"><div class="sk-line" style="width:96%;"></div><div class="sk-line" style="width:88%;"></div><div class="sk-line" style="width:80%;margin-bottom:0;"></div></div>

    <section class="table-card" id="usersTableCard">
      <?php if (!$erro && count($utilizadores) === 0): ?>
        <div class="empty-state"><i class="fa-regular fa-folder-open"></i><div>Nenhum utilizador encontrado.</div></div>
      <?php elseif (!$erro): ?>
        <div class="table-wrap">
          <table class="users-table">
            <thead>
              <tr>
                <th><a href="<?= htmlspecialchars($sortLink('nome')) ?>">Avatar + Nome</a></th>
                <th><a href="<?= htmlspecialchars($sortLink('username')) ?>">Username</a></th>
                <th><a href="<?= htmlspecialchars($sortLink('perfil')) ?>">Perfil</a></th>
                <th><a href="<?= htmlspecialchars($sortLink('status')) ?>">Status</a></th>
                <th><a href="<?= htmlspecialchars($sortLink('ultimo_login')) ?>">Ultimo Login</a></th>
                <th> </th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($utilizadores as $u): ?>
                <?php
                  $id = (int)v($u, ['id','usuario_id'], '0');
                  $nome = v($u, ['nome','nome_completo','usuario','username'], '-');
                  $username = v($u, ['username'], '-');
                  $perfil = v($u, ['perfil','cargo','role'], '-');
                  $status = v($u, ['status','ativo'], 'Inativo');
                  $email = v($u, ['email'], '-');
                  $statusView = stLabel($status); $statusClass = stClass($status); $perfilClass = pfClass($perfil);
                ?>
                <tr>
                  <td><div class="name-cell"><span class="row-avatar"><?= htmlspecialchars(avatar($nome)) ?></span><div><div class="row-name"><?= htmlspecialchars($nome) ?></div><div class="row-email"><?= htmlspecialchars($email) ?></div></div></div></td>
                  <td><strong><?= htmlspecialchars($username) ?></strong></td>
                  <td><span class="tag <?= htmlspecialchars($perfilClass) ?>"><?= htmlspecialchars($perfil) ?></span></td>
                  <td><span class="tag <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusView) ?></span></td>
                  <td><?= htmlspecialchars(humanLogin(v($u,['ultimo_login']))) ?></td>
                  <td>
                    <div class="actions-menu-wrap">
                      <button class="menu-trigger" type="button" data-menu-toggle="<?= $id ?>" title="Acoes"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                      <div class="menu-panel" data-menu-panel="<?= $id ?>">
                        <a class="menu-link" href="acoes.php?usuario_id=<?= $id ?>"><i class="fa-regular fa-eye"></i> Ver Detalhes</a>
                        <?php if ($podeGerirUtilizadores): ?>
                          <button class="menu-item" type="button" data-open-toggle data-id="<?= $id ?>" data-nome="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>" data-status="<?= htmlspecialchars($statusView, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-lock"></i><?= strtolower($statusView)==='ativo' ? 'Bloquear' : 'Desbloquear' ?></button>
                          <button class="menu-item" type="button" data-open-pass data-id="<?= $id ?>" data-nome="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-key"></i> Redefinir Senha</button>
                          <button class="menu-item danger" type="button" data-open-remove data-id="<?= $id ?>" data-nome="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-trash-can"></i> Remover</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="pagination">
          <span>Pagina <?= (int)$page ?> de <?= (int)$totalPages ?></span>
          <div class="pages">
            <?php if ($page > 1): ?><a class="page-link" href="<?= htmlspecialchars($pageLink($page - 1)) ?>">&lt;</a><?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="page-link <?= $i === $page ? 'current' : '' ?>" href="<?= htmlspecialchars($pageLink($i)) ?>"><?= $i ?></a><?php endfor; ?>
            <?php if ($page < $totalPages): ?><a class="page-link" href="<?= htmlspecialchars($pageLink($page + 1)) ?>">&gt;</a><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php if ($podeGerirUtilizadores): ?>
<div class="modal" id="modalNovo" aria-hidden="true"><div class="modal-card"><div class="modal-head"><h4>Novo Utilizador</h4><button type="button" class="modal-close" data-close-modal="modalNovo">&times;</button></div><form method="post" class="modal-body"><input type="hidden" name="acao_admin" value="criar_usuario"><p>Registar novo utilizador no sistema.</p><input class="modal-input" type="text" name="nome" required placeholder="Nome completo" style="margin-bottom:8px;"><input class="modal-input" type="email" name="email" required placeholder="Email" style="margin-bottom:8px;"><input class="modal-input" type="text" name="username" placeholder="Username (opcional)" style="margin-bottom:8px;"><input class="modal-input" type="text" name="nova_senha" minlength="6" required placeholder="Senha inicial" style="margin-bottom:8px;"><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;"><select class="modal-input" name="perfil"><option>Administrador</option><option>RH</option><option>Logistica</option><option>Oficina</option><option>Transporte</option><option>Comum</option></select><select class="modal-input" name="status"><option>Ativo</option><option>Inativo</option><option>Suspenso</option></select></div><div class="modal-actions"><button type="button" class="btn-outline" data-close-modal="modalNovo">Cancelar</button><button type="submit" class="btn-primary">Criar</button></div></form></div></div>
<div class="modal" id="modalSenha" aria-hidden="true"><div class="modal-card"><div class="modal-head"><h4>Redefinir Senha</h4><button type="button" class="modal-close" data-close-modal="modalSenha">&times;</button></div><form method="post" class="modal-body"><input type="hidden" name="acao_admin" value="alterar_senha"><input type="hidden" name="usuario_id" id="senhaUsuarioId" value=""><p id="senhaTexto">Defina a nova senha.</p><input class="modal-input" type="text" name="nova_senha" minlength="6" required placeholder="Nova senha"><div class="modal-actions"><button type="button" class="btn-outline" data-close-modal="modalSenha">Cancelar</button><button type="submit" class="btn-primary">Guardar</button></div></form></div></div>
<div class="modal" id="modalStatus" aria-hidden="true"><div class="modal-card"><div class="modal-head"><h4>Confirmar Alteracao</h4><button type="button" class="modal-close" data-close-modal="modalStatus">&times;</button></div><form method="post" class="modal-body"><input type="hidden" name="acao_admin" value="toggle_status"><input type="hidden" name="usuario_id" id="statusUsuarioId" value=""><p id="statusTexto">Deseja alterar o estado deste utilizador?</p><div class="modal-actions"><button type="button" class="btn-outline" data-close-modal="modalStatus">Cancelar</button><button type="submit" class="btn-primary">Confirmar</button></div></form></div></div>
<div class="modal" id="modalRemove" aria-hidden="true"><div class="modal-card"><div class="modal-head"><h4>Confirmar Remocao</h4><button type="button" class="modal-close" data-close-modal="modalRemove">&times;</button></div><form method="post" class="modal-body"><input type="hidden" name="acao_admin" value="remover_usuario"><input type="hidden" name="usuario_id" id="removeUsuarioId" value=""><p id="removeTexto">Deseja remover este utilizador?</p><div class="modal-actions"><button type="button" class="btn-outline" data-close-modal="modalRemove">Cancelar</button><button type="submit" class="btn-primary" style="background:#b91c1c;border-color:#b91c1c;">Remover</button></div></form></div></div>
<?php endif; ?>

<script>
(function(){
  var menus=document.querySelectorAll('[data-menu-panel]');
  function closeMenus(){menus.forEach(function(p){p.classList.remove('open');});}
  document.querySelectorAll('[data-menu-toggle]').forEach(function(b){b.addEventListener('click',function(e){e.stopPropagation();var id=b.getAttribute('data-menu-toggle');var p=document.querySelector('[data-menu-panel="'+id+'"]');if(!p)return;var open=p.classList.contains('open');closeMenus();if(!open)p.classList.add('open');});});
  document.addEventListener('click',closeMenus);
  document.querySelectorAll('[data-menu-panel]').forEach(function(p){p.addEventListener('click',function(e){e.stopPropagation();});});

  var sk=document.getElementById('searchSkeleton');var card=document.getElementById('usersTableCard');
  ['searchForm','filtersForm'].forEach(function(id){var f=document.getElementById(id);if(!f)return;f.addEventListener('submit',function(){if(sk)sk.classList.add('visible');if(card)card.style.opacity='0.45';});});

  function openModal(id){var m=document.getElementById(id);if(!m)return;m.classList.add('open');m.setAttribute('aria-hidden','false');}
  function closeModal(id){var m=document.getElementById(id);if(!m)return;m.classList.remove('open');m.setAttribute('aria-hidden','true');}
  document.querySelectorAll('[data-close-modal]').forEach(function(b){b.addEventListener('click',function(){closeModal(b.getAttribute('data-close-modal'));});});
  document.querySelectorAll('.modal').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m){m.classList.remove('open');m.setAttribute('aria-hidden','true');}});});

  document.querySelectorAll('[data-open-pass]').forEach(function(b){b.addEventListener('click',function(){document.getElementById('senhaUsuarioId').value=b.getAttribute('data-id')||'';document.getElementById('senhaTexto').textContent='Defina a nova senha para '+(b.getAttribute('data-nome')||'utilizador')+'.';closeMenus();openModal('modalSenha');});});
  document.querySelectorAll('[data-open-toggle]').forEach(function(b){b.addEventListener('click',function(){var st=(b.getAttribute('data-status')||'').toLowerCase();document.getElementById('statusUsuarioId').value=b.getAttribute('data-id')||'';document.getElementById('statusTexto').textContent='Deseja '+(st==='ativo'?'bloquear':'desbloquear')+' a conta de '+(b.getAttribute('data-nome')||'utilizador')+'?';closeMenus();openModal('modalStatus');});});
  document.querySelectorAll('[data-open-remove]').forEach(function(b){b.addEventListener('click',function(){document.getElementById('removeUsuarioId').value=b.getAttribute('data-id')||'';document.getElementById('removeTexto').textContent='Deseja remover o utilizador '+(b.getAttribute('data-nome')||'utilizador')+'?';closeMenus();openModal('modalRemove');});});
  var btnNovo = document.getElementById('btnNovoUtilizador');
  if (btnNovo) {
    btnNovo.addEventListener('click', function () { openModal('modalNovo'); });
  }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
