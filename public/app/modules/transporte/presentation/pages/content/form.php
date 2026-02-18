                <?php if ($view == 'pedido_reparacao'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="color: var(--vilcon-black);">Pedido de Autorização de Reparação</h3>
                            <p style="font-size: 11px; color: var(--vilcon-orange); font-weight: bold;">Ref: VIL.F.TO.04_R3</p>
                        </div>
                        <div style="text-align: right;">
                            <span style="display: block; font-size: 10px; font-weight: bold; color: #777;">ESTADO DE ENVIO:</span>
                            <span class="badge" style="background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8;">Aguardando Gestor</span>
                        </div>
                    </div>

                    <form class="form-grid" action="processar_reparacao.php" method="POST">
                        <div class="section-title">1. Identificação do Equipamento</div>

                        <div class="form-group">
                            <label>Data do Pedido</label>
                            <input type="date" name="data_pedido" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Matrícula / TAG</label>
                            <input type="text" name="matricula" placeholder="Ex: AIJ-268-MP" required>
                        </div>
                        <div class="form-group">
                            <label>Nome do Equipamento</label>
                            <input type="text" name="nome_equipamento" placeholder="Ex: Nissan Patrol">
                        </div>
                        <div class="form-group">
                            <label>Sector / Projecto</label>
                            <input type="text" name="sector" placeholder="Ex: Roadworks / Logística">
                        </div>

                        <div class="section-title">2. Detalhes da Reparação</div>

                        <div class="form-group">
                            <label>Oficina Destino</label>
                            <select name="tipo_oficina">
                                <option value="INTERNA">Oficina Interna Vilcon</option>
                                <option value="EXTERNA">Oficina Externa</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Registro</label>
                            <select name="tipo_registro">
                                <option value="AVARIA">Avaria / Falha</option>
                                <option value="INSPECAO">Inspeção Técnica</option>
                                <option value="TESTE">Teste de Equipamento</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Solicitante (Nome Completo)</label>
                            <input type="text" name="solicitante_nome" required>
                        </div>

                        <div class="section-title">3. Estado na Entrada (Sintomas Detectados)</div>

                        <div style="grid-column: span 4; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; background: #fdfdfd; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
                            <label style="font-size: 11px; display: flex; align-items: center; gap: 8px;"><input type="checkbox" name="sintoma[]" value="vazamento"> Vazamento</label>
                            <label style="font-size: 11px; display: flex; align-items: center; gap: 8px;"><input type="checkbox" name="sintoma[]" value="vibracao"> Vibração</label>
                            <label style="font-size: 11px; display: flex; align-items: center; gap: 8px;"><input type="checkbox" name="sintoma[]" value="ruido"> Ruído Estranho</label>
                            <label style="font-size: 11px; display: flex; align-items: center; gap: 8px;"><input type="checkbox" name="sintoma[]" value="aquecimento"> Aquecimento</label>
                            <label style="font-size: 11px; display: flex; align-items: center; gap: 8px;"><input type="checkbox" name="sintoma[]" value="falha_eletrica"> Falha Elétrica</label>
                        </div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Descrição Detalhada do Problema</label>
                            <textarea name="descricao_avaria" rows="4" placeholder="Descreva o que aconteceu com o equipamento..."></textarea>
                        </div>

                        <div style="grid-column: span 4; background: #fffaf0; border-left: 5px solid #ed8936; padding: 15px; margin-top: 10px;">
                            <p style="font-size: 10px; color: #7b341e; font-weight: 800; text-transform: uppercase;">
                                <i class="fas fa-lock"></i> Áreas Restritas ao Gestor de Frota / Mecânico:
                            </p>
                            <p style="font-size: 11px; color: #9c4221; margin-top: 5px;">
                                Km/H Actual, Material Necessário, Custos, Histórico de Execução e Condições de Saída serão preenchidos pela equipa técnica após a recepção.
                            </p>
                        </div>

                        <div style="grid-column: span 4; display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                            <button type="submit" class="btn-save" style="background: var(--vilcon-orange); flex: 1;">
                                <i class="fas fa-file-signature"></i> Submeter Pedido de Autorização
                            </button>
                            <button type="button" class="btn-save" style="background: #95a5a6; width: 150px;">
                                <i class="fas fa-print"></i> Imprimir Guia
                            </button>
                        </div>
                    </form>
                <?php elseif ($view == 'checklist'): ?>
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: var(--vilcon-black);">Checklist de Pré-Utilização</h3>
                        <p style="font-size: 12px; color: #666;">Selecione o equipamento para carregar os itens de inspeção específicos.</p>
                    </div>

                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px solid #e0e0e0; margin-bottom: 20px;">
                        <div class="form-group" style="max-width: 400px; margin-bottom: 0;">
                            <label>Tipo de Equipamento / Ferramenta</label>
                            <select id="tipo_maquina" class="form-control" onchange="carregarCampos(this.value)">
                                <option value="">-- Selecione uma opção --</option>
                                <option value="bulldozer">Bulldozer (VIL.F.QAS.35)</option>
                                <option value="cilindro">Cilindro / Roller (VIL.F.QAS.36)</option>
                                <option value="niveladora">Niveladora / Grader (VIL.F.QAS.37)</option>
                                <option value="gerador">Gerador (VIL.F.QAS.32)</option>
                                <option value="ferramentas">Ferramentas Manuais (VIL.F.QAS.31)</option>
                            </select>
                        </div>
                    </div>

                    <form action="processar_checklist.php" method="POST">
                        <div id="campos_checklist" style="background: white; padding: 20px; border: 1px solid #eee; border-radius: 8px; display: none;">
                            <div class="section-title">Itens de Verificação - Bulldozer</div>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr style="background: #eee; font-size: 11px;">
                                    <th style="padding: 10px; text-align: left;">Descrição</th>
                                    <th style="width: 50px;">Sim</th>
                                    <th style="width: 50px;">Não</th>
                                    <th style="width: 50px;">N/A</th>
                                </tr>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #f0f0f0;">Nível de óleo do motor correto?</td>
                                    <td><input type="radio" name="item1" value="Y"></td>
                                    <td><input type="radio" name="item1" value="N"></td>
                                    <td><input type="radio" name="item1" value="NA"></td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #f0f0f0;">Sem vazamentos (água, óleo, combustível)?</td>
                                    <td><input type="radio" name="item2" value="Y"></td>
                                    <td><input type="radio" name="item2" value="N"></td>
                                    <td><input type="radio" name="item2" value="NA"></td>
                                </tr>
                            </table>

                            <div style="margin-top: 25px;">
                                <button type="submit" class="btn-save" style="background: var(--vilcon-orange);">
                                    <i class="fas fa-check-double"></i> Finalizar e Assinar Checklist
                                </button>
                            </div>
                        </div>
                    </form>

                    <script>
                    function carregarCampos(valor) {
                        const div = document.getElementById('campos_checklist');
                        if (valor !== "") {
                            div.style.display = 'block';
                        } else {
                            div.style.display = 'none';
                        }
                    }
                    </script>
                <?php elseif ($view == 'plano_manutencao'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="color: var(--vilcon-black);">Registo de Manutenção de Equipamentos</h3>
                            <p style="font-size: 11px; color: var(--success); font-weight: bold;">Ref: VIL.F.TO.02_R3 | Plano & Registo</p>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: #f0fff4; color: #2f855a; padding: 5px 12px; border-radius: 15px; font-size: 10px; font-weight: 800; border: 1px solid #c6f6d5;">
                                <i class="fas fa-tools"></i> CONTROLO TÉCNICO
                            </span>
                        </div>
                    </div>

                    <form class="form-grid" action="salvar_manutencao.php" method="POST">
                        <div class="section-title">1. Identificação do Equipamento</div>

                        <div class="form-group">
                            <label>Equipamento / Máquina</label>
                            <input type="text" name="equipamento" placeholder="Ex: Camião Basculante" required>
                        </div>
                        <div class="form-group">
                            <label>Marca / Modelo</label>
                            <input type="text" name="marca_modelo" placeholder="Ex: Sinotruck / HOWO">
                        </div>
                        <div class="form-group">
                            <label>Matrícula / TAG</label>
                            <input type="text" name="matricula" placeholder="Ex: AIU-769-MC" required>
                        </div>
                        <div class="form-group">
                            <label>Localização (Estaleiro/Obra)</label>
                            <input type="text" name="localizacao" placeholder="Ex: Vilankulos">
                        </div>

                        <div class="section-title">2. Detalhes da Intervenção</div>

                        <div class="form-group">
                            <label>Tipo de Manutenção</label>
                            <select name="tipo_manutencao">
                                <option value="PREVENTIVA">Preventiva (Revisão)</option>
                                <option value="CORRETIVA">Correctiva (Reparo)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data da Manutenção</label>
                            <input type="date" name="data_manutencao" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Responsável / Mecânico</label>
                            <input type="text" name="responsavel" placeholder="Ex: Chuva & Melcio">
                        </div>

                        <div class="section-title">3. Descrição dos Trabalhos Realizados</div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Trabalhos Executados / Observações</label>
                            <textarea name="descricao_trabalho" rows="4" placeholder="Ex: Substituição de filtros, troca de óleo de motor, lubrificação geral..."></textarea>
                        </div>

                        <div style="grid-column: span 4; background: #ebf8ff; border-left: 5px solid #3182ce; padding: 15px; border-radius: 4px;">
                            <div style="display: flex; align-items: center; gap: 10px; color: #2c5282;">
                                <i class="fas fa-user-shield" style="font-size: 18px;"></i>
                                <div>
                                    <p style="font-size: 10px; font-weight: 800; text-transform: uppercase; margin-bottom: 3px;">Validação da Gestão de Frota</p>
                                    <p style="font-size: 11px;">Os campos <strong>KM Actual</strong>, <strong>Próximo KM</strong> e <strong>Periodicidade</strong> serão validados pelo Gestor após a submissão deste registo.</p>
                                </div>
                            </div>
                        </div>

                        <div style="grid-column: span 4; display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                            <button type="submit" class="btn-save" style="background: var(--success); flex: 1;">
                                <i class="fas fa-save"></i> Gravar Registo de Manutenção
                            </button>
                            <button type="reset" class="btn-save" style="background: #95a5a6; width: 150px;">
                                Limpar
                            </button>
                        </div>
                    </form>
                <?php elseif ($view == 'avarias'): ?>
                    <h3>Registo de Avaria / Incidência</h3>
                    <form class="form-grid">
                        <div class="section-title">Dados da Ocorrência</div>
                        <div class="form-group"><label>Equipamento</label><input type="text"></div>
                        <div class="form-group"><label>Motorista</label><input type="text"></div>
                        <div class="form-group" style="grid-column: span 4;"><label>Descrição da Falha</label><textarea rows="3"></textarea></div>
                        <button class="btn-save" style="background:var(--danger); grid-column: span 4;">Registar Avaria</button>
                    </form>
                <?php elseif ($view == 'relatorio_atividades'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="color: var(--vilcon-black);">Relatório Diário de Transporte</h3>
                            <p style="font-size: 11px; color: var(--info); font-weight: bold;">Ref: VIL.F.TO.35_R0 | Registo Operacional</p>
                        </div>
                        <div style="background: #eef2f7; padding: 10px; border-radius: 8px; border: 1px solid #d1d9e6;">
                            <span style="display: block; font-size: 9px; color: #5c6ac4; font-weight: 800; text-transform: uppercase;">Data de Emissão</span>
                            <span style="font-size: 13px; font-weight: 700; color: #2c3e50;"><?= date('d/m/Y') ?></span>
                        </div>
                    </div>

                    <form class="form-grid" action="processar_relatorio.php" method="POST">
                        <div class="section-title">1. Identificação da Missão</div>

                        <div class="form-group">
                            <label>Nº Guia Manual</label>
                            <input type="text" name="guia_no" placeholder="Ex: 8491" required>
                        </div>
                        <div class="form-group">
                            <label>Nº Ordem (OS)</label>
                            <input type="text" name="ordem_no" placeholder="Ex: 1">
                        </div>
                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="prioridade">
                                <option value="BAIXA">Baixa</option>
                                <option value="MEDIA" selected>Média</option>
                                <option value="ALTA">Alta</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Solicitante</label>
                            <input type="text" name="solicitante" placeholder="Ex: Sr. Pascoal" required>
                        </div>

                        <div class="section-title">2. Veículo e Operação</div>

                        <div class="form-group">
                            <label>Matrícula</label>
                            <input type="text" name="matricula" placeholder="Ex: AHH-532-MP" required>
                        </div>
                        <div class="form-group">
                            <label>Equipamento</label>
                            <input type="text" name="equipamento" placeholder="Ex: MERCEDES GRUA">
                        </div>
                        <div class="form-group">
                            <label>Motorista</label>
                            <input type="text" name="motorista" placeholder="Nome do Condutor">
                        </div>
                        <div class="form-group">
                            <label>Hora Inicial (Saída)</label>
                            <input type="time" name="h_inicial">
                        </div>

                        <div class="section-title">3. Atividades e Percursos</div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>Local / Rota</label>
                            <input type="text" name="local_rota" placeholder="Ex: Estaleiro Vilcon -> Obra">
                        </div>
                        <div class="form-group" style="grid-column: span 1;">
                            <label>Qtd. Viagens</label>
                            <input type="number" name="viagens" value="1">
                        </div>
                        <div class="form-group" style="grid-column: span 1;">
                            <label>Abastecimento Sugerido (L)</label>
                            <input type="number" name="combustivel_solicitado" placeholder="Apenas pedido">
                        </div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Descrição das Atividades Realizadas</label>
                            <textarea name="atividades" rows="4" placeholder="Ex: Carregamento de 3 rolos de Vedação e outros materiais..."></textarea>
                        </div>

                        <div style="grid-column: span 4; background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-user-lock" style="color: #c53030; font-size: 20px;"></i>
                            <div>
                                <p style="font-size: 10px; color: #c53030; font-weight: 800; text-transform: uppercase;">Dados Restritos ao Gestor de Frota</p>
                                <p style="font-size: 11px; color: #742a2a;">Km Inicial/Final, Km Total, Horas de Operação e Custos Reais de Combustível são processados apenas na validação do relatório.</p>
                            </div>
                        </div>

                        <div style="grid-column: span 4; display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                            <button type="submit" class="btn-save" style="background: var(--vilcon-black); flex: 1;">
                                <i class="fas fa-share-square"></i> Submeter Relatório para Conferência
                            </button>
                            <button type="reset" class="btn-save" style="background: #95a5a6; width: 150px;">
                                Limpar
                            </button>
                        </div>
                    </form>
                <?php elseif ($view == 'entrada'): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="color: var(--vilcon-black);">Abertura de Ordem de Serviço</h3>
                            <p style="font-size: 11px; color: var(--info); font-weight: bold;">
                                <i class="fas fa-info-circle"></i> Esta OS será enviada ao Gestor de Frota para validação de KM e Horas.
                            </p>
                        </div>
                        <span style="background: #fdf2e9; color: var(--vilcon-orange); padding: 8px 15px; border-radius: 5px; font-weight: 800; font-size: 12px; border: 1px solid var(--vilcon-orange);">
                            SISTEMA SIOV | Nº <?= $proximo_id_os ?>
                        </span>
                    </div>

                    <form class="form-grid" action="enviar_para_gestor.php" method="POST">
                        <div class="section-title">1. Identificação da Solicitação</div>

                        <div class="form-group">
                            <label>Nº Guia Manual (Físico)</label>
                            <input type="text" name="guia_manual" placeholder="Ex: 8491" required>
                        </div>

                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="prioridade">
                                <option value="BAIXA">Baixa</option>
                                <option value="MEDIA" selected>Média</option>
                                <option value="ALTA">Alta</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Data/Hora Solicitação</label>
                            <input type="datetime-local" name="data_abertura" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="form-group">
                            <label>Solicitante</label>
                            <input type="text" name="solicitante" placeholder="Nome do solicitante">
                        </div>

                        <div class="section-title">2. Dados do Equipamento / Ativo</div>

                        <div class="form-group">
                            <label>Matrícula / TAG</label>
                            <input type="text" name="matricula" placeholder="Ex: AHV-648-MP" required>
                        </div>

                        <div class="form-group">
                            <label>Equipamento (Marca/Modelo)</label>
                            <input type="text" name="equipamento_desc" placeholder="Ex: TOYOTA COASTER">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label>Motorista Designado</label>
                            <input type="text" name="motorista" placeholder="Nome do Motorista">
                        </div>

                        <div class="section-title">3. Detalhes da Actividade</div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Descrição do Trabalho</label>
                            <textarea name="descricao_servico" rows="4" placeholder="Descreva a actividade a ser realizada..."></textarea>
                        </div>

                        <div class="form-group" style="grid-column: span 4;">
                            <label>Localização / Rota</label>
                            <input type="text" name="localizacao" placeholder="Ex: Vilankulos -> Maputo">
                        </div>

                        <div style="grid-column: span 4; background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; margin-top: 10px;">
                            <p style="font-size: 10px; color: #c53030; text-transform: uppercase; font-weight: 800;">
                                Atenção: Os campos de Km Inicial, Km Final e Horas de Operação são de preenchimento exclusivo do Gestor de Frota no momento da recepção da viatura.
                            </p>
                        </div>

                        <div style="grid-column: span 4; display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                            <button type="submit" class="btn-save" style="background: var(--vilcon-black); flex: 1;">
                                <i class="fas fa-paper-plane"></i> Enviar para Validação do Gestor
                            </button>
                            <a href="?tab=transporte&view=entrada&mode=list" class="btn-save" style="background: #ccc; text-decoration: none; text-align: center; color: #333;">
                                Cancelar
                            </a>
                        </div>
                    </form>
                <?php endif; ?>

