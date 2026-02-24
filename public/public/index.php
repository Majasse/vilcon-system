<?php
/**
 * VILCON SYSTEM
 * Front Controller (Entry Point)
 */

session_start();

/**
 * Caminhos base do sistema
 * Evita problemas de paths no futuro
 */
define('BASE_PATH', dirname(__DIR__));      // vilcon-systemon
define('APP_PATH', BASE_PATH . '/app');     // vilcon-systemon/app

/**
 * Dependências essenciais
 */
require_once APP_PATH . '/config/db.php';
require_once APP_PATH . '/core/auth.php';
require_once APP_PATH . '/core/router.php';
require_once APP_PATH . '/core/access_control.php';

/**
 * Verificação de autenticação
 * Alinhado com login.php
 */
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

/**
 * Utilizador autenticado
 * Redireciona para o primeiro modulo permitido
 */
$perfil = (string)($_SESSION['usuario_perfil'] ?? '');
$modulos = modulosPorPerfil($perfil);
$mapaRotas = [
    'dashboard' => '/vilcon-systemon/public/app/modules/dashboard/index.php',
    'documental' => '/vilcon-systemon/public/app/modules/documental/index.php',
    'oficina' => '/vilcon-systemon/public/app/modules/oficina/index.php',
    'transporte' => '/vilcon-systemon/public/app/modules/transporte/index.php',
    'rh' => '/vilcon-systemon/public/app/modules/rh/index.php',
    'seguranca' => '/vilcon-systemon/public/app/modules/seguranca/index.php',
    'logistica' => '/vilcon-systemon/public/app/modules/logistica/index.php',
    'armazem' => '/vilcon-systemon/public/app/modules/armazem/index.php',
    'aprovacoes' => '/vilcon-systemon/public/app/modules/aprovacoes/index.php',
    'relatorios' => '/vilcon-systemon/public/app/modules/relatorios/index.php',
    'utilizadores' => '/vilcon-systemon/public/app/modules/utilizadores/index.php',
];

$destino = '/vilcon-systemon/public/logout.php';
foreach ($modulos as $modulo) {
    if (isset($mapaRotas[$modulo])) {
        $destino = $mapaRotas[$modulo];
        break;
    }
}

header('Location: ' . $destino);
exit;


