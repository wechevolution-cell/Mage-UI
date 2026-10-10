<?php
declare(strict_types=1);

/** First-run setup wizard: requirements → platform host → first Super Admin. No default credentials exist. */
final class SetupController
{
    public static function requirements(): array
    {
        return [
            ['PHP ≥ 8.1 (τρέχουσα ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>=')],
            ['PDO SQLite', extension_loaded('pdo_sqlite')],
            ['GD (εικόνες)', extension_loaded('gd')],
            ['GD WebP', function_exists('imagewebp')],
            ['mbstring', extension_loaded('mbstring')],
            ['ZipArchive (backups)', class_exists('ZipArchive')],
            ['XMLReader / XMLWriter (feeds, imports)', class_exists('XMLReader') && class_exists('XMLWriter')],
            ['Εγγράψιμος φάκελος data/', is_writable(DATA_DIR)],
            ['Εγγράψιμος φάκελος uploads/', is_writable(UPLOAD_DIR)],
        ];
    }

    public static function handle(string $path): void
    {
        security_headers(true);
        $reqs = self::requirements();
        $ok = !in_array(false, array_column($reqs, 1), true);
        if (is_post()) {
            csrf_check();
            $errors = [];
            $hosts = array_values(array_filter(array_map(fn($h) => Shops::normalizeDomain($h), preg_split('/[\s,]+/', in_str('platform_hosts')))));
            if (!$hosts) $errors[] = 'Συμπληρώστε τουλάχιστον ένα host για τον πίνακα της πλατφόρμας.';
            $name = in_str('name', '', 120);
            $email = in_str('email', '', 190);
            $pass = (string) ($_POST['password'] ?? '');
            if ($name === '') $errors[] = 'Συμπληρώστε όνομα.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Μη έγκυρο email.';
            if ($e = Auth::validatePassword($pass)) $errors[] = $e;
            if ($pass !== (string) ($_POST['password2'] ?? '')) $errors[] = 'Οι κωδικοί δεν ταιριάζουν.';
            if (!$ok) $errors[] = 'Δεν πληρούνται οι απαιτήσεις συστήματος.';
            if (!$errors) {
                Config::write(['app_key' => bin2hex(random_bytes(32)), 'platform_hosts' => $hosts, 'timezone' => in_str('timezone', 'Europe/Athens') ?: 'Europe/Athens',
                    'debug' => false, 'cron_key' => bin2hex(random_bytes(16)), 'installed_at' => date('c')]);
                $p = DB::platform();
                Plans::seedDefaults();
                Templates::sync();
                Payments::seedProviders();
                setting_set($p, 'notify_email', in_str('notify_email', $email) ?: $email);
                setting_set($p, 'mail_driver', 'log');
                setting_set($p, 'grace_days', 0);
                setting_set($p, 'platform_name', 'wechevo e-shop');
                $id = db_insert($p, 'super_admins', ['name' => $name, 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => now()]);
                Audit::$actor = ['type' => 'super_admin', 'id' => $id, 'name' => $name];
                Audit::log('platform.installed', null, 'platform', null, ['hosts' => $hosts]);
                $dest = (is_https() ? 'https://' : 'http://') . $hosts[0] . (preg_match('/:\d+$/', req_host(), $m) ? $m[0] : '') . '/superadmin/login';
                session_destroy();
                echo View::page('system/setup-done', ['dest' => $dest, 'title' => 'Η εγκατάσταση ολοκληρώθηκε'], 'layouts/auth');
                return;
            }
            foreach ($errors as $e) flash('bad', $e);
            keep_old();
        }
        echo View::page('system/setup', ['reqs' => $reqs, 'ok' => $ok, 'title' => 'Εγκατάσταση πλατφόρμας', 'wide' => true], 'layouts/auth');
        clear_old();
    }
}
