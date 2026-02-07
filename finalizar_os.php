<?php
session_start();
require_once('config/db.php'); // Carrega a variável $pdo e a função registrarAuditoria

// Verifica login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verifica se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: modulo2_oficina.php?error=ID_nao_encontrado");
    exit();
}

$id_os = $_GET['id'];

try {
    // 1. Verificar se a OS existe e obter dados para a auditoria
    $stmt_check = $pdo->prepare("SELECT ativo_matricula, status_os FROM oficina_ordens_servico WHERE id = ?");
    $stmt_check->execute([$id_os]);
    $os = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        die("Erro: Ordem de Serviço não encontrada.");
    }

    if ($os['status_os'] === 'Concluído') {
        header("Location: modulo2_oficina.php?tab=ordens_servico&info=ja_concluida");
        exit();
    }

    // 2. Atualizar o status para Concluído e definir a data de conclusão
    // Usamos NOW() para pegar a hora exata do servidor
    $sql_update = "UPDATE oficina_ordens_servico SET status_os = 'Concluído', data_conclusao = NOW() WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$id_os]);

    // 3. Registrar na Auditoria (usando a sua função do db.php)
    $mensagem_auditoria = "Finalizou a OS #" . $id_os . " do Ativo: " . $os['ativo_matricula'];
    registrarAuditoria($pdo, $mensagem_auditoria, "oficina_ordens_servico");

    // 4. Redirecionar com sucesso
    header("Location: modulo2_oficina.php?tab=ordens_servico&success=1");
    exit();

} catch (PDOException $e) {
    // Caso haja erro no banco de dados
    die("Erro ao finalizar a Ordem de Serviço: " . $e->getMessage());
}
?>