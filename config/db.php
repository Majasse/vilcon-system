<?php
// config/db.php - O "Cérebro" do SIOV
$host = 'localhost';
$db = 'vilcon_vrp';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Constantes do Sistema
    if(!defined('NOME_SISTEMA')) define('NOME_SISTEMA', 'Sistema Integrado de Operações Vilcon');
    if(!defined('SIGLA_SISTEMA')) define('SIGLA_SISTEMA', 'SIOV');
    if(!defined('EMPRESA_NOME')) define('EMPRESA_NOME', 'Vilcon');

} catch (PDOException $e) {
    die("Erro ao ligar ao SIOV Vilcon: " . $e->getMessage());
}

// Função para Auditoria (Usada no processar_modulo.php)
if (!function_exists('registrarAuditoria')) {
    function registrarAuditoria($pdo, $acao, $tabela) {
        if(isset($_SESSION['usuario_id'])) {
            $sql = "INSERT INTO auditoria (usuario_id, acao, tabela_afetada, data_hora) VALUES (:user, :acao, :tab, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user' => $_SESSION['usuario_id'], 
                'acao' => $acao, 
                'tab' => $tabela
            ]);
        }
    }
}
?>