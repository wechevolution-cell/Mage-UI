<?php
declare(strict_types=1);

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
const APP_VERSION = '1.0.0';
const DATA_DIR = ROOT . '/data';
const UPLOAD_DIR = ROOT . '/uploads';

mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DATA_DIR . '/logs/php-error.log');
error_reporting(E_ALL);

foreach (['helpers', 'db', 'router', 'auth', 'sanitize', 'markdown', 'upload', 'mailer', 'view', 'seo', 'app'] as $f) {
    require ROOT . "/app/core/$f.php";
}
foreach (glob(ROOT . '/app/platform/*.php') as $f) {
    require $f;
}
foreach (glob(ROOT . '/app/shop/*.php') as $f) {
    require $f;
}
foreach (glob(ROOT . '/app/controllers/*.php') as $f) {
    require $f;
}

$cfg = Config::all();
date_default_timezone_set($cfg['timezone'] ?? 'Europe/Athens');
if (!empty($cfg['debug'])) {
    ini_set('display_errors', '1');
}

set_exception_handler(function (Throwable $e) {
    error_log('[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $debug = !empty(Config::all()['debug']);
    echo View::render('system/error', ['code' => 500, 'title' => 'Κάτι πήγε στραβά',
        'message' => 'Παρουσιάστηκε απρόσμενο σφάλμα. Δοκιμάστε ξανά σε λίγο.',
        'debug' => $debug ? ($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) : null]);
});
