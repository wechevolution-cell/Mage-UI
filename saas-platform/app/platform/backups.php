<?php
declare(strict_types=1);

/** ZIP backups: tenant DB (consistent copy via VACUUM INTO) + uploads + platform record. Created before destructive actions. */
final class Backups
{
    public static function dir(?int $shopId = null): string
    {
        return ensure_dir(DATA_DIR . '/backups/' . ($shopId ? 'shop_' . $shopId : 'platform'));
    }

    public static function shop(int $shopId, string $reason = 'manual'): array
    {
        if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => 'Η επέκταση ZipArchive δεν είναι διαθέσιμη.'];
        $shop = Shops::find($shopId);
        if (!$shop) return ['ok' => false, 'error' => 'Δεν βρέθηκε κατάστημα'];
        $tmpDb = DATA_DIR . '/tmp/backup-' . $shopId . '-' . bin2hex(random_bytes(3)) . '.sqlite';
        ensure_dir(DATA_DIR . '/tmp');
        if (is_file(DB::shopPath($shopId))) {
            DB::shop($shopId)->exec('VACUUM INTO ' . DB::shop($shopId)->quote($tmpDb));
        }
        $file = self::dir($shopId) . '/' . date('Ymd-His') . '-' . slugify($reason, 30) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return ['ok' => false, 'error' => 'Δεν δημιουργήθηκε το ZIP'];
        if (is_file($tmpDb)) $zip->addFile($tmpDb, 'shop.sqlite');
        $meta = ['shop' => $shop, 'domains' => Shops::domains($shopId), 'subscriptions' => db_all(DB::platform(), 'SELECT * FROM subscriptions WHERE shop_id = ?', [$shopId]),
            'history' => db_all(DB::platform(), 'SELECT * FROM subscription_history WHERE shop_id = ?', [$shopId]), 'created_at' => now(), 'reason' => $reason, 'version' => APP_VERSION];
        $zip->addFromString('platform-record.json', je($meta));
        $up = UPLOAD_DIR . '/' . $shopId;
        if (is_dir($up)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) $zip->addFile($f->getPathname(), 'uploads/' . ltrim(substr($f->getPathname(), strlen($up)), '/\\'));
            }
        }
        $zip->close();
        @unlink($tmpDb);
        Audit::log('backup.created', $shopId, 'backup', basename($file), ['reason' => $reason, 'bytes' => filesize($file)]);
        return ['ok' => true, 'path' => $file];
    }

    public static function platform(string $reason = 'manual'): array
    {
        if (!class_exists('ZipArchive')) return ['ok' => false, 'error' => 'ZipArchive missing'];
        $tmp = DATA_DIR . '/tmp/platform-' . bin2hex(random_bytes(3)) . '.sqlite';
        DB::platform()->exec('VACUUM INTO ' . DB::platform()->quote($tmp));
        $file = self::dir() . '/' . date('Ymd-His') . '-' . slugify($reason, 30) . '.zip';
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmp, 'platform.sqlite');
        if (is_file(Config::path())) $zip->addFile(Config::path(), 'config.php');
        $zip->close();
        @unlink($tmp);
        Audit::log('backup.platform', null, 'backup', basename($file), ['reason' => $reason]);
        return ['ok' => true, 'path' => $file];
    }

    public static function list(?int $shopId = null): array
    {
        $out = [];
        foreach (glob(self::dir($shopId) . '/*.zip') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function path(?int $shopId, string $name): ?string
    {
        if (!preg_match('/^[a-z0-9\-]+\.zip$/', $name)) return null;
        $f = self::dir($shopId) . '/' . $name;
        return is_file($f) ? $f : null;
    }
}
