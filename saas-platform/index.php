<?php
/**
 * wechevo e-shop platform — front controller.
 * Local: php -S localhost:8000 index.php
 */
declare(strict_types=1);

define('ROOT', __DIR__);

if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (preg_match('#^/(assets|uploads|templates/[a-z0-9-]+/assets)/#', $path)
        && !preg_match('#\.(php\d?|phtml|phar|htaccess)$#i', $path)) {
        $file = realpath(__DIR__ . $path);
        if ($file && is_file($file) && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)) {
            return false; // let the built-in server stream the static file
        }
    }
}

require ROOT . '/app/bootstrap.php';
App::run();
