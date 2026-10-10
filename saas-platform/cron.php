<?php
// CLI cron entry: php /path/to/cron.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT', __DIR__);
require ROOT . '/app/bootstrap.php';
if (!Config::installed()) { fwrite(STDERR, "Not installed\n"); exit(1); }
echo je(CronController::run(50.0)) . PHP_EOL;
