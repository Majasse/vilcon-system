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
 * Redireciona para o dashboard
 */
header('Location: /vilcon-systemon/app/modules/dashboard/index.php');
exit;


