<?php 
$ocultar_marcacao_manual_presencas = isset($ocultar_marcacao_manual_presencas) ? (bool) $ocultar_marcacao_manual_presencas : false;
?>
<div style="background:#fff; border:1px solid #e5edf5; border-radius:8px; padding:10px;">
    <style>
        #trFaceVideo {
            width: 100%;
            height: clamp(460px, 82vh, 860px) !important;
            object-fit: cover;
            background: transparent;
            border-radius: 8px;
        }
        #trFaceFeedback {
            position: fixed;
            right: 18px;
            top: 18px;
            z-index: 1400;
            min-width: 260px;
            max-width: 420px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.18);
            font-size: 13px;
            font-weight: 800;
            display: none;
            transform: translateY(-12px) scale(0.98);
            opacity: 0;
            transition: transform .22s ease, opacity .22s ease;
        }
        #trFaceFeedback.tr-show {
            display: block;
            transform: translateY(0) scale(1);
            opacity: 1;
            animation: trFacePulse .32s ease;
        }
        #trFaceFeedback.tr-success { background: #ecfdf3; border-color: #86efac; color: #166534; }
        #trFaceFeedback.tr-error { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
        #trFaceFeedback .tr-face-row { display:flex; align-items:center; gap:10px; }
        #trFaceFeedback .tr-face-icon {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 2px solid currentColor;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 900;
            flex: 0 0 30px;
        }
        #trFaceFeedback .tr-face-msg {
            opacity: 0;
            transform: translateX(6px);
        }
        #trFaceOverlayStatus {
            position: absolute;
            left: 50%;
            top: 12px;
            transform: translate(-50%, -8px);
            z-index: 12;
            max-width: calc(100% - 28px);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255,255,255,0.38);
            backdrop-filter: blur(10px) saturate(140%);
            color: #334155;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease, transform .2s ease;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #trFaceOverlayStatus.tr-show {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        #trFaceOverlayStatus.tr-error {
            background: rgba(254, 226, 226, 0.48);
            color: #b91c1c;
            border-color: rgba(252, 165, 165, 0.65);
        }
        #trFaceOverlayStatus.tr-success {
            background: rgba(220, 252, 231, 0.44);
            color: #166534;
            border-color: rgba(134, 239, 172, 0.65);
        }
        #trFaceDecision {
            position: absolute;
            left: 50%;
            bottom: 14px;
            transform: translate(-50%, 10px);
            z-index: 13;
            min-width: 260px;
            max-width: calc(100% - 24px);
            border-radius: 12px;
            border: 1px solid rgba(148,163,184,.45);
            background: rgba(255,255,255,.44);
            backdrop-filter: blur(10px) saturate(130%);
            box-shadow: 0 12px 28px rgba(15,23,42,.18);
            padding: 10px 12px;
            opacity: 0;
            pointer-events: none;
            transition: transform .22s ease, opacity .22s ease;
        }
        #trFaceDecision.tr-show {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        #trFaceDecision .line1 {
            font-size: 12px;
            font-weight: 900;
            color: #166534;
            display:flex;
            align-items:center;
            gap:8px;
        }
        #trFaceDecision .line2 {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }
        #trFaceDecision .line3 {
            font-size: 11px;
            font-weight: 700;
            color: #0f766e;
            margin-top: 3px;
        }
        #trFaceLiveName {
            position: absolute;
            left: 12px;
            bottom: 58px;
            transform: translateY(10px);
            z-index: 12;
            max-width: min(62%, calc(100% - 24px));
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid rgba(148, 163, 184, 0.48);
            background: rgba(255,255,255,0.38);
            backdrop-filter: blur(10px) saturate(140%);
            color: #0f172a;
            opacity: 0;
            pointer-events: none;
            transition: opacity .22s ease, transform .22s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #trFaceLiveName.tr-show {
            opacity: 1;
            transform: translateY(0);
        }
        #trFaceStatus {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 800;
            color: #334155;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255, 255, 255, 0.42);
            backdrop-filter: blur(8px) saturate(130%);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
        }
        #trFaceStatus i {
            font-size: 12px;
            width: 14px;
            text-align: center;
        }
        #trFaceStatus .tr-state-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #64748b;
            box-shadow: 0 0 0 0 rgba(100,116,139,.45);
            animation: trPulseDot 1.4s infinite;
        }
        #trFaceStatus.tr-state-active {
            color: #166534;
            border-color: rgba(134, 239, 172, 0.7);
            background: rgba(220, 252, 231, 0.42);
        }
        #trFaceStatus.tr-state-active .tr-state-dot {
            background: #16a34a;
            box-shadow: 0 0 0 0 rgba(22,163,74,.45);
        }
        #trFaceStatus.tr-state-inactive {
            color: #334155;
        }
        #trFaceStatus.tr-state-error {
            color: #b91c1c;
            border-color: rgba(252, 165, 165, 0.7);
            background: rgba(254, 226, 226, 0.42);
        }
        #trFaceStatus.tr-state-error .tr-state-dot {
            background: #dc2626;
            box-shadow: 0 0 0 0 rgba(220,38,38,.4);
        }
        @keyframes trPulseDot {
            70% { box-shadow: 0 0 0 8px rgba(0,0,0,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); }
        }
        #trFaceFeedback.tr-show .tr-face-msg {
            animation: trFaceMsgIn .26s ease .18s forwards;
        }
        #trFaceFeedback.tr-show.tr-error .tr-face-icon {
            animation: trFaceXIn .35s ease;
        }
        #trFaceFeedback.tr-show.tr-success .tr-face-icon {
            animation: trFaceCheckIn .35s ease;
        }
        .tr-alert {
            display:flex;
            align-items:center;
            gap:10px;
            padding:11px 12px;
            border-radius:12px;
            margin-bottom:12px;
            font-size:13px;
            font-weight:800;
            border:1px solid transparent;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            transition: opacity .35s ease, transform .35s ease;
        }
        .tr-alert.tr-fade-out { opacity:0; transform: translateY(-6px); }
        .tr-alert i { font-size:15px; }
        .tr-alert.success { background:linear-gradient(135deg, rgba(220,252,231,.85), rgba(236,253,245,.95)); color:#166534; border-color:#86efac; }
        .tr-alert.error { background:linear-gradient(135deg, rgba(254,226,226,.92), rgba(254,242,242,.96)); color:#b91c1c; border-color:#fca5a5; }
        .tr-scan-card {
            margin-top:8px;
            border:1px dashed #94a3b8;
            border-radius:12px;
            padding:8px;
            background:
                radial-gradient(1200px 220px at 10% 0%, rgba(14,165,233,.06), transparent 60%),
                radial-gradient(1000px 220px at 90% 0%, rgba(34,197,94,.06), transparent 60%),
                #fff;
            max-width:1100px;
        }
        .tr-progress-wrap {
            margin-top:6px;
            display:grid;
            grid-template-columns: 100px 1fr;
            gap:10px;
            align-items:center;
        }
        .tr-progress-ring {
            position:relative;
            width:92px;
            height:92px;
            margin:0 auto;
        }
        .tr-progress-ring svg {
            width:92px;
            height:92px;
            transform:rotate(-90deg);
        }
        .tr-progress-ring .trk { stroke: rgba(148,163,184,.35); stroke-width:8; fill:none; }
        .tr-progress-ring .trv {
            stroke:url(#trFaceRingGrad);
            stroke-width:8;
            stroke-linecap:round;
            fill:none;
            stroke-dasharray:314;
            stroke-dashoffset:314;
            filter:drop-shadow(0 0 4px rgba(14,165,233,.35));
            transition:stroke-dashoffset .22s ease, filter .22s ease;
        }
        .tr-progress-ring .txt {
            position:absolute;
            inset:0;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:15px;
            font-weight:900;
            color:#0f172a;
            letter-spacing:.2px;
            text-shadow:0 1px 0 rgba(255,255,255,.65);
        }
        .tr-progress-main .title {
            font-size:12px;
            font-weight:800;
            color:#334155;
            margin-bottom:6px;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .tr-progress-main .title i {
            color:#0ea5e9;
        }
        .tr-progress-bar {
            position:relative;
            height:10px;
            background:#e2e8f0;
            border-radius:999px;
            overflow:hidden;
        }
        .tr-growth-chart {
            margin-top: 6px;
            height: 62px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background:
                linear-gradient(180deg, rgba(14,165,233,.06), rgba(34,197,94,.03)),
                rgba(255,255,255,.52);
            position: relative;
            overflow: hidden;
            padding: 6px;
        }
        .tr-growth-bars {
            position: absolute;
            inset: 6px;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            align-items: end;
            gap: 4px;
        }
        .tr-growth-bars span {
            border-radius: 4px 4px 0 0;
            background: rgba(100,116,139,.32);
            transition: background .22s ease, box-shadow .22s ease, opacity .22s ease, transform .22s ease, filter .22s ease;
        }
        .tr-growth-bars span.active {
            background: linear-gradient(180deg, #22c55e, #16a34a);
            box-shadow: 0 0 0 1px rgba(255,255,255,.28) inset, 0 0 10px rgba(34,197,94,.25);
        }
        .tr-progress-bar > div {
            position:relative;
            height:100%;
            width:0%;
            background:linear-gradient(90deg,#0ea5e9,#22c55e);
            box-shadow: 0 0 0 1px rgba(255,255,255,.18) inset, 0 0 10px rgba(14,165,233,.2);
            transition:width .24s ease, background .22s ease, box-shadow .22s ease, filter .22s ease;
        }
        .tr-progress-bar > div.tr-rise {
            filter:saturate(1.24) brightness(1.14);
        }
        .tr-growth-chart.tr-rise {
            box-shadow: 0 0 0 1px rgba(255,255,255,.2) inset, 0 0 16px rgba(14,165,233,.22);
        }
        .tr-progress-bar > div::after {
            content:'';
            position:absolute;
            top:0; right:-28px;
            width:28px; height:100%;
            background:linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.55));
            filter:blur(1px);
        }
        #trFaceReady {
            font-size:12px;
            color:#64748b;
            margin-top:6px;
            font-weight:700;
        }
        #trFaceStage {
            margin-top:4px;
            font-size:11px;
            color:#0f766e;
            font-weight:800;
            letter-spacing:.1px;
        }
        @media (max-height: 930px) {
            #trFaceVideo { height: clamp(400px, 72vh, 760px) !important; }
            .tr-scan-card { padding: 6px; }
            .tr-progress-wrap { grid-template-columns: 84px 1fr; gap: 8px; margin-top: 5px; }
            .tr-progress-ring { width: 78px; height: 78px; }
            .tr-progress-ring svg { width: 78px; height: 78px; }
            .tr-progress-ring .txt { font-size: 13px; }
            .tr-progress-main .title { margin-bottom: 4px; font-size: 11px; }
            .tr-progress-bar { height: 8px; }
            #trFaceReady { margin-top: 5px; font-size: 11px; }
            #trFaceStage { margin-top: 3px; font-size: 10px; }
        }
        @keyframes trFacePulse {
            0% { transform: translateY(-12px) scale(0.97); }
            70% { transform: translateY(0) scale(1.02); }
            100% { transform: translateY(0) scale(1); }
        }
        @keyframes trFaceMsgIn {
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes trFaceXIn {
            0% { transform: scale(0.4) rotate(-35deg); }
            70% { transform: scale(1.08) rotate(5deg); }
            100% { transform: scale(1) rotate(0); }
        }
        @keyframes trFaceCheckIn {
            0% { transform: scale(0.4); }
            70% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }
    </style>
    <div id="trFaceFeedback" role="status" aria-live="polite"></div>
    <?php $ocultar_marcacao_manual_presencas = false; ?>
    <?php if(!empty($msg_presencas)): ?>
        <div class="tr-alert success tr-auto-dismiss"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($msg_presencas) ?></span></div>
    <?php endif; ?>
    <?php if(!empty($erro_form)): ?>
        <div class="tr-alert error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($erro_form) ?></span></div>
    <?php endif; ?>

    <div style="display:flex; gap:8px; align-items:center; justify-content:space-between; flex-wrap:wrap; margin-bottom:12px;">
        <div id="trDataHoraAuto" style="font-size:12px; color:#475569; font-weight:700;">Data automatica: <?= htmlspecialchars(date('d/m/Y', strtotime((string) $data_assiduidade))) ?> | Hora: --:--:--</div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if (false): ?>
            <button type="button" class="btn-mode" onclick="trAbrirPainelRegistoFacial()"><i class="fa-solid fa-user-check"></i> Lista de Registo Facial</button>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode((string) $data_assiduidade) ?>" style="margin-bottom:14px;border:1px solid #dbe3ed;border-radius:10px;padding:10px;background:#f8fafc;">
        <input type="hidden" name="acao_presencas" value="marcar_presenca_camera">
        <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string) $data_assiduidade) ?>">
        <select name="pessoal_id" id="trFacePessoalHidden" style="display:none;">
            <option value="">Selecionar</option>
            <?php foreach($colaboradores_oficina as $co): ?>
                <?php
                    $pidFotoTr = (int)($co['id'] ?? 0);
                    $fotoUrlTr = '';
                    $presAtualTr = $presencas_por_colaborador[$pidFotoTr] ?? null;
                    $assinouEntradaTr = (int)($presAtualTr['assinou_entrada'] ?? 0);
                    $assinouSaidaTr = (int)($presAtualTr['assinou_saida'] ?? 0);
                    $temTemplateTr = isset($face_templates_map_oficina[$pidFotoTr]) && trim((string)$face_templates_map_oficina[$pidFotoTr]) !== '';
                    if ($pidFotoTr > 0) {
                        $baseDirTr = dirname(__DIR__, 4) . '/public/uploads/pessoal/';
                        $baseUrlTr = '/vilcon-systemon/public/uploads/pessoal/';
                        foreach ([$pidFotoTr . '.jpg', $pidFotoTr . '.jpeg', $pidFotoTr . '.png', $pidFotoTr . '.webp', 'func_' . $pidFotoTr . '.jpg', 'func_' . $pidFotoTr . '.png'] as $fnTr) {
                            if (is_file($baseDirTr . $fnTr)) { $fotoUrlTr = $baseUrlTr . $fnTr; break; }
                        }
                    }
                ?>
                <option value="<?= (int)($co['id'] ?? 0) ?>" data-face-name="<?= htmlspecialchars((string)($co['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>" data-face-img="<?= htmlspecialchars((string)$fotoUrlTr, ENT_QUOTES, 'UTF-8') ?>" data-face-template="<?= htmlspecialchars((string)($face_templates_map_oficina[$pidFotoTr] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-assinou-entrada="<?= $assinouEntradaTr ?>" data-assinou-saida="<?= $assinouSaidaTr ?>" data-tem-template="<?= $temTemplateTr ? '1' : '0' ?>"><?= htmlspecialchars((string)($co['nome'] ?? '-')) ?> - <?= htmlspecialchars((string)($co['numero'] ?? '-')) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="tipo_batida" id="trFaceTipoBatida" value="Entrada">
        <input type="hidden" id="trFaceBatidaManual" value="0">
        <input type="hidden" id="trFaceScoreSim" name="score_similaridade" value="90">
        <input type="hidden" id="trFaceScoreLive" name="score_liveness" value="88">
        <input type="hidden" name="payload_captura" id="trFacePayload" value="" required>
        <input type="hidden" name="ultra_assistido" id="trFaceUltra" value="0">
        <div style="display:flex; gap:8px; align-items:center; margin-top:8px; flex-wrap:wrap;">
            <div style="font-size:12px; color:#334155; font-weight:700;">Batida</div>
            <button type="button" id="trBtnEntrada" class="btn-mode" style="background:#166534;color:#fff;border-color:#166534;" onclick="trSelecionarBatidaManual('Entrada')">Entrada</button>
            <button type="button" id="trBtnSaida" class="btn-mode" style="background:#334155;color:#fff;border-color:#334155;" onclick="trSelecionarBatidaManual('Saida')">Saida</button>
            <button type="button" class="btn-mode" onclick="trAtivarBatidaAuto()">Automatico</button>
            <div id="trBatidaModoInfo" style="font-size:11px;color:#64748b;">Modo: Automatico</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto auto auto;gap:8px;align-items:center;margin-top:8px;">
            <select id="trFaceDevice" style="width:100%;"><option value="">Selecionar camera do dispositivo</option></select>
            <button type="button" class="btn-mode" onclick="trAtualizarCameras()">Atualizar</button>
            <button type="button" class="btn-mode" style="background:#0f172a;color:#fff;border-color:#0f172a;" onclick="trAbrirCamera()">Abrir camera</button>
            <button type="button" class="btn-mode" style="background:#64748b;color:#fff;border-color:#64748b;" onclick="trFecharCamera()">Fechar</button>
        </div>
        <div class="tr-scan-card">
            <div style="position:relative;">
                <video id="trFaceVideo" autoplay muted playsinline></video>
                <canvas id="trFaceOverlay" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;border-radius:8px;"></canvas>
                <div id="trFaceOverlayStatus" aria-live="polite"></div>
                <div id="trFaceDecision" aria-live="polite"></div>
                <div id="trFaceLiveName" aria-live="polite"></div>
            </div>
            <canvas id="trFaceCanvas" style="display:none;"></canvas>
            <div class="tr-progress-wrap">
                <div class="tr-progress-ring">
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <defs>
                            <linearGradient id="trFaceRingGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop id="trFaceRingStart" offset="0%" stop-color="#0ea5e9"></stop>
                                <stop id="trFaceRingEnd" offset="100%" stop-color="#22c55e"></stop>
                            </linearGradient>
                        </defs>
                        <circle class="trk" cx="60" cy="60" r="50"></circle>
                        <circle id="trFacePctRing" class="trv" cx="60" cy="60" r="50"></circle>
                    </svg>
                    <div class="txt" id="trFacePct">0%</div>
                </div>
                <div class="tr-progress-main">
                    <div class="title"><i class="fa-solid fa-wave-square"></i> Leitura facial 3D</div>
                    <div class="tr-progress-bar">
                        <div id="trFacePctBar"></div>
                    </div>
                    <div id="trFaceGrowth" class="tr-growth-chart" aria-hidden="true"></div>
                    <div id="trFaceReady">Aguardando leitura facial...</div>
                    <div id="trFaceStage">Estado: inicializando varredura inteligente</div>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;justify-content:flex-start;margin-top:8px;">
                <div id="trFaceStatus" class="tr-state-inactive"><span class="tr-state-dot"></span><i class="fa-solid fa-video-slash"></i><span class="tr-face-status-text">Camera inativa.</span></div>
            </div>
        </div>
    </form>

    <?php if (false): ?>
    <div id="trPainelRegistoFacial" style="display:none; position:fixed; inset:0; z-index:1200; background:rgba(15,23,42,0.68); padding:24px; overflow:auto;">
        <div style="max-width:1100px; margin:0 auto; border:1px solid #dbe3ed; border-radius:10px; padding:10px; background:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:12px;font-weight:800;color:#0f172a;">Lista de Registo Facial (Base da Oficina)</div>
                    <div style="font-size:11px;color:#64748b;">Use apenas quando o sistema mostrar "fazer primeiro registo".</div>
                </div>
                <div style="display:flex; gap:6px; align-items:center;">
                    <button type="button" class="btn-mode" style="background:#14532d;color:#fff;border-color:#14532d;" onclick="trRegistarSelecionadoDaLista()">Registar rosto do selecionado</button>
                    <button type="button" class="btn-mode" onclick="trFecharPainelRegistoFacial()"><i class="fa-solid fa-xmark"></i> Fechar</button>
                </div>
            </div>
            <div style="margin-top:8px;max-height:420px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;">
                <table class="history-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Cargo</th>
                        <th>Template</th>
                        <th>Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($colaboradores_oficina)): ?>
                        <tr><td colspan="4" style="text-align:center;color:#6b7280;padding:10px;">Sem colaboradores.</td></tr>
                    <?php else: ?>
                        <?php foreach($colaboradores_oficina as $coReg): ?>
                            <?php
                                $pidReg = (int)($coReg['id'] ?? 0);
                                $temTplReg = isset($face_templates_map_oficina[$pidReg]) && trim((string)$face_templates_map_oficina[$pidReg]) !== '';
                                $nomeReg = (string)($coReg['nome'] ?? '-');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($nomeReg) ?></td>
                                <td><?= htmlspecialchars((string)($coReg['cargo_nome'] ?? '-')) ?></td>
                                <td>
                                    <?php if($temTplReg): ?>
                                        <span style="font-size:11px;font-weight:700;color:#166534;">Registado</span>
                                    <?php else: ?>
                                        <span style="font-size:11px;font-weight:700;color:#b91c1c;">Por registar</span>
                                    <?php endif; ?>
                                </td>
                                <td><button type="button" class="btn-mode" style="font-size:10px;" onclick="trSelecionarColaboradorRegisto('<?= $pidReg ?>')">Selecionar</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode((string) $data_assiduidade) ?>" id="trAutoRegForm" style="display:none;">
        <input type="hidden" name="acao_presencas" value="registrar_face_template">
        <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string) $data_assiduidade) ?>">
        <input type="hidden" name="pessoal_id" id="trFaceRegPessoal" value="">
        <input type="hidden" name="template_payload" id="trFaceTemplatePayload" value="">
    </form>

    <?php if(false): ?>
        <form method="POST" action="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode((string) $data_assiduidade) ?>" style="margin-bottom:14px;">
            <input type="hidden" name="acao_presencas" value="marcar_presenca_lote">
            <input type="hidden" name="data_presenca" value="<?= htmlspecialchars((string) $data_assiduidade) ?>">
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
                        <th>Cargo</th>
                        <th>Entrada</th>
                        <th>Hora Entrada</th>
                        <th>Saida</th>
                        <th>Hora Saida</th>
                        <th>Observacoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($colaboradores_oficina)): ?>
                        <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:12px;">Sem funcionarios para marcacao.</td></tr>
                    <?php else: ?>
                        <?php foreach($colaboradores_oficina as $col): ?>
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
    <?php endif; ?>

    <?php if(!$ocultar_marcacao_manual_presencas): ?>
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
                                    <a href="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode($dataLista) ?>&hist_data=<?= urlencode($dataLista) ?>" class="btn-mode" style="font-size:10px; margin-right:6px;">Ver historico</a>
                                    <?php if(!$enviadoTodos): ?>
                                        <form method="POST" enctype="multipart/form-data" action="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode($dataLista) ?>" style="display:inline; margin-right:6px;">
                                            <input type="hidden" name="acao_presencas" value="anexar_lista_fisica">
                                            <input type="hidden" name="data_presenca" value="<?= htmlspecialchars($dataLista) ?>">
                                            <input type="file" name="lista_fisica_file[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif" style="font-size:10px; width:150px;" required>
                                            <button type="submit" class="btn-mode" style="font-size:10px;">Anexar lista</button>
                                        </form>
                                        <a href="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode($dataLista) ?>" class="btn-mode" style="font-size:10px; margin-right:6px;">Editar</a>
                                        <form method="POST" action="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&data_assiduidade=<?= urlencode($dataLista) ?>" style="display:inline;">
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
    <?php endif; ?>

    <?php if(!$ocultar_marcacao_manual_presencas): ?>
    <div id="painel-lista-dia-presencas" style="display:none; position:fixed; inset:0; z-index:1100; background:rgba(15,23,42,0.68); padding:24px; overflow:auto;">
        <div style="max-width:1150px; margin:0 auto; background:#fff; border-radius:12px; border:1px solid #dbe3ed; padding:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                <div style="font-size:13px; font-weight:800; color:#334155;">Lista de Presencas - <?= $hist_data_oficina !== '' ? htmlspecialchars(date('d/m/Y', strtotime($hist_data_oficina))) : '' ?></div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <?php if($hist_data_oficina !== ''): ?>
                        <a class="btn-mode" style="background:#c2410c; color:#fff;" target="_blank" href="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&doc=presenca_pdf&data_presenca=<?= urlencode($hist_data_oficina) ?>"><i class="fa-solid fa-file-pdf"></i> Baixar PDF</a>
                        <a class="btn-mode" style="background:#166534; color:#fff;" target="_blank" href="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&doc=presenca_excel&data_presenca=<?= urlencode($hist_data_oficina) ?>"><i class="fa-solid fa-file-excel"></i> Baixar Excel</a>
                        <a class="btn-mode" style="background:#1d4ed8; color:#fff;" target="_blank" href="?tab=<?= urlencode((string) $tab) ?>&view=presencas&mode=list&aplicar=1&doc=presenca_word&data_presenca=<?= urlencode($hist_data_oficina) ?>"><i class="fa-solid fa-file-word"></i> Baixar Word</a>
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
                                <td><?= htmlspecialchars((string) ($prh['cargo_nome'] ?? ($prh['funcao'] ?? '-'))) ?></td>
                                <td><?= !empty($prh['hora_entrada']) ? htmlspecialchars(substr((string) $prh['hora_entrada'], 0, 5)) : '-' ?></td>
                                <td><?= !empty($prh['hora_saida']) ? htmlspecialchars(substr((string) $prh['hora_saida'], 0, 5)) : '-' ?></td>
                                <td><?= htmlspecialchars((string) ($prh['status_presenca'] ?? ($prh['estado'] ?? '-'))) ?></td>
                                <td><?= (int) ($prh['enviado_rh'] ?? 0) === 1 ? 'Sim' : 'Nao' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
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
var trFaceStream = null;
var trFaceApiReady = false;
var trFaceMatcher = null;
var trLivenessScore = 0;
var trAutoRunning = false;
var trNoseTrack = [];
var trLastDescriptor = null;
var trPresenceProgress = 0;
var TR_FACE_MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@latest/model/';
var TR_FACE_CONF_MIN = 72;
var TR_FACE_LIVE_MIN = 65;
var TR_FACE_ASSIST_CONF_MIN = 56;
var TR_FACE_ASSIST_LIVE_MIN = 60;
var trMpReady = false;
var trMpFaceMesh = null;
var trMpLoopRunning = false;
var trMpLandmarks = null;
var trFaceFeedbackTimer = null;
var trAutoLoopTimer = null;
var trLastDeniedNotifyAt = 0;
var trFaceOverlayStatusTimer = null;
var trFaceProgressVisual = 0;
var trFaceProgressAnimId = null;
var trFaceLastRenderedProgress = 0;
var trLastSuccessBeepAt = 0;
var trFaceDecisionTimer = null;
var trFaceMatchedName = '';
var TR_FACE_DEVICE_STORAGE_KEY = 'tr_face_default_device_id_v1';
var trLastSpeechMap = {};
function trGetDefaultFaceDeviceId() {
    try { return String(localStorage.getItem(TR_FACE_DEVICE_STORAGE_KEY) || ''); } catch (e) { return ''; }
}
function trSetDefaultFaceDeviceId(deviceId) {
    var id = String(deviceId || '');
    try {
        if (id === '') localStorage.removeItem(TR_FACE_DEVICE_STORAGE_KEY);
        else localStorage.setItem(TR_FACE_DEVICE_STORAGE_KEY, id);
    } catch (e) {}
}
function trApplyPreferredCameraSelection(sel, preferredId) {
    if (!sel || !sel.options || sel.options.length === 0) return false;
    var target = String(preferredId || '');
    if (target === '') return false;
    for (var i = 0; i < sel.options.length; i++) {
        if (String(sel.options[i].value || '') === target) {
            sel.selectedIndex = i;
            return true;
        }
    }
    return false;
}
function trPlaySuccessBeep() {
    try {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        var ctx = new Ctx();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1174, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.06, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.14);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.15);
        osc.onended = function() { try { ctx.close(); } catch (e) {} };
    } catch (e) {}
}
function trSpeakStatus(msg, key, minIntervalMs) {
    try {
        var txt = String(msg || '').trim();
        if (txt === '') return;
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') return;
        var k = String(key || 'geral');
        var now = Date.now();
        var minMs = Math.max(300, Number(minIntervalMs || 1200));
        var last = Number(trLastSpeechMap[k] || 0);
        if ((now - last) < minMs) return;
        trLastSpeechMap[k] = now;
        var utter = new SpeechSynthesisUtterance(txt);
        utter.lang = 'pt-PT';
        utter.rate = 0.95;
        utter.pitch = 1.0;
        utter.volume = 1.0;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utter);
    } catch (e) {}
}
function trShowFaceDecision(tipoBatida, nomeColaborador, ultraAssistido) {
    var el = document.getElementById('trFaceDecision');
    if (!el) return;
    var tipoRaw = String(tipoBatida || '').toLowerCase();
    var tipo = (tipoRaw === 'saida') ? 'Saida' : ((tipoRaw === 'entrada') ? 'Entrada' : 'Presenca');
    var nome = String(nomeColaborador || 'Colaborador');
    var modo = ultraAssistido ? 'Registo RH confirmado (modo assistido)' : 'Registo RH confirmado';
    el.innerHTML =
        '<div class="line1"><i class="fa-solid fa-circle-check"></i>' + tipo + ' aceite</div>' +
        '<div class="line2">' + nome + '</div>' +
        '<div class="line3">' + modo + '</div>';
    el.classList.add('tr-show');
    if (trFaceDecisionTimer) clearTimeout(trFaceDecisionTimer);
    trFaceDecisionTimer = setTimeout(function() {
        el.classList.remove('tr-show');
    }, 3600);
}
function trSetFaceOverlayStatus(msg, erro) {
    var el = document.getElementById('trFaceOverlayStatus');
    if (!el) return;
    var txt = String(msg || '').trim();
    if (txt === '') {
        el.className = '';
        el.textContent = '';
        return;
    }
    el.textContent = txt;
    el.className = 'tr-show ' + (erro ? 'tr-error' : 'tr-success');
    if (trFaceOverlayStatusTimer) clearTimeout(trFaceOverlayStatusTimer);
    var closeAfter = erro ? 4200 : 2200;
    trFaceOverlayStatusTimer = setTimeout(function() {
        el.className = '';
    }, closeAfter);
}
function trSetFaceLiveName(nome) {
    var el = document.getElementById('trFaceLiveName');
    if (!el) return;
    var txt = String(nome || '').trim();
    if (txt === '') {
        el.classList.remove('tr-show');
        el.textContent = '';
        return;
    }
    el.textContent = 'Colaborador: ' + txt;
    el.classList.add('tr-show');
}
function trSetFaceStatus(msg, erro) {
    var el = document.getElementById('trFaceStatus');
    if (!el) return;
    var raw = String(msg || 'Camera inativa.');
    var inactive = /inativa/i.test(raw);
    var txt = inactive ? 'Camera inativa.' : 'Camera ativa. Aguardando rosto...';
    var icon = inactive ? 'fa-video-slash' : 'fa-camera';
    var cls = inactive ? 'tr-state-inactive' : 'tr-state-active';
    el.className = cls;
    el.innerHTML = '<span class="tr-state-dot"></span><i class="fa-solid ' + icon + '"></i><span class="tr-face-status-text">' + txt + '</span>';
    el.setAttribute('aria-label', txt);
    trSetFaceOverlayStatus(raw, !!erro);
}
function trShowFaceFeedback(type, msg) {
    var el = document.getElementById('trFaceFeedback');
    if (!el) return;
    el.className = '';
    var icon = type === 'success' ? '&#10003;' : '&#10005;';
    el.innerHTML = '<div class="tr-face-row"><div class="tr-face-icon">' + icon + '</div><div class="tr-face-msg">' + String(msg || '') + '</div></div>';
    el.classList.add(type === 'success' ? 'tr-success' : 'tr-error');
    el.classList.add('tr-show');
    if (trFaceFeedbackTimer) clearTimeout(trFaceFeedbackTimer);
    trFaceFeedbackTimer = setTimeout(function() {
        el.classList.remove('tr-show');
    }, 2400);
}
function trScheduleAutoReconhecer(delayMs) {
    if (trAutoLoopTimer) clearTimeout(trAutoLoopTimer);
    trAutoLoopTimer = setTimeout(function() {
        if (trFaceStream) trAutoReconhecer();
    }, Math.max(120, Number(delayMs || 300)));
}
function trStageByProgress(p, done) {
    if (done || p >= 100) return 'Estado: impressao validada e conclusao da autenticacao';
    if (p >= 85) return 'Estado: finalizando verificacao biometrica';
    if (p >= 65) return 'Estado: cruzando template e liveness 3D';
    if (p >= 40) return 'Estado: capturando vetores faciais';
    if (p >= 20) return 'Estado: mapeando pontos da face';
    return 'Estado: procurando rosto no quadro';
}
function trBuildFacePalette(pv, rising) {
    var h1 = 204 - (pv * 0.58);
    var h2 = 168 - (pv * 0.48);
    if (pv >= 92) {
        h1 = 132;
        h2 = 96;
    }
    h1 = Math.max(104, Math.min(210, h1));
    h2 = Math.max(84, Math.min(178, h2));
    var sat = rising ? 92 : 84;
    var startL = rising ? 52 : 48;
    var endL = rising ? 42 : 38;
    var glowAlpha = rising ? 0.48 : 0.30;
    var ringGlowAlpha = rising ? 0.58 : 0.34;
    return {
        start: 'hsl(' + h1.toFixed(0) + ', ' + sat + '%, ' + startL + '%)',
        end: 'hsl(' + h2.toFixed(0) + ', ' + (sat - 6) + '%, ' + endL + '%)',
        softStart: 'hsla(' + h1.toFixed(0) + ', 95%, 54%, 0.20)',
        softEnd: 'hsla(' + h2.toFixed(0) + ', 92%, 46%, 0.12)',
        glow: 'hsla(' + h1.toFixed(0) + ', 96%, 50%, ' + glowAlpha + ')',
        ringGlow: 'hsla(' + h1.toFixed(0) + ', 96%, 48%, ' + ringGlowAlpha + ')'
    };
}
function trRenderFaceProgress(p, msg, done) {
    var txt = document.getElementById('trFacePct');
    var progressBar = document.getElementById('trFacePctBar');
    var ready = document.getElementById('trFaceReady');
    var ring = document.getElementById('trFacePctRing');
    var stage = document.getElementById('trFaceStage');
    var pv = Math.max(0, Math.min(100, Number(p || 0)));
    var delta = pv - trFaceLastRenderedProgress;
    var rising = delta > 0.25;
    var palette = trBuildFacePalette(pv, rising);
    if (txt) txt.textContent = pv.toFixed(0) + '%';
    if (progressBar) {
        progressBar.style.width = pv.toFixed(0) + '%';
        progressBar.style.background = 'linear-gradient(90deg, ' + palette.start + ', ' + palette.end + ')';
        progressBar.style.boxShadow = '0 0 0 1px rgba(255,255,255,.2) inset, 0 0 ' + (rising ? 18 : 10) + 'px ' + palette.glow;
        progressBar.classList.toggle('tr-rise', rising);
    }
    if (ring) {
        var c = 314;
        ring.style.strokeDasharray = String(c);
        ring.style.strokeDashoffset = String(c - ((pv / 100) * c));
        ring.style.filter = 'drop-shadow(0 0 ' + (rising ? 10 : 6) + 'px ' + palette.ringGlow + ')';
    }
    var ringStart = document.getElementById('trFaceRingStart');
    if (ringStart) ringStart.setAttribute('stop-color', palette.start);
    var ringEnd = document.getElementById('trFaceRingEnd');
    if (ringEnd) ringEnd.setAttribute('stop-color', palette.end);
    if (ready) {
        ready.textContent = msg || (done ? 'Impressao presente.' : 'Aguardando leitura facial...');
        ready.style.color = done ? '#15803d' : palette.end;
    }
    if (stage) stage.textContent = trStageByProgress(pv, done);
    trSetFaceLiveName(trFaceMatchedName);
    var growth = document.getElementById('trFaceGrowth');
    if (growth) {
        growth.style.background = 'linear-gradient(180deg, ' + palette.softStart + ', ' + palette.softEnd + '), rgba(255,255,255,.52)';
        growth.classList.toggle('tr-rise', rising);
        var barsWrap = growth.querySelector('.tr-growth-bars');
        if (!barsWrap) {
            growth.innerHTML = '<div class="tr-growth-bars"></div>';
            barsWrap = growth.querySelector('.tr-growth-bars');
            if (barsWrap) {
                var heights = [16, 22, 28, 34, 40, 48, 56, 64, 72, 80, 88, 96];
                for (var bi = 0; bi < heights.length; bi++) {
                    var seg = document.createElement('span');
                    seg.style.height = heights[bi] + '%';
                    barsWrap.appendChild(seg);
                }
            }
        }
        if (barsWrap) {
            var totalBars = barsWrap.children.length || 8;
            var activeBars = Math.max(0, Math.min(totalBars, Math.round((pv / 100) * totalBars)));
            for (var i = 0; i < totalBars; i++) {
                var b = barsWrap.children[i];
                if (!b) continue;
                b.classList.toggle('active', i < activeBars);
                b.style.opacity = (i < activeBars) ? '1' : '0.72';
                b.style.background = (i < activeBars)
                    ? 'linear-gradient(180deg, ' + palette.start + ', ' + palette.end + ')'
                    : 'rgba(100,116,139,.32)';
                b.style.boxShadow = (i < activeBars)
                    ? '0 0 0 1px rgba(255,255,255,.25) inset, 0 0 ' + (rising ? 12 : 7) + 'px ' + palette.glow
                    : 'none';
                b.style.transform = (rising && i === (activeBars - 1)) ? 'translateY(-2px)' : 'translateY(0)';
                b.style.filter = (rising && i === (activeBars - 1)) ? 'brightness(1.2)' : 'none';
            }
        }
    }
    trFaceLastRenderedProgress = pv;
}
function trSetFaceProgress(pct, msg, done) {
    var p = Math.max(0, Math.min(100, Number(pct || 0)));
    trPresenceProgress = p;
    if (done && p >= 100) {
        var now = Date.now();
        if ((now - trLastSuccessBeepAt) > 1400) {
            trLastSuccessBeepAt = now;
            trPlaySuccessBeep();
        }
    }
    if (trFaceProgressAnimId) cancelAnimationFrame(trFaceProgressAnimId);
    var step = function() {
        var delta = p - trFaceProgressVisual;
        if (Math.abs(delta) < 0.35) {
            trFaceProgressVisual = p;
            trRenderFaceProgress(trFaceProgressVisual, msg, done);
            trFaceProgressAnimId = null;
            return;
        }
        trFaceProgressVisual += delta * 0.18;
        trRenderFaceProgress(trFaceProgressVisual, msg, done);
        trFaceProgressAnimId = requestAnimationFrame(step);
    };
    step();
}
function trResetFaceProgress() {
    trFaceMatchedName = '';
    trSetFaceLiveName('');
    trFaceProgressVisual = 0;
    trFaceLastRenderedProgress = 0;
    trSetFaceProgress(0, 'Aguardando leitura facial...', false);
}
function trSetUltraAssist(flag) {
    var el = document.getElementById('trFaceUltra');
    if (el) el.value = flag ? '1' : '0';
}
function trDetectorOpts() {
    return new faceapi.TinyFaceDetectorOptions({ inputSize: 608, scoreThreshold: 0.32 });
}
async function trLoadFaceApi() {
    if (trFaceApiReady && window.faceapi) return true;
    if (!window.faceapi) {
        await new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@latest/dist/face-api.min.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        }).catch(function() {
            trSetFaceStatus('Falha ao carregar motor de reconhecimento facial.', true);
        });
    }
    if (!window.faceapi) return false;
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(TR_FACE_MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(TR_FACE_MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(TR_FACE_MODEL_URL);
        trFaceApiReady = true;
        return true;
    } catch (e) {
        trSetFaceStatus('Falha ao carregar modelos de reconhecimento.', true);
        return false;
    }
}
async function trLoadScriptOnce(id, src) {
    if (document.getElementById(id)) return true;
    return await new Promise(function(resolve) {
        var s = document.createElement('script');
        s.id = id;
        s.src = src;
        s.onload = function(){ resolve(true); };
        s.onerror = function(){ resolve(false); };
        document.head.appendChild(s);
    });
}
async function trLoadMediaPipeFaceMesh() {
    if (trMpReady && trMpFaceMesh) return true;
    var ok1 = await trLoadScriptOnce('tr-mp-facemesh', 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js');
    var ok2 = await trLoadScriptOnce('tr-mp-camerautils', 'https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js');
    if (!ok1 || !ok2 || !window.FaceMesh) return false;
    try {
        trMpFaceMesh = new FaceMesh({
            locateFile: function(file) {
                return 'https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/' + file;
            }
        });
        trMpFaceMesh.setOptions({
            maxNumFaces: 1,
            refineLandmarks: true,
            minDetectionConfidence: 0.6,
            minTrackingConfidence: 0.6
        });
        trMpFaceMesh.onResults(function(results) {
            var lm = (results && results.multiFaceLandmarks && results.multiFaceLandmarks[0]) ? results.multiFaceLandmarks[0] : null;
            trMpLandmarks = lm || null;
            if (lm && lm.length) {
                trDrawMeshOverlay(lm);
            } else {
                trClearFaceOverlay();
            }
        });
        trMpReady = true;
        return true;
    } catch (e) {
        return false;
    }
}
function trDrawMeshOverlay(landmarks) {
    var video = document.getElementById('trFaceVideo');
    var ov = document.getElementById('trFaceOverlay');
    if (!video || !ov || !landmarks || !landmarks.length) return;
    var w = Math.max(1, video.clientWidth || video.videoWidth || 1);
    var h = Math.max(1, video.clientHeight || video.videoHeight || 1);
    var dpr = Math.max(1, window.devicePixelRatio || 1);
    var rw = Math.round(w * dpr);
    var rh = Math.round(h * dpr);
    if (ov.width !== rw || ov.height !== rh) {
        ov.width = rw;
        ov.height = rh;
    }
    var ctx = ov.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    var tess = window.FACEMESH_TESSELATION || [];
    ctx.strokeStyle = 'rgba(56,189,248,0.9)';
    ctx.lineWidth = 1.05;
    for (var i = 0; i < tess.length; i++) {
        var a = landmarks[tess[i][0]];
        var b = landmarks[tess[i][1]];
        if (!a || !b) continue;
        ctx.beginPath();
        ctx.moveTo(a.x * w, a.y * h);
        ctx.lineTo(b.x * w, b.y * h);
        ctx.stroke();
    }
    ctx.fillStyle = 'rgba(34,211,238,0.96)';
    for (var p = 0; p < landmarks.length; p++) {
        var pt = landmarks[p];
        if (!pt) continue;
        ctx.beginPath();
        ctx.arc(pt.x * w, pt.y * h, 1.6, 0, Math.PI * 2);
        ctx.fill();
    }
}
async function trStartMeshLoop() {
    if (trMpLoopRunning) return;
    var ok = await trLoadMediaPipeFaceMesh();
    if (!ok || !trMpFaceMesh) return;
    var video = document.getElementById('trFaceVideo');
    if (!video) return;
    trMpLoopRunning = true;
    (async function loop() {
        while (trMpLoopRunning) {
            try {
                if (trFaceStream && video.videoWidth > 0 && video.videoHeight > 0) {
                    // eslint-disable-next-line no-await-in-loop
                    await trMpFaceMesh.send({ image: video });
                }
            } catch (e) {}
            // eslint-disable-next-line no-await-in-loop
            await new Promise(function(r){ setTimeout(r, 80); });
        }
    })();
}
function trStopMeshLoop() {
    trMpLoopRunning = false;
    trMpLandmarks = null;
}
function trGetCameraForm() {
    var h = document.querySelector('form input[name="acao_presencas"][value="marcar_presenca_camera"]');
    return h ? h.closest('form') : null;
}
function trGetRegForm() {
    return document.getElementById('trAutoRegForm');
}
function trSelectPresencaColaborador() {
    var frm = trGetCameraForm();
    return frm ? frm.querySelector('select[name="pessoal_id"]') : null;
}
function trGetTipoBatidaInput() {
    return document.getElementById('trFaceTipoBatida');
}
function trGetBatidaManualInput() {
    return document.getElementById('trFaceBatidaManual');
}
function trAtualizarBotoesBatida() {
    var tipo = (trGetTipoBatidaInput() && trGetTipoBatidaInput().value) ? String(trGetTipoBatidaInput().value) : 'Entrada';
    var manual = trGetBatidaManualInput() && String(trGetBatidaManualInput().value) === '1';
    var bIn = document.getElementById('trBtnEntrada');
    var bOut = document.getElementById('trBtnSaida');
    var info = document.getElementById('trBatidaModoInfo');
    if (bIn) bIn.style.opacity = (tipo === 'Entrada') ? '1' : '0.75';
    if (bOut) bOut.style.opacity = (tipo === 'Saida') ? '1' : '0.75';
    if (info) info.textContent = manual ? ('Modo manual: ' + tipo) : 'Modo: Automatico';
}
function trSelecionarBatidaManual(tipo) {
    var inTipo = trGetTipoBatidaInput();
    var inManual = trGetBatidaManualInput();
    if (inTipo) inTipo.value = (String(tipo) === 'Saida') ? 'Saida' : 'Entrada';
    if (inManual) inManual.value = '1';
    trAtualizarBotoesBatida();
    if (!trFaceStream) trAbrirCamera();
}
function trAtivarBatidaAuto() {
    var inManual = trGetBatidaManualInput();
    if (inManual) inManual.value = '0';
    trAtualizarBotoesBatida();
    if (!trFaceStream) trAbrirCamera();
}
function trAtualizarDataHoraAuto() {
    var el = document.getElementById('trDataHoraAuto');
    if (!el) return;
    var d = new Date();
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var yyyy = d.getFullYear();
    var hh = String(d.getHours()).padStart(2, '0');
    var mi = String(d.getMinutes()).padStart(2, '0');
    var ss = String(d.getSeconds()).padStart(2, '0');
    el.textContent = 'Data automatica: ' + dd + '/' + mm + '/' + yyyy + ' | Hora: ' + hh + ':' + mi + ':' + ss;
}
function trAbrirPainelRegistoFacial() {
    trSetFaceStatus('Registo facial desativado neste painel. Fazer cadastro no RH.', true);
}
function trFecharPainelRegistoFacial() {
    var el = document.getElementById('trPainelRegistoFacial');
    if (el) el.style.display = 'none';
}
function trSetBatidaInfo(msg) {
    var el = document.getElementById('trFaceBatidaInfo');
    if (el) el.textContent = msg || 'Aguardando rosto...';
}
function trGetSelectedOption() {
    var sel = trSelectPresencaColaborador();
    if (!sel || sel.selectedIndex < 0) return null;
    return sel.options[sel.selectedIndex] || null;
}
function trGetOptionByPid(pid) {
    var sel = trSelectPresencaColaborador();
    if (!sel) return null;
    var val = String(pid || '');
    for (var i = 0; i < sel.options.length; i++) {
        if (String(sel.options[i].value || '') === val) return sel.options[i];
    }
    return null;
}
function trResolveBatidaAutomatica(op) {
    var manual = trGetBatidaManualInput();
    var tipoInput = trGetTipoBatidaInput();
    if (manual && String(manual.value) === '1' && tipoInput) {
        return (String(tipoInput.value) === 'Saida') ? 'Saida' : 'Entrada';
    }
    if (!op) return '';
    var inOk = Number(op.getAttribute('data-assinou-entrada') || '0') === 1;
    var outOk = Number(op.getAttribute('data-assinou-saida') || '0') === 1;
    if (!inOk) return 'Entrada';
    if (!outOk) return 'Saida';
    return '';
}
function trClearFaceOverlay() {
    var ov = document.getElementById('trFaceOverlay');
    if (!ov) return;
    var ctx = ov.getContext('2d');
    if (!ctx) return;
    ctx.clearRect(0, 0, ov.width || 0, ov.height || 0);
}
function trDrawFaceOverlay(det) {
    var video = document.getElementById('trFaceVideo');
    var ov = document.getElementById('trFaceOverlay');
    if (!video || !ov) return;
    var w = Math.max(1, video.clientWidth || video.videoWidth || 1);
    var h = Math.max(1, video.clientHeight || video.videoHeight || 1);
    var dpr = Math.max(1, window.devicePixelRatio || 1);
    var rw = Math.round(w * dpr);
    var rh = Math.round(h * dpr);
    if (ov.width !== rw || ov.height !== rh) {
        ov.width = rw;
        ov.height = rh;
    }
    var ctx = ov.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    if (!det || !det.landmarks || !det.landmarks.positions) return;
    var resized = (window.faceapi && faceapi.resizeResults) ? faceapi.resizeResults(det, { width: w, height: h }) : det;
    var points = resized.landmarks.positions;
    ctx.strokeStyle = 'rgba(56,189,248,0.9)';
    ctx.fillStyle = 'rgba(34,211,238,0.85)';
    ctx.lineWidth = 1.2;
    var groups = [
        resized.landmarks.getJawOutline ? resized.landmarks.getJawOutline() : [],
        resized.landmarks.getLeftEye ? resized.landmarks.getLeftEye() : [],
        resized.landmarks.getRightEye ? resized.landmarks.getRightEye() : [],
        resized.landmarks.getNose ? resized.landmarks.getNose() : [],
        resized.landmarks.getMouth ? resized.landmarks.getMouth() : [],
        resized.landmarks.getLeftEyeBrow ? resized.landmarks.getLeftEyeBrow() : [],
        resized.landmarks.getRightEyeBrow ? resized.landmarks.getRightEyeBrow() : []
    ];
    for (var g = 0; g < groups.length; g++) {
        var grp = groups[g];
        if (!grp || grp.length < 2) continue;
        ctx.beginPath();
        ctx.moveTo(grp[0].x, grp[0].y);
        for (var i = 1; i < grp.length; i++) ctx.lineTo(grp[i].x, grp[i].y);
        ctx.stroke();
    }
    for (var p = 0; p < points.length; p++) {
        var pt = points[p];
        ctx.beginPath();
        ctx.arc(pt.x, pt.y, 1.8, 0, Math.PI * 2);
        ctx.fill();
    }
}
async function trBuildFaceMatcher() {
    var sel = trSelectPresencaColaborador();
    if (!sel) return null;
    var ok = await trLoadFaceApi();
    if (!ok) return null;
    if (trFaceMatcher) return trFaceMatcher;
    var labels = [];
    for (var i = 0; i < sel.options.length; i++) {
        var op = sel.options[i];
        var pid = String(op.value || '');
        var tpl = String(op.getAttribute('data-face-template') || '');
        var nome = String(op.getAttribute('data-face-name') || op.textContent || '');
        if (pid === '') continue;
        var descritores = [];
        if (tpl !== '') {
            try {
                var objTpl = JSON.parse(tpl);
                if (objTpl && Array.isArray(objTpl.descriptor)) descritores.push(new Float32Array(objTpl.descriptor));
            } catch (e) {}
        }
        if (descritores.length > 0) labels.push(new faceapi.LabeledFaceDescriptors(pid + '|' + nome, descritores));
    }
    if (labels.length === 0) return null;
    trFaceMatcher = new faceapi.FaceMatcher(labels, 0.6);
    return trFaceMatcher;
}
async function trAtualizarCameras() {
    var sel = document.getElementById('trFaceDevice');
    if (!sel || !navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
    try {
        var selectedBefore = String(sel.value || '');
        var defaultSaved = trGetDefaultFaceDeviceId();
        var devices = await navigator.mediaDevices.enumerateDevices();
        var cams = devices.filter(function(d){ return d.kind === 'videoinput'; });
        sel.innerHTML = '';
        if (cams.length === 0) {
            var op0 = document.createElement('option');
            op0.value = '';
            op0.textContent = 'Nenhuma camera encontrada';
            sel.appendChild(op0);
            return;
        }
        cams.forEach(function(cam, idx){
            var op = document.createElement('option');
            op.value = cam.deviceId || '';
            op.textContent = cam.label || ('Camera ' + (idx + 1));
            sel.appendChild(op);
        });
        var picked = trApplyPreferredCameraSelection(sel, selectedBefore);
        if (!picked) picked = trApplyPreferredCameraSelection(sel, defaultSaved);
        if (!picked && cams.length > 0) sel.selectedIndex = 0;
        var finalSelected = String(sel.value || '');
        if (finalSelected !== '') trSetDefaultFaceDeviceId(finalSelected);
    } catch (e) {
        trSetFaceStatus('Falha ao listar cameras.', true);
    }
}
async function trAbrirCamera() {
    var video = document.getElementById('trFaceVideo');
    if (!video || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
    try {
        trFecharCamera();
        var sel = document.getElementById('trFaceDevice');
        var devId = sel ? String(sel.value || '') : '';
        if (devId !== '') trSetDefaultFaceDeviceId(devId);
        var cons = devId !== '' ? { video: { deviceId: { exact: devId } }, audio: false } : { video: { facingMode: 'user' }, audio: false };
        var st = await navigator.mediaDevices.getUserMedia(cons);
        trFaceStream = st;
        video.srcObject = st;
        await video.play().catch(function(){});
        if (sel && devId === '' && trFaceStream && trFaceStream.getVideoTracks && trFaceStream.getVideoTracks()[0]) {
            var trackSettings = trFaceStream.getVideoTracks()[0].getSettings ? trFaceStream.getVideoTracks()[0].getSettings() : null;
            var activeId = trackSettings && trackSettings.deviceId ? String(trackSettings.deviceId) : '';
            if (activeId !== '' && trApplyPreferredCameraSelection(sel, activeId)) trSetDefaultFaceDeviceId(activeId);
        }
        trFaceMatcher = null;
        trNoseTrack = [];
        trResetFaceProgress();
        trStartMeshLoop();
        trSetFaceStatus('Camera ativa. Leitura facial automatica iniciada.', false);
        trScheduleAutoReconhecer(900);
    } catch (e) {
        trSetFaceStatus('Nao foi possivel abrir camera. Verifique permissao do navegador.', true);
    }
}
function trFecharCamera() {
    var video = document.getElementById('trFaceVideo');
    if (trFaceStream) {
        trFaceStream.getTracks().forEach(function(t){ t.stop(); });
        trFaceStream = null;
    }
    if (video) video.srcObject = null;
    trStopMeshLoop();
    trClearFaceOverlay();
    trResetFaceProgress();
    if (trAutoLoopTimer) clearTimeout(trAutoLoopTimer);
    trAutoLoopTimer = null;
    trAutoRunning = false;
    trSetFaceStatus('Camera inativa.', false);
}
async function trCapturarFace() {
    var video = document.getElementById('trFaceVideo');
    var canvas = document.getElementById('trFaceCanvas');
    var out = document.getElementById('trFacePayload');
    if (!canvas || !out) return;
    if (!video || video.videoWidth <= 0 || video.videoHeight <= 0) return;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    if (!trLastDescriptor && trFaceApiReady) {
        try {
            var det = await faceapi.detectSingleFace(video, trDetectorOpts()).withFaceLandmarks().withFaceDescriptor();
            if (det && det.descriptor) trLastDescriptor = Array.from(det.descriptor);
        } catch (e) {}
    }
    out.value = JSON.stringify({
        origem: 'camera_oficina',
        data: new Date().toISOString(),
        largura: canvas.width,
        altura: canvas.height,
        imagem_base64: canvas.toDataURL('image/jpeg', 0.7),
        liveness_score: trLivenessScore,
        descriptor: trLastDescriptor
    });
}
function trLivenessFromTrack() {
    if (trNoseTrack.length < 4) return 60;
    var amp = Math.max.apply(null, trNoseTrack) - Math.min.apply(null, trNoseTrack);
    return amp >= 0.08 ? 92 : (amp >= 0.05 ? 80 : 65);
}
function trAverageDescriptor(samples) {
    if (!samples || !samples.length) return null;
    var len = samples[0].length;
    var out = new Float32Array(len);
    for (var i = 0; i < samples.length; i++) {
        for (var j = 0; j < len; j++) out[j] += samples[i][j];
    }
    for (var k = 0; k < len; k++) out[k] = out[k] / samples.length;
    return out;
}
function trSubmitAutoRegistro(pessoalId, avgDescriptor, sampleCount) {
    var frm = trGetRegForm();
    var pid = document.getElementById('trFaceRegPessoal');
    var out = document.getElementById('trFaceTemplatePayload');
    if (!frm || !pid || !out) return false;
    pid.value = String(pessoalId || '');
    out.value = JSON.stringify({
        origem: 'registro_oficina_auto',
        data: new Date().toISOString(),
        quality: 100,
        samples: sampleCount,
        descriptor: Array.from(avgDescriptor || [])
    });
    frm.submit();
    return true;
}
async function trSubmitAutoPresenca() {
    var frm = trGetCameraForm();
    if (!frm) return;
    var payload = document.getElementById('trFacePayload');
    if (payload && String(payload.value || '') === '') await trCapturarFace();
    trSetFaceStatus('Impressao presente. A marcar presenca...', false);
    await new Promise(function(r){ setTimeout(r, 2400); });
    frm.submit();
}
async function trAutoReconhecer() {
    if (trAutoRunning) return;
    var video = document.getElementById('trFaceVideo');
    var sel = trSelectPresencaColaborador();
    if (!video || !sel) return;
    if (!trFaceStream) await trAbrirCamera();
    var ok = await trLoadFaceApi();
    if (!ok) return;
    trAutoRunning = true;
    try {
        var matcher = null;
        trSetUltraAssist(false);
        trSetFaceProgress(5, 'Aproxime o rosto da camera...', false);
        trSetBatidaInfo('Aguardando rosto...');
        for (var t = 0; t < 35; t++) {
            var det = null;
            var nomeNoQuadro = '';
            try {
                // eslint-disable-next-line no-await-in-loop
                det = await faceapi.detectSingleFace(video, trDetectorOpts()).withFaceLandmarks().withFaceDescriptor();
            } catch (eDet) {
                det = null;
            }
            var alvo = 6;
            var info = 'Procurando rosto...';
            if (!trMpLandmarks) trDrawFaceOverlay(det || null);
            if (det && det.landmarks && det.landmarks.getNose && det.landmarks.getNose()[3]) {
                var nx = det.landmarks.getNose()[3].x / Math.max(1, video.videoWidth);
                trNoseTrack.push(nx);
                if (trNoseTrack.length > 16) trNoseTrack.shift();
                trLivenessScore = trLivenessFromTrack();
                document.getElementById('trFaceScoreLive').value = trLivenessScore.toFixed(1);
                alvo = 28 + (trLivenessScore * 0.32);
                if (trLivenessScore >= TR_FACE_LIVE_MIN && det.descriptor) {
                    if (!matcher) matcher = await trBuildFaceMatcher();
                    if (matcher) {
                        var best = matcher.findBestMatch(det.descriptor);
                        if (best && best.label !== 'unknown') {
                            var parts = String(best.label).split('|');
                            var pid = parts[0] || '';
                            var nome = parts.slice(1).join('|') || 'Funcionario';
                            trFaceMatchedName = nome;
                            nomeNoQuadro = nome;
                            var conf = Math.max(0, Math.min(100, (1 - Number(best.distance || 1)) * 100));
                            alvo = 55 + (conf * 0.40);
                            info = 'Validando impressao facial...';
                            if (pid !== '' && conf >= TR_FACE_CONF_MIN) {
                                var opMatch = trGetOptionByPid(pid);
                                if (!opMatch) {
                                    trSetFaceStatus('Acesso negado. Trabalhador nao faz parte deste departamento.', true);
                                    trShowFaceFeedback('error', 'Acesso negado. Encaminhar para registo no RH.');
                                    trSpeakStatus('Acesso negado.', 'acesso_negado', 5000);
                                    return;
                                }
                                var tipoBatida = trResolveBatidaAutomatica(opMatch);
                                sel.value = pid;
                                var batidaInput = trGetTipoBatidaInput();
                                if (batidaInput) batidaInput.value = tipoBatida;
                                var tipoTxt = tipoBatida !== '' ? tipoBatida : 'Batida';
                                trSetBatidaInfo(tipoTxt + ' automatica para ' + nome);
                                document.getElementById('trFaceScoreSim').value = conf.toFixed(1);
                                trLastDescriptor = Array.from(det.descriptor || []);
                                trSetUltraAssist(false);
                                await trCapturarFace();
                                trSetFaceProgress(100, 'Impressao presente.', true);
                                var msgOk = tipoBatida !== '' ? (tipoBatida + ' aceite para ' + nome) : ('Registo aceite para ' + nome);
                                trSetFaceStatus(msgOk + ' | Confianca ' + conf.toFixed(1) + '%.', false);
                                trShowFaceDecision(tipoBatida, nome, false);
                                trShowFaceFeedback('success', (tipoBatida !== '' ? tipoBatida : 'Registo') + ' aceite: ' + nome + '.');
                                trSpeakStatus((tipoBatida === 'Saida' ? 'Saida aceite' : 'Entrada aceite') + ' para ' + nome + '.', tipoBatida === 'Saida' ? 'saida_aceite' : 'entrada_aceite', 1200);
                                await trSubmitAutoPresenca();
                                return;
                            }
                            if (conf >= TR_FACE_ASSIST_CONF_MIN && trLivenessScore >= TR_FACE_ASSIST_LIVE_MIN) {
                                var opAssist = trGetOptionByPid(pid);
                                if (!opAssist) continue;
                                var tipoBatidaAssist = trResolveBatidaAutomatica(opAssist);
                                sel.value = pid;
                                var batidaInputAssist = trGetTipoBatidaInput();
                                if (batidaInputAssist) batidaInputAssist.value = tipoBatidaAssist;
                                var tipoTxtAssist = tipoBatidaAssist !== '' ? tipoBatidaAssist : 'Batida';
                                trSetBatidaInfo(tipoTxtAssist + ' automatica (assistida) para ' + nome);
                                document.getElementById('trFaceScoreSim').value = conf.toFixed(1);
                                trLastDescriptor = Array.from(det.descriptor || []);
                                trSetUltraAssist(true);
                                await trCapturarFace();
                                trSetFaceProgress(100, 'Impressao presente (ultra assistido).', true);
                                var msgOkAssist = tipoBatidaAssist !== '' ? (tipoBatidaAssist + ' aceite (ultra assistido): ' + nome + '.') : ('Registo aceite (ultra assistido): ' + nome + '.');
                                trSetFaceStatus(msgOkAssist, false);
                                trShowFaceDecision(tipoBatidaAssist, nome, true);
                                trShowFaceFeedback('success', (tipoBatidaAssist !== '' ? tipoBatidaAssist : 'Registo') + ' aceite: ' + nome + '.');
                                trSpeakStatus((tipoBatidaAssist === 'Saida' ? 'Saida aceite' : 'Entrada aceite') + ' para ' + nome + '.', tipoBatidaAssist === 'Saida' ? 'saida_aceite' : 'entrada_aceite', 1200);
                                await trSubmitAutoPresenca();
                                return;
                            }
                        }
                    }
                }
            }
            if (alvo > 99) alvo = 99;
            trFaceMatchedName = nomeNoQuadro;
            var delta = Math.max(4, Math.min(12, Math.round((alvo - trPresenceProgress) / 3)));
            var prox = Math.max(trPresenceProgress, Math.min(alvo, trPresenceProgress + delta));
            trSetFaceProgress(prox, info, false);
            // eslint-disable-next-line no-await-in-loop
            await new Promise(function(r){ setTimeout(r, 220); });
        }
        if (trPresenceProgress < 18) trSetFaceProgress(18, 'Leitura incompleta. Reposicione o rosto e tente novamente.', false);
        trSetFaceStatus('Acesso negado. Rosto nao reconhecido. Fazer primeiro registo facial no RH.', true);
        if ((Date.now() - trLastDeniedNotifyAt) > 8000) {
            trLastDeniedNotifyAt = Date.now();
            trShowFaceFeedback('error', 'Acesso negado. Nao reconhecido. Fazer primeiro registo no RH.');
            trSpeakStatus('Acesso negado.', 'acesso_negado', 5000);
        }
        trSetBatidaInfo('Sem reconhecimento');
    } finally {
        trAutoRunning = false;
        if (trFaceStream) trScheduleAutoReconhecer(450);
    }
}
function trSelecionarColaboradorRegisto(pid) {
    var sel = trSelectPresencaColaborador();
    if (!sel) return;
    sel.value = String(pid || '');
    var op = trGetSelectedOption();
    var nome = op ? String(op.getAttribute('data-face-name') || op.textContent || '') : 'colaborador';
    trSetFaceStatus('Selecionado para registo facial: ' + nome + '.', false);
}
async function trRegistarSelecionadoDaLista() {
    var sel = trSelectPresencaColaborador();
    if (!sel || String(sel.value || '') === '') {
        trSetFaceStatus('Selecione um colaborador na lista de registo facial.', true);
        return;
    }
    await trPrepararRegistro();
}
async function trPrepararRegistro() {
    var sel = trSelectPresencaColaborador();
    if (!sel || String(sel.value || '') === '') {
        trSetFaceStatus('Selecione o colaborador para registo.', true);
        return;
    }
    var ok = await trLoadFaceApi();
    if (!ok) return;
    if (!trFaceStream) await trAbrirCamera();
    var samples = [];
    var video = document.getElementById('trFaceVideo');
    for (var s = 0; s < 12 && samples.length < 5; s++) {
        // eslint-disable-next-line no-await-in-loop
        var det = await faceapi.detectSingleFace(video, trDetectorOpts()).withFaceLandmarks().withFaceDescriptor();
        if (det && det.descriptor) samples.push(det.descriptor);
        var pct = Math.min(100, Math.round((samples.length / 5) * 100));
        trSetFaceProgress(pct, 'Primeiro registo facial em progresso...', false);
        // eslint-disable-next-line no-await-in-loop
        await new Promise(function(r){ setTimeout(r, 220); });
    }
    var avg = trAverageDescriptor(samples);
    if (!avg) {
        trSetFaceStatus('Falha no registo facial.', true);
        return;
    }
    trSubmitAutoRegistro(String(sel.value || ''), avg, samples.length);
}
document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-listas-presencas');
    if(el && el.style.display === 'block' && ev.target === el) fecharTelaListasPresencas();
});
document.addEventListener('click', function(ev) {
    var el = document.getElementById('painel-lista-dia-presencas');
    if(el && el.style.display === 'block' && ev.target === el) fecharTelaListaDiaPresencas();
});
<?php if(!$ocultar_marcacao_manual_presencas && $hist_data_oficina !== ''): ?>
document.addEventListener('DOMContentLoaded', function() {
    abrirTelaListasPresencas();
    abrirTelaListaDiaPresencas();
});
<?php endif; ?>
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.tr-alert.success.tr-auto-dismiss');
    if (alerts && alerts.length) {
        alerts.forEach(function(alertEl) {
            setTimeout(function() {
                alertEl.classList.add('tr-fade-out');
                setTimeout(function() {
                    if (alertEl && alertEl.parentNode) alertEl.parentNode.removeChild(alertEl);
                }, 380);
            }, 3400);
        });
    }
    trAtualizarCameras();
    var trDeviceSel = document.getElementById('trFaceDevice');
    if (trDeviceSel) {
        trDeviceSel.addEventListener('change', function() {
            trSetDefaultFaceDeviceId(String(trDeviceSel.value || ''));
        });
    }
    trSetBatidaInfo('Aguardando rosto...');
    trAtualizarBotoesBatida();
    trAtualizarDataHoraAuto();
    setInterval(trAtualizarDataHoraAuto, 1000);
    setTimeout(function(){ trAbrirCamera(); }, 450);
});
</script>

