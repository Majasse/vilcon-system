<?php

function transporteBuildContext(): array
{
    $allowedViews = [
        'entrada',
        'pedido_reparacao',
        'checklist',
        'plano_manutencao',
        'avarias',
        'relatorio_atividades',
    ];

    $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'transporte';
    $view = isset($_GET['view']) ? (string) $_GET['view'] : 'entrada';
    $mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'list';

    if (!in_array($view, $allowedViews, true)) {
        $view = 'entrada';
    }

    if (!in_array($mode, ['list', 'form'], true)) {
        $mode = 'list';
    }

    $proximoIdOs = 'OS-' . date('Y') . '-0042';

    return [
        'tab' => $tab,
        'view' => $view,
        'mode' => $mode,
        'proximo_id_os' => $proximoIdOs,
    ];
}
