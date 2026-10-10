<?php
declare(strict_types=1);

/**
 * Scheduled tasks: subscription checks (10/3/1-day warnings, expiry → lock), queued imports, feed refresh.
 * CLI:  php cron.php        HTTP: /cron?key=CRON_KEY  (Hostinger hPanel → Cron Jobs, every 5–15 minutes)
 */
final class CronController
{
    public static function http(): void
    {
        $key = (string) ($_GET['key'] ?? '');
        if (!Config::installed() || !hash_equals((string) Config::get('cron_key', ''), $key)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        noindex();
        echo je(self::run(20.0));
    }

    public static function run(float $budget = 50.0): array
    {
        $t0 = microtime(true);
        $lock = fopen(DATA_DIR . '/tmp/cron.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return ['skipped' => 'already running'];
        Audit::$actor = ['type' => 'system', 'id' => null, 'name' => 'cron'];
        $out = ['subscriptions' => Subscriptions::runChecks(), 'imports' => 0, 'feeds' => 0];
        // Process queued imports across shops within the time budget
        foreach (db_all(DB::platform(), "SELECT * FROM shops WHERE deleted_at IS NULL AND status <> 'deleted'") as $shop) {
            if (!is_file(DB::shopPath((int) $shop['id']))) continue;
            $db = DB::shop((int) $shop['id']);
            foreach (db_col($db, "SELECT id FROM imports WHERE status IN ('queued','processing') ORDER BY id") as $impId) {
                while (microtime(true) - $t0 < $budget) {
                    $p = Importer::processBatch($db, $shop, (int) $impId, Importer::BATCH, 5.0);
                    $out['imports']++;
                    if (in_array($p['status'], ['completed', 'failed', 'missing'], true)) break;
                }
            }
            if (Shops::isOpen($shop) && microtime(true) - $t0 < $budget) {
                foreach (db_all($db, "SELECT * FROM feeds WHERE status = 'active' AND frequency <> 'realtime'") as $feed) {
                    if (!Feeds::allowed($shop, $feed)) continue;
                    $ttl = $feed['frequency'] === 'daily' ? 86400 : 3600;
                    if (!$feed['last_generated_at'] || strtotime($feed['last_generated_at']) < time() - $ttl) {
                        try { Feeds::output($db, $shop, $feed, true); $out['feeds']++; } catch (Throwable $e) { error_log('feed: ' . $e->getMessage()); }
                    }
                }
            }
            DB::closeShop((int) $shop['id']);
        }
        setting_set(DB::platform(), 'cron_last_run', now());
        flock($lock, LOCK_UN);
        return $out + ['seconds' => round(microtime(true) - $t0, 2)];
    }
}
