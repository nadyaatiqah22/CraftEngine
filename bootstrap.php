<?php
// Bootstrap: konstanta, folder, run-id untuk log, dan load semua library backend
define('ROOT', __DIR__);
define('DATA_DIR', ROOT . '/data');
define('LOG_DIR', ROOT . '/logs');
define('LOG_FILE', LOG_DIR . '/process.log');
define('INDEX_FILE', DATA_DIR . '/index.json');
foreach ([DATA_DIR, LOG_DIR, ROOT . '/uploads'] as $d) { if (!is_dir($d)) @mkdir($d, 0775, true); }
$GLOBALS['T0'] = microtime(true);
$GLOBALS['RUN_ID'] = substr(md5(uniqid('', true)), 0, 8);
require ROOT . '/lib/logger.php';
require ROOT . '/lib/preprocess.php';
require ROOT . '/lib/engine.php';
require ROOT . '/lib/layout.php';
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
