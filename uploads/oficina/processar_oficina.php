<?php
session_start();
require_once('config/db.php'); // Carrega $pdo

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';

    // --- SALVAR ORDEM DE SERVIÇO ---
    if ($acao == 'abrir_os') {
        try {
            $sql = "INSERT INTO oficina_ordens_servico 
                    (ativo_matricula, setor_tecnico, tipo_intervencao, descricao_avaria, tecnico_diagnostico, projeto_destino, km_horimetro, validador_final, status_os) 
                    VALUES (:ativo, :setor, :tipo, :descricao, :tecnico, :projeto, :km, :validador, 'Em Aberto')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'ativo'     => $_POST['ativo_matricula'],
                'setor'     => $_POST['setor_tecnico'],
                'tipo'      => $_POST['tipo_intervencao'],
                'descricao' => $_POST['descricao_avaria'],
                'tecnico'   => $_POST['tecnico_diagnostico'],
                'projeto'   => $_POST['projeto_destino'],
                'km'        => $_POST['km_horimetro'],
                'validador' => $_POST['validador_final']
            ]);

            // Usa a sua função de auditoria do db.php
            registrarAuditoria($pdo, "Abriu OS para: " . $_POST['ativo_matricula'], "oficina_ordens_servico");

            header("Location: modulo2_oficina.php?tab=ordens_servico&success=1");
        } catch (PDOException $e) {
            die("Erro ao salvar OS: " . $e->getMessage());
        }
    }

    // --- REGISTRAR FERRAMENTA ---
    if ($acao == 'registrar_ferramenta') {
        try {
            $sql = "INSERT INTO oficina_ferramentas_uso (tecnico_nome, ferramenta, os_referente, servico_executado, estado_devolucao, data_uso) 
                    VALUES (:tec, :fer, :os, :serv, :est, :data)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'tec'  => $_POST['tecnico'],
                'fer'  => $_POST['ferramenta'],
                'os'   => $_POST['os_referente'],
                'serv' => $_POST['servico_executado'],
                'est'  => $_POST['estado_devolucao'],
                'data' => $_POST['data_uso']
            ]);
            header("Location: modulo2_oficina.php?tab=gestao_ferramentas&success=1");
        } catch (PDOException $e) {
            die("Erro ferramenta: " . $e->getMessage());
        }
    }
}
?>