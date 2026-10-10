<?php
declare(strict_types=1);

final class Shops
{
    public const STATUSES = ['pending', 'active', 'expiring', 'expired', 'locked', 'suspended', 'deleted'];
    /** Storefront is public only in these states. */
    public const OPEN = ['active', 'expiring'];

    public static function find(int $id): ?array { return db_one(DB::platform(), 'SELECT * FROM shops WHERE id = ?', [$id]); }

    /** @return array{0:array,1:array}|null [shop, domain] for an incoming host (www/non-www tolerant). */
    public static function resolveHost(string $host): ?array
    {
        $p = DB::platform();
        $cands = [$host];
        if (str_starts_with($host, 'www.')) $cands[] = substr($host, 4); else $cands[] = 'www.' . $host;
        $d = db_one($p, 'SELECT d.*, (d.domain = ?) AS exact FROM shop_domains d JOIN shops s ON s.id = d.shop_id
            WHERE d.domain IN (?, ?) AND s.deleted_at IS NULL ORDER BY exact DESC LIMIT 1', [$host, $cands[0], $cands[1]]);
        if (!$d) return null;
        $shop = self::find((int) $d['shop_id']);
        return $shop ? [$shop, $d] : null;
    }

    public static function domains(int $shopId): array { return db_all(DB::platform(), 'SELECT * FROM shop_domains WHERE shop_id = ? ORDER BY is_primary DESC, id', [$shopId]); }

    public static function primaryDomain(int $shopId): ?string
    {
        return db_val(DB::platform(), 'SELECT domain FROM shop_domains WHERE shop_id = ? ORDER BY is_primary DESC, id LIMIT 1', [$shopId]);
    }

    public static function normalizeDomain(string $d): string
    {
        $d = strtolower(trim($d));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#[/?].*$#', '', $d);
        $d = preg_replace('/:\d+$/', '', $d);
        return rtrim($d, '.');
    }

    public static function validDomain(string $d): bool
    {
        return (bool) preg_match('/^(?=.{3,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $d)
            || (bool) preg_match('/^[a-z0-9-]+\.localhost$/', $d);
    }

    public static function addDomain(int $shopId, string $domain, bool $primary = false): array
    {
        $domain = self::normalizeDomain($domain);
        if (!self::validDomain($domain)) return ['ok' => false, 'error' => 'Μη έγκυρο domain.'];
        if (App::isPlatformHost($domain)) return ['ok' => false, 'error' => 'Το domain χρησιμοποιείται από την πλατφόρμα.'];
        $p = DB::platform();
        if (db_val($p, 'SELECT 1 FROM shop_domains WHERE domain = ?', [$domain])) return ['ok' => false, 'error' => 'Το domain χρησιμοποιείται ήδη από άλλο κατάστημα.'];
        $hasAny = (int) db_val($p, 'SELECT COUNT(*) FROM shop_domains WHERE shop_id = ?', [$shopId]);
        if ($primary || !$hasAny) db_exec($p, 'UPDATE shop_domains SET is_primary = 0 WHERE shop_id = ?', [$shopId]);
        $id = db_insert($p, 'shop_domains', ['shop_id' => $shopId, 'domain' => $domain, 'is_primary' => ($primary || !$hasAny) ? 1 : 0,
            'ssl_status' => str_ends_with($domain, '.localhost') ? 'not_required' : 'pending', 'dns_status' => 'pending', 'hosting_status' => 'pending', 'created_at' => now()]);
        Audit::log('domain.added', $shopId, 'domain', $id, ['domain' => $domain]);
        return ['ok' => true, 'id' => $id];
    }

    /** DNS check: does the domain resolve to the same IP as the platform host? */
    public static function checkDns(int $domainId): string
    {
        $p = DB::platform();
        $d = db_one($p, 'SELECT * FROM shop_domains WHERE id = ?', [$domainId]);
        if (!$d) return 'unknown';
        if (str_ends_with($d['domain'], '.localhost')) { $status = 'ok'; }
        else {
            $ip = gethostbyname($d['domain']);
            $platformIp = setting_get($p, 'server_ip', '') ?: gethostbyname((string) (Config::get('platform_hosts')[0] ?? ''));
            $status = ($ip && $ip !== $d['domain'] && $ip === $platformIp) ? 'ok' : (($ip && $ip !== $d['domain']) ? 'wrong_target' : 'not_resolving');
        }
        db_update($p, 'shop_domains', ['dns_status' => $status, 'verified_at' => $status === 'ok' ? now() : null], 'id = ?', [$domainId]);
        return $status;
    }

    /* ---------- limits & features ---------- */
    public static function plan(array $shop): ?array { return Plans::find((int) $shop['plan_id']); }

    public static function activeLimit(array $shop): int
    {
        if ($shop['active_limit_override'] !== null && $shop['active_limit_override'] !== '') return (int) $shop['active_limit_override'];
        return (int) (self::plan($shop)['active_product_limit'] ?? 0);
    }

    public static function storedLimit(array $shop): int { return (int) (self::plan($shop)['stored_product_limit'] ?? 10000); }
    public static function staffLimit(array $shop): int { return (int) (self::plan($shop)['staff_limit'] ?? 1); }

    public static function features(array $shop): array
    {
        $f = Plans::features(self::plan($shop));
        foreach ((array) jd($shop['feature_overrides'] ?? '{}') as $k => $v) {
            if (array_key_exists($k, $f) && in_array($v, ['on', 'off'], true)) $f[$k] = $v === 'on';
        }
        return $f;
    }

    public static function feature(array $shop, string $key): bool { return self::features($shop)[$key] ?? false; }

    public static function isOpen(array $shop): bool { return in_array($shop['status'], self::OPEN, true); }

    public static function adminAllowed(array $shop): bool
    {
        return (bool) $shop['admin_login_enabled'] && in_array($shop['status'], ['active', 'expiring', 'pending'], true);
    }

    /* ---------- provisioning ---------- */
    /**
     * Wizard: client details → domain → plan → duration → template → admin account → review → provision.
     * @return array{ok:bool,error?:string,shop_id?:int}
     */
    public static function provision(array $in): array
    {
        $p = DB::platform();
        $plan = Plans::find((int) $in['plan_id']);
        if (!$plan) return ['ok' => false, 'error' => 'Επιλέξτε πακέτο.'];
        if (!in_array((int) $in['duration'], Plans::durations($plan), true)) return ['ok' => false, 'error' => 'Μη έγκυρη διάρκεια συνδρομής.'];
        if (!Templates::isAllowed($in['template_key'], (int) $plan['id'])) return ['ok' => false, 'error' => 'Το template δεν είναι διαθέσιμο για αυτό το πακέτο.'];
        $domain = self::normalizeDomain($in['domain']);
        if (!self::validDomain($domain)) return ['ok' => false, 'error' => 'Μη έγκυρο domain.'];
        if (db_val($p, 'SELECT 1 FROM shop_domains WHERE domain = ?', [$domain])) return ['ok' => false, 'error' => 'Το domain χρησιμοποιείται ήδη.'];
        if (!filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Μη έγκυρο email διαχειριστή.'];
        if ($err = Auth::validatePassword($in['admin_password'])) return ['ok' => false, 'error' => $err];

        $slug = slugify($in['slug'] ?: $in['name'], 40);
        $base = $slug;
        $i = 2;
        while (db_val($p, 'SELECT 1 FROM shops WHERE slug = ?', [$slug])) { $slug = $base . '-' . $i++; }

        $start = $in['start_date'] ?: today();
        $months = (int) $in['duration'];
        $end = add_months($start, $months);
        $status = $in['status'] === 'pending' ? 'pending' : 'active';

        $shopId = db_tx($p, function () use ($p, $in, $plan, $slug, $status, $start, $end, $months, $domain) {
            $shopId = db_insert($p, 'shops', [
                'slug' => $slug, 'name' => $in['name'], 'company_name' => $in['company_name'] ?: null, 'owner_name' => $in['owner_name'] ?: null,
                'email' => $in['email'] ?: null, 'phone' => $in['phone'] ?: null, 'vat_number' => $in['vat_number'] ?: null, 'address' => $in['address'] ?: null,
                'notes' => $in['notes'] ?: null, 'plan_id' => $plan['id'], 'template_key' => $in['template_key'], 'status' => $status,
                'hosting_status' => $in['hosting_status'] ?? 'pending', 'hosting_notes' => $in['hosting_notes'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            db_insert($p, 'shop_domains', ['shop_id' => $shopId, 'domain' => $domain, 'is_primary' => 1,
                'ssl_status' => str_ends_with($domain, '.localhost') ? 'not_required' : 'pending', 'dns_status' => 'pending', 'hosting_status' => 'pending', 'created_at' => now()]);
            $subId = db_insert($p, 'subscriptions', ['shop_id' => $shopId, 'plan_id' => $plan['id'], 'status' => 'active', 'start_date' => $start, 'end_date' => $end,
                'duration_months' => $months, 'price' => round($plan['price'] * $months, 2), 'currency' => 'EUR', 'provider_code' => 'manual', 'created_at' => now()]);
            Subscriptions::history($shopId, $subId, 'created', $plan, $months, $start, $end, round($plan['price'] * $months, 2), 'Δημιουργία καταστήματος');
            if ((float) $plan['setup_fee'] > 0) {
                db_insert($p, 'payments', ['shop_id' => $shopId, 'subscription_id' => $subId, 'provider_code' => 'manual', 'kind' => 'setup_fee', 'amount' => $plan['setup_fee'],
                    'status' => !empty($in['setup_paid']) ? 'paid' : 'pending', 'paid_at' => !empty($in['setup_paid']) ? now() : null, 'created_at' => now()]);
            }
            db_insert($p, 'payments', ['shop_id' => $shopId, 'subscription_id' => $subId, 'provider_code' => 'manual', 'kind' => 'subscription', 'amount' => round($plan['price'] * $months, 2),
                'status' => !empty($in['subscription_paid']) ? 'paid' : 'pending', 'paid_at' => !empty($in['subscription_paid']) ? now() : null, 'created_at' => now()]);
            return $shopId;
        });

        // Tenant database: created + migrated on first open, then seeded.
        $db = DB::shop($shopId);
        ShopSeeder::seed($db, $shopId, [
            'shop_name' => $in['name'], 'email' => $in['email'] ?: $in['admin_email'], 'phone' => $in['phone'] ?? '', 'address' => $in['address'] ?? '',
            'company_name' => $in['company_name'] ?? '', 'demo' => !empty($in['demo_content']),
        ]);
        db_insert($db, 'users', ['name' => $in['admin_name'] ?: 'Διαχειριστής', 'email' => trim($in['admin_email']),
            'password_hash' => password_hash($in['admin_password'], PASSWORD_DEFAULT), 'role' => 'owner', 'status' => 'active', 'created_at' => now()]);

        Audit::log('shop.provisioned', $shopId, 'shop', $shopId, ['domain' => $domain, 'plan' => $plan['name'], 'template' => $in['template_key'], 'months' => $months]);
        if (!empty($in['send_credentials'])) {
            $url = (str_ends_with($domain, '.localhost') ? 'http://' : 'https://') . $domain . '/admin/';
            Mailer::send($in['admin_email'], 'Το e-shop σας είναι έτοιμο', Mailer::layout('Καλώς ήρθατε!',
                '<p>Το κατάστημα <b>' . e($in['name']) . '</b> δημιουργήθηκε.</p><p>Σύνδεση διαχείρισης: <a href="' . e($url) . '">' . e($url) . '</a><br>Email: ' . e($in['admin_email']) . '</p><p>Για λόγους ασφαλείας ο κωδικός δεν αποστέλλεται με email. Θα τον λάβετε από τον πάροχο ή μπορείτε να ορίσετε νέο από τη σελίδα «Ξέχασα τον κωδικό».</p>'),
                ['shop_id' => $shopId]);
        }
        return ['ok' => true, 'shop_id' => $shopId];
    }

    public static function setStatus(int $shopId, string $status, string $note = ''): void
    {
        if (!in_array($status, self::STATUSES, true)) return;
        $shop = self::find($shopId);
        if (!$shop || $shop['status'] === $status) return;
        db_update(DB::platform(), 'shops', ['status' => $status, 'locked_at' => $status === 'locked' ? now() : $shop['locked_at'], 'updated_at' => now()], 'id = ?', [$shopId]);
        $sub = Subscriptions::current($shopId);
        $map = ['locked' => 'locked', 'suspended' => 'suspended', 'active' => 'reactivated', 'expired' => 'expired'];
        if (isset($map[$status])) {
            Subscriptions::history($shopId, $sub['id'] ?? null, $map[$status], self::plan($shop), $sub['duration_months'] ?? null, $sub['start_date'] ?? null, $sub['end_date'] ?? null, null, $note);
        }
        Audit::log('shop.status.' . $status, $shopId, 'shop', $shopId, ['from' => $shop['status'], 'note' => $note]);
        if ($status === 'locked') {
            Notifications::create('shop_locked', $shopId, 'Κλείδωμα καταστήματος: ' . $shop['name'], 'Το κατάστημα κλειδώθηκε. ' . $note, 'locked:' . $shopId . ':' . date('YmdHi'));
        }
    }

    public static function changeTemplate(int $shopId, string $key): array
    {
        $shop = self::find($shopId);
        if (!$shop) return ['ok' => false, 'error' => 'Δεν βρέθηκε κατάστημα.'];
        if (!Templates::isAllowed($key, (int) $shop['plan_id'])) return ['ok' => false, 'error' => 'Το template δεν είναι ενεργό ή δεν επιτρέπεται στο πακέτο.'];
        db_update(DB::platform(), 'shops', ['template_key' => $key, 'updated_at' => now()], 'id = ?', [$shopId]);
        Audit::log('shop.template.changed', $shopId, 'shop', $shopId, ['from' => $shop['template_key'], 'to' => $key]);
        return ['ok' => true];
    }

    /* ---------- impersonation ---------- */
    public static function createImpersonationToken(int $superAdminId, int $shopId): string
    {
        $token = random_token(32);
        db_insert(DB::platform(), 'impersonation_tokens', ['token_hash' => hash('sha256', $token), 'super_admin_id' => $superAdminId, 'shop_id' => $shopId, 'expires_at' => time() + 120]);
        Audit::log('shop.impersonation.token', $shopId, 'shop', $shopId);
        return $token;
    }

    /** One-time, short-lived token, bound to the shop resolved from the host. @return int|null super admin id */
    public static function consumeImpersonationToken(string $token, int $shopId): ?int
    {
        $p = DB::platform();
        $h = hash('sha256', $token);
        $row = db_one($p, 'SELECT * FROM impersonation_tokens WHERE token_hash = ?', [$h]);
        if (!$row || $row['used_at'] || (int) $row['expires_at'] < time() || (int) $row['shop_id'] !== $shopId) return null;
        db_exec($p, 'UPDATE impersonation_tokens SET used_at = ? WHERE token_hash = ?', [now(), $h]);
        return (int) $row['super_admin_id'];
    }

    /* ---------- deletion ---------- */
    /** Permanent deletion: backup first, release domains, remove tenant DB + uploads. Platform row kept (soft-deleted) for audit. */
    public static function deletePermanently(int $shopId): array
    {
        $shop = self::find($shopId);
        if (!$shop) return ['ok' => false, 'error' => 'Δεν βρέθηκε κατάστημα.'];
        $backup = Backups::shop($shopId, 'before-delete');
        if (!$backup['ok']) return ['ok' => false, 'error' => 'Η διαγραφή ακυρώθηκε: απέτυχε το αντίγραφο ασφαλείας (' . ($backup['error'] ?? '') . ').'];
        $p = DB::platform();
        db_exec($p, 'DELETE FROM shop_domains WHERE shop_id = ?', [$shopId]);
        db_update($p, 'shops', ['status' => 'deleted', 'deleted_at' => now(), 'updated_at' => now()], 'id = ?', [$shopId]);
        DB::closeShop($shopId);
        foreach (['', '-wal', '-shm'] as $sfx) { @unlink(DB::shopPath($shopId) . $sfx); }
        self::rrmdir(UPLOAD_DIR . '/' . $shopId);
        Audit::log('shop.deleted', $shopId, 'shop', $shopId, ['backup' => basename($backup['path'])]);
        return ['ok' => true, 'backup' => $backup['path']];
    }

    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }

    /** Count helpers used by dashboards (tenant DB queries). */
    public static function productCounts(int $shopId): array
    {
        if (!is_file(DB::shopPath($shopId))) return ['active' => 0, 'stored' => 0];
        $db = DB::shop($shopId);
        $r = db_one($db, 'SELECT COUNT(*) AS stored, COALESCE(SUM(is_active),0) AS active FROM products WHERE deleted_at IS NULL');
        return ['active' => (int) $r['active'], 'stored' => (int) $r['stored']];
    }

    public static function url(int $shopId, string $path = '/'): string
    {
        $d = self::primaryDomain($shopId);
        if (!$d) return '#';
        $port = '';
        $h = req_host();
        if (str_ends_with($d, '.localhost') && preg_match('/:(\d+)$/', $h, $m)) $port = ':' . $m[1];
        return (str_ends_with($d, '.localhost') ? 'http://' : 'https://') . $d . $port . $path;
    }
}
