<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /vilcon-systemon/public/login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

$registos = [
    1 => [
        'identificacao' => 'ABC-100-MC',
        'categoria' => 'Ativo / Obra Pemba',
        'info' => 'Chassi: 9BWZZZ...',
        'data' => '12/02/2026',
    ],
    2 => [
        'identificacao' => 'RM-022-PE',
        'categoria' => 'Alerta / Seguranca',
        'info' => 'Documento: seguro vencido',
        'data' => '08/02/2026',
    ],
    3 => [
        'identificacao' => 'EMP-117',
        'categoria' => 'Pessoal / Motoristas',
        'info' => 'Licenca renovada',
        'data' => '02/02/2026',
    ],
];

$item = $registos[$id] ?? null;
$titulo = 'DOCUMENTAL HISTORICO';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    <style>
        @page { margin: 18mm 12mm; }
        body { font-family: Arial, sans-serif; color: #111111; margin: 0; padding: 0; }
        .pdf-wrap { width: 100%; padding: 10px; box-sizing: border-box; }
        .pdf-header { border: 2px solid #111111; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
        .pdf-strip { height: 10px; background: #f4b400; }
        .pdf-head-content { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #ffffff; }
        .pdf-brand { display: flex; align-items: center; gap: 12px; }
        .pdf-brand img { width: 130px; height: auto; object-fit: contain; }
        .pdf-brand h1 { margin: 0; font-size: 18px; color: #111111; letter-spacing: 0.4px; }
        .pdf-meta { text-align: right; font-size: 11px; color: #333333; }
        .pdf-meta strong { display: block; color: #111111; margin-bottom: 4px; }
        .pdf-cert img { width: 54px; height: auto; object-fit: contain; margin-left: 10px; }
        h2 { margin: 0 0 10px 0; color: #111111; font-size: 14px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #111111;
            color: #f4b400;
            border: 1px solid #111111;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        tbody td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; color: #111111; }
        tbody tr:nth-child(even) td { background: #fff8e1; }
    </style>
    <link rel="stylesheet" href="/vilcon-systemon/public/assets/css/global-loader.css">
</head>

<div id="vilcon-global-loader" class="vilcon-loader-overlay" aria-live="polite" aria-busy="true" aria-label="A processar">
    <div class="vilcon-loader-spinner" role="status" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</div>
    <div class="pdf-wrap">
        <div class="pdf-header">
            <div class="pdf-strip"></div>
            <div class="pdf-head-content">
                <div class="pdf-brand">
                    <img src="/vilcon-systemon/public/assets/img/logo-vilcon.png" alt="Vilcon">
                    <h1>VILCON</h1>
                </div>
                <div style="display:flex; align-items:center;">
                    <div class="pdf-meta">
                        <strong><?= htmlspecialchars($titulo) ?></strong>
                        <span>Emitido em: <?= htmlspecialchars(date('d/m/Y H:i')) ?></span>
                    </div>
                    <div class="pdf-cert"><img src="/vilcon-systemon/public/assets/img/innocertificate.png" alt="INNO Certificate"></div>
                </div>
            </div>
        </div>

        <h2>Relatorio</h2>

        <table>
            <thead>
                <tr>
                    <th>Identificacao / Nome</th>
                    <th>Categoria / Projeto</th>
                    <th>Info Adicional</th>
                    <th>Status / Data</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($item === null): ?>
                    <tr>
                        <td colspan="4">Registo nao encontrado.</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><?= htmlspecialchars($item['identificacao']) ?></td>
                        <td><?= htmlspecialchars($item['categoria']) ?></td>
                        <td><?= htmlspecialchars($item['info']) ?></td>
                        <td><?= htmlspecialchars($item['data']) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
    <script src="/vilcon-systemon/public/assets/js/global-loader.js"></script>
</body>
</html>

