<?php
set_time_limit(0);
define('MODX_API_MODE', true);
/* Default structure is:
  /extension
  --/script
  ----common.php
  --/lib
  ----modxhelper.class.php
  index.php
*/
require_once dirname(dirname(dirname(__FILE__))).'/index.php';

// Включаем обработку ошибок
$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->initialize('mgr');

if (!defined('AUTHORIZATION_IN_SCRIPT')) {
    define('AUTHORIZATION_IN_SCRIPT', false);
}

if (AUTHORIZATION_IN_SCRIPT !== true && $modx->user->isAuthenticated('mgr') !== true) {
    exit('Access denied');
}

$modx->getService('helper', 'modxHelper', MODX_BASE_PATH.'extension/lib/');
