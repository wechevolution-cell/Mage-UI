<?php
declare(strict_types=1);

/**
 * Request context + top-level dispatch: setup → platform host (Super Admin) → tenant host (shop).
 * A platform host may also be a shop's domain ("one domain"): then /superadmin/* goes to the Super Admin
 * and every other path to that shop (storefront at /, shop admin at /admin).
 */
final class App
{
    /** Resolved tenant for this request (null on the platform host). */
    public static ?array $shop = null;
    public static ?PDO $db = null;
    public static ?array $domain = null;

    public static function run(): void
    {
        $path = req_path();
        if (!Config::installed()) {
            session_start_scoped('wsetup');
            SetupController::handle($path);
            return;
        }
        if ($path === '/cron' || $path === '/cron/') {
            CronController::http();
            return;
        }
        $host = host_without_port(req_host());
        $res = null;
        if (self::isPlatformHost($host)) {
            $res = self::isSuperAdminPath($path) ? null : Shops::resolveHost($host);
            if (!$res) {
                session_start_scoped('wsa');
                SuperAdminController::dispatch($path);
                return;
            }
        }
        $res ??= Shops::resolveHost($host);
        if (!$res) {
            http_response_code(404);
            security_headers();
            noindex();
            echo View::render('system/unknown-domain', ['host' => $host]);
            return;
        }
        [$shop, $domain] = $res;
        self::$shop = $shop;
        self::$domain = $domain;
        self::$db = DB::shop((int) $shop['id']);
        Subscriptions::refreshShopStatus((int) $shop['id']);
        self::$shop = Shops::find((int) $shop['id']);
        session_start_scoped('wshop');
        ShopController::dispatch($path);
    }

    public static function isPlatformHost(string $host): bool
    {
        $hosts = array_map('strtolower', (array) Config::get('platform_hosts', []));
        $host = strtolower($host);
        return in_array($host, $hosts, true) || (str_starts_with($host, 'www.') && in_array(substr($host, 4), $hosts, true));
    }

    public static function isSuperAdminPath(string $path): bool
    {
        return $path === '/superadmin' || str_starts_with($path, '/superadmin/');
    }

    public static function shopId(): int { return (int) (self::$shop['id'] ?? 0); }
}
