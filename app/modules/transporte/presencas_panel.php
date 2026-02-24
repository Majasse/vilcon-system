<div style="background:#fff; border:1px solid #e5edf5; border-radius:8px; padding:10px;">
    <?php if(!empty($msg_presencas)): ?>
        <div style="background:#e8f8ef; color:#1e8449; padding:10px; border-radius:6px; margin-bottom:12px; font-weight:700;"><?= htmlspecialchars($msg_presencas) ?></div>
    <?php endif; ?>
    <?php if(!empty($erro_form)): ?>
        <div style="background:#fdecec; color:#b91c1c; padding:10px; border-radius:6px; margin-bottom:12px; font-weight:700;"><?= htmlspecialchars($erro_form) ?></div>
    <?php endif; ?>

    <form method="GET" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px;">
        <input type="hidden" name="tab" value="transporte">
        <input type="hidden" name="view" value="presencas">
        <input type="hidden" name="mode" value="list">
        <div class="form-group" style="margin:0;">
            <label>Data da Lista</label>
            <input type="date" name="data_assiduidade" value="<?= htmlspecialchars((string) $data_assiduidade_transporte) ?>">
        </div>
        <button type="submit" class="btn-mode">Carregar Lista</button>
        <button type="button" class="btn-mode" onclick="abrirTelaListasPresencas()"><i class="fa-solid fa-list-check"></i> Ver listas de presencas</button>
    </form>

    <form method="POST" action="?tab=transporte&view=presencas&mode=list&data_assiduidade=<?= urlencode((string) $data_assiduidade_transporte) ?>" style="margin-bottom:14px;">
        <input type="hidden" name="acao_presencas" value="marcar_presenca_lote">
        <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string) $data_assiduidade_transporte) ?>">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
            <div style="font-size:12px; color:#374151; font-weight:700;">Marque entrada/saida conforme a folha fisica do dia.</div>
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <button type="button" class="btn-save" onclick="marcarTodosPresentesTransporte(event)" style="background:#0ea5e9;" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>>Marcar todos presentes</button>
                <button type="button" class="btn-save" onclick="marcarTodosAusentesTransporte(event)" style="background:#9ca3af;" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>>Marcar todos ausentes</button>
            </div>
            <button type="submit" class="btn-save" style="background:#111827;" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>>Salvar lista</button>
        </div>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Numero</th>
                    <th>Nome</th>
                    <th>Cargo</th>
                    <th>Entrada</th>
                    <th>Hora Entrada</th>
                    <th>Saida</th>
                    <th>Hora Saida</th>
                    <th>Observacoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($colaboradores_transporte)): ?>
                    <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:12px;">Sem funcionarios para marcacao.</td></tr>
                <?php else: ?>
                    <?php foreach($colaboradores_transporte as $col): ?>
                        <?php
                            $pid = (int) ($col['id'] ?? 0);
                            $atual = $presencas_por_colaborador[$pid] ?? null;
                            $inChecked = (int) ($atual['assinou_entrada'] ?? 0) === 1;
                            $outChecked = (int) ($atual['assinou_saida'] ?? 0) === 1;
                            $horaIn = !empty($atual['hora_entrada']) ? substr((string) $atual['hora_entrada'], 0, 5) : '07:00';
                            $horaOut = !empty($atual['hora_saida']) ? substr((string) $atual['hora_saida'], 0, 5) : '16:00';
                            $obsAtual = (string) ($atual['observacoes'] ?? '');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($col['numero'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string) ($col['nome'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string) ($col['cargo_nome'] ?? '-')) ?></td>
                            <td><input type="checkbox" class="js-pres-entry" name="entrada_lote[<?= $pid ?>]" value="1" <?= $inChecked ? 'checked' : '' ?> <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                            <td><input type="time" class="js-pres-entry-time" name="hora_entrada_lote[<?= $pid ?>]" value="<?= htmlspecialchars($horaIn) ?>" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                            <td><input type="checkbox" class="js-pres-exit" name="saida_lote[<?= $pid ?>]" value="1" <?= $outChecked ? 'checked' : '' ?> <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                            <td><input type="time" class="js-pres-exit-time" name="hora_saida_lote[<?= $pid ?>]" value="<?= htmlspecialchars($horaOut) ?>" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                            <td><input type="text" name="obs_lote[<?= $pid ?>]" value="<?= htmlspecialchars($obsAtual) ?>" placeholder="Opcional" <?= $lista_presenca_enviada_rh ? 'disabled' : '' ?>></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <div id="painel-listas-presencas" style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(15,23,42,0.6); padding:24px; overflow:auto;">
        <div style="max-width:1200px; margin:0 auto; background:#fff; border-radius:12px; border:1px solid #dbe3ed; padding:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <div style="font-size:13px; font-weight:800; color:#334155;">Listas de Presencas (ultimos 30 dias)</div>
                <button type="button" class="btn-mode" onclick="fecharTelaListasPresencas()"><i class="fa-solid fa-xmark"></i> Fechar tela</button>
            </div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Lista</th>
                        <th>Total Funcionarios</th>
                        <th>Presentes</th>
                        <th>Ausentes</th>
                        <th>Lista Fisica</th>
                        <th>Enviado RH</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($listas_presenca_dias)): ?>
                        <tr><td colspan="7" style="text-align:center; color:#777;">Sem listas de presenca no periodo.</td></tr>
                    <?php else: ?>
                        <?php foreach($listas_presenca_dias as $ld): ?>
                            <?php
                                $dataLista = (string) ($ld['data_presenca'] ?? '');
                                $enviadoTodos = (int) ($ld['enviado_rh_todos'] ?? 0) === 1;
                                $possuiAnexo = (int) ($ld['possui_anexo'] ?? 0) === 1;
                                $anexoPathLista = (string) ($ld['lista_fisica_anexo'] ?? '');
                            ?>
                            <tr>
                                <td><?= !empty($dataLista) ? ('Lista ' . date('d/m/Y', strtotime($dataLista))) : '-' ?></td>
                                <td><?= (int) ($ld['total_funcionarios'] ?? 0) ?></td>
                                <td><?= (int) ($ld['total_presentes'] ?? 0) ?></td>
                                <td><?= (int) ($ld['total_ausentes'] ?? 0) ?></td>
                                <td>
                                    <?php if($possuiAnexo && $anexoPathLista !== ''): ?>
                                        <a href="<?= htmlspecialchars('/vilcon-systemon/' . ltrim($anexoPathLista, '/')) ?>" target="_blank" class="btn-mode" style="font-size:10px;">Ver anexo</a>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#b91c1c; font-weight:700;">Nao anexada</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $enviadoTodos ? 'Sim' : 'Nao' ?></td>
                                <td>
                                    <a href="?tab=transporte&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>&hist_data=<?= urlencode($dataLista) ?>" class="btn-mode" style="font-size:10px; margin-right:6px;">Ver historico</a>
                                    <?php if(!$enviadoTodos): ?>
                                        <form method="POST" enctype="multipart/form-data" action="?tab=transporte&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>" style="display:inline; margin-right:6px;">
                                            <input type="hidden" name="acao_presencas" value="anexar_lista_fisica">
                                            <input type="hidden" name="data_presenca" value="<?= htmlspecialchars($dataLista) ?>">
                                            <input type="file" name="lista_fisica_file[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif" style="font-size:10px; width:150px;" required>
                                            <button type="submit" class="btn-mode" style="font-size:10px;">Anexar lista</button>
                                        </form>
                                        <a href="?tab=transporte&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>" class="btn-mode" style="font-size:10px; margin-right:6px;">Editar</a>
                                        <form method="POST" action="?tab=transporte&view=presencas&mode=list&data_assiduidade=<?= urlencode($dataLista) ?>" style="display:inline;">
                                            <input type="hidden" name="acao_presencas" value="enviar_rh">
                                            <input type="hidden" name="data_presenca" value="<?= htmlspecialchars($dataLista) ?>">
                                            <button type="submit" class="btn-mode" style="font-size:10px; background:#8e44ad; color:#fff; border-color:#8e44ad;" <?= $possuiAnexo ? '' : 'disabled title="Anexe primeiro a lista fisica"' ?>>Enviar RH</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:11px; color:#64748b; font-weight:700;">Bloqueada apos envio</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="painel-lista-dia-presencas" style="display:none; position:fixed; inset:0; z-index:1100; background:rgba(15,23,42,0.68); padding:24px; overflow:auto;">
        <div style="max-width:1150px; margin:0 auto; background:#fff; border-radius:12px; border:1px solid #dbe3ed; padding:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                <div style="font-size:13px; font-weight:800; color:#334155;">Lista de Presencas - <?= $hist_data_transporte !== '' ? htmlspecialchars(date('d/m/Y', strtotime($hist_data_transporte))) : '' ?></div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <?php if($hist_data_transporte !== ''): ?>
                        <a class="btn-mode" style="background:#c2410c; color:#fff;" target="_blank" href="?tab=transporte&view=presencas&mode=list&doc=presenca_pdf&data_presenca=<?= urlencode($hist_data_transporte) ?>"><i class="fa-solid fa-file-pdf"></i> Baixar PDF</a>
                        <a class="btn-mode" style="background:#166534; color:#fff;" target="_blank" href="?tab=transporte&view=presencas&mode=list&doc=presenca_excel&data_presenca=<?= urlencode($hist_data_transporte) ?>"><i class="fa-solid fa-file-excel"></i> Baixar Excel</a>
                        <a class="btn-mode" style="background:#1d4ed8; color:#fff;" target="_blank" href="?tab=transporte&view=presencas&mode=list&doc=presenca_word&data_presenca=<?= urlencode($hist_data_transporte) ?>"><i class="fa-solid fa-file-word"></i> Baixar Word</a>
                    <?php endif; ?>
                    <button type="button" class="btn-mode" onclick="fecharTelaListaDiaPresencas()"><i class="fa-solid fa-xmark"></i> Fechar tela</button>
                </div>
            </div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Funcionario</th>
                        <th>Cargo</th>
                        <th>Entrada</th>
                        <th>Saida</th>
                        <th>Estado</th>
                        <th>Enviado RH</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lista_presencas_historico)): ?>
                        <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:12px;">Sem registos para esta lista.</td></tr>
                    <?php else: ?>
                        <?php foreach($lista_presencas_historico as $prh): ?>
                            <tr>
                                <td><?= !empty($prh['data_presenca']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $prh['data_presenca']))) : '-' ?></td>
                                <td><?= htmlspecialchars((string) ($prh['colaborador'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($prh['funcao'] ?? '-')) ?></td>
                                <td><?= !empty($prh['hora_entrada']) ? htmlspecialchars(substr((string) $prh['hora_entrada'], 0, 5)) : '-' ?></td>
                                <td><?= !empty($prh['hora_saida']) ? htmlspecialchars(substr((string) $prh['hora_saida'], 0, 5)) : '-' ?></td>
                                <td><?= htmlspecialchars((string) ($prh['estado'] ?? '-')) ?></td>
                                <td><?= (int) ($prh['enviado_rh'] ?? 0) === 1 ? 'Sim' : 'Nao' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function abrirTelaListasPresencas() {
    var el = document.getElementById('painel-listas-presencas');
    if(el) el.style.display = 'block';
}
function fecharTelaListasPresencas() {
    var el = document.getElementById('painel-listas-presencas');
    if(el) el.style.display = 'none';
}
function abrirTelaListaDiaPresencas() {
    var el = document.getElementById('painel-lista-dia-presencas');
    if(el) el.style.display = 'block';
}
function fecharTelaListaDiaPresencas() {
    var el = document.getElementById('painel-lista-dia-presencas');
    if(el) el.style.display = 'none';
}
function marcarTodosPresentesTransporte(ev) {
    if(ev) ev.preventDefault();
    document.querySelectorAll('input.js-pres-entry').forEach(function(el){ if(!el.disabled) el.checked = true; });
    document.querySelectorAll('input.js-pres-exit').forEach(function(el){ if(!el.disabled) el.checked = true; });
    document.querySelectorAll('input.js-pres-entry-time').forEach(function(el){ if(!el.disabled && !el.value) el.value = '07:00'; });
    document.querySelectorAll('input.js-pres-exit-time').forEach(function(el){ if(!el.disabled && !el.value) el.value = '16:00'; });
}
function marcarTodosAusentesTransporte(ev) {
    if(ev) ev.preventDefault();
    document.querySelectorAll('input.js-pres-entry').forEach(function(el){ if(!el.disabled) el.checked = false; });
    document.querySelectorAll('input.js-pres-exit').forEach(function(el){ if(!el.disabled) el.checked = false; });
    document.querySelectorAll('input.js-pres-entry-time').forEach(function(el){ if(!el.disabled) el.value = ''; });
    document.querySelectorAll('input.js-pres-exit-time').forEach(function(el){ if(!el.disabled) el.value = ''; });
}
document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-listas-presencas');
    if(el && el.style.display === 'block' && ev.target === el) fecharTelaListasPresencas();
});
document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-lista-dia-presencas');
    if(el && el.style.display === 'block' && ev.target === el) fecharTelaListaDiaPresencas();
});
<?php if($hist_data_transporte !== ''): ?>
document.addEventListener('DOMContentLoaded', function() {
    abrirTelaListasPresencas();
    abrirTelaListaDiaPresencas();
});
<?php endif; ?>
</script>
