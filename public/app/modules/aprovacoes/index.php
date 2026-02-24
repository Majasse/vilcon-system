<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: /vilcon-systemon/public/login.php'); exit; }
$page_title = 'Aprovações | Vilcon System';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function n($v){ return trim((string)$v); }
function m($v){ return number_format((float)$v, 2, ',', '.'); }
function up($f,$disk,$web){
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return null;
  $ext=strtolower(pathinfo((string)($f['name']??''),PATHINFO_EXTENSION));
  if(!in_array($ext,['pdf','png','jpg','jpeg','webp'],true)) throw new RuntimeException('Anexo inválido.');
  if(!is_dir($disk) && !mkdir($disk,0775,true) && !is_dir($disk)) throw new RuntimeException('Sem pasta de upload.');
  $name='aprov_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $to=rtrim($disk,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
  if(!move_uploaded_file((string)$f['tmp_name'],$to)) throw new RuntimeException('Falha no upload.');
  return rtrim($web,'/').'/'.$name;
}
function cotRows($raw){
  $out=[]; foreach(preg_split('/\r\n|\r|\n/',n($raw)) as $l){
    $l=trim($l); if($l==='') continue; $p=array_map('trim',explode('|',$l));
    $out[]=['f'=>$p[0]??'-','v'=>$p[1]??'-','p'=>$p[2]??'-','o'=>$p[3]??'-'];
  } return $out;
}

$disk = dirname(__DIR__, 3) . '/uploads/aprovacoes';
$web = '/vilcon-systemon/public/uploads/aprovacoes';

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/pdf.php';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

require_once __DIR__ . '/view.php';

require_once __DIR__ . '/../../includes/footer.php';
