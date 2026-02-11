<h3>Ordens de Serviço</h3>

<form method="POST" action="../../../processar_oficina.php">
    <input type="hidden" name="acao" value="abrir_os">

    <input name="ativo_matricula" placeholder="Ativo" required>
    <input name="tecnico_diagnostico" placeholder="Técnico">
    <textarea name="descricao_avaria" placeholder="Descrição"></textarea>

    <button>Abrir OS</button>
</form>

<hr>

<?php
$stmt = $pdo->query("SELECT * FROM oficina_ordens_servico ORDER BY id DESC");
?>

<table>
    <tr>
        <th>OS</th>
        <th>Ativo</th>
        <th>Status</th>
        <th>Ações</th>
    </tr>
    <?php while($os = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
            <td>#<?= $os['id'] ?></td>
            <td><?= htmlspecialchars($os['ativo_matricula']) ?></td>
            <td><?= $os['status_os'] ?></td>
            <td>
                <a href="visualizar.php?id=<?= $os['id'] ?>">Ver</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
