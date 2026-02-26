<?php
require_once dirname(__DIR__) . '/includes/mojibake_fix.php';
vilcon_bootstrap_mojibake_fix();

$host = 'localhost';
$db = 'vilcon_vrp';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!defined('NOME_SISTEMA')) define('NOME_SISTEMA', 'Sistema Integrado de Operacoes Vilcon');
    if (!defined('SIGLA_SISTEMA')) define('SIGLA_SISTEMA', 'SIOV');
    if (!defined('EMPRESA_NOME')) define('EMPRESA_NOME', 'Vilcon');
} catch (PDOException $e) {
    die('Erro ao ligar ao SIOV Vilcon: ' . $e->getMessage());
}

if (!function_exists('registrarAuditoria')) {
    function registrarAuditoria($pdo, $acao, $tabela) {
        if (isset($_SESSION['usuario_id'])) {
            $sql = 'INSERT INTO auditoria (usuario_id, acao, tabela_afetada, data_hora) VALUES (:user, :acao, :tab, NOW())';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user' => (int)$_SESSION['usuario_id'],
                'acao' => (string)$acao,
                'tab' => (string)$tabela,
            ]);
        }
    }
}

if (!function_exists('registrarAcaoSistema')) {
    function registrarAcaoSistema($pdo, $acao, $tabela = 'sistema', $usuarioId = null) {
        $userId = $usuarioId;
        if ($userId === null && isset($_SESSION['usuario_id'])) {
            $userId = (int)$_SESSION['usuario_id'];
        }

        $sql = 'INSERT INTO auditoria (usuario_id, acao, tabela_afetada, data_hora) VALUES (:user, :acao, :tab, NOW())';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':acao', (string)$acao, PDO::PARAM_STR);
        $stmt->bindValue(':tab', (string)$tabela, PDO::PARAM_STR);
        $stmt->execute();
    }
}

if (!function_exists('registrarAcessoAtual')) {
    function registrarAcessoAtual($pdo) {
        if (!isset($_SESSION['usuario_id'])) {
            return;
        }

        $metodo = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
        $rota = $script . ($query !== '' ? ('?' . $query) : '');

        registrarAcaoSistema($pdo, 'Acesso: ' . $metodo . ' ' . $rota, 'sistema');
    }
}

if (!empty($_SESSION['usuario_id']) && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'OPTIONS') {
    try {
        registrarAcessoAtual($pdo);
    } catch (Throwable $e) {
        // Nao interromper a aplicacao por falha de auditoria.
    }
}
?>
