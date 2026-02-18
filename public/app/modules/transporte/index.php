<?php
require_once __DIR__ . '/bootstrap/init.php';
require_once __DIR__ . '/application/context.php';

$context = transporteBuildContext();
$tab = $context['tab'];
$view = $context['view'];
$mode = $context['mode'];
$proximo_id_os = $context['proximo_id_os'];

require __DIR__ . '/presentation/pages/module.php';
