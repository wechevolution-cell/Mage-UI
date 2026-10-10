<?php
declare(strict_types=1);

/**
 * Tenant request router. Server-side access order (spec §51):
 *   Shop status OPEN?  no → Lock page (storefront AND client admin, except verified impersonation)
 *   Product ACTIVE (and within quota)? no → 404
 */
final class ShopController
{
    public static function dispatch(string $path): void
    {
        $shop = App::$shop;
        $db = App::$db;

        // Canonical host (primary domain) + HTTPS when SSL is active
        if (in_array(req_method(), ['GET', 'HEAD'], true) && !str_starts_with($path, '/admin')) {
            $primary = Shops::primaryDomain((int) $shop['id']);
            $host = host_without_port(req_host());
            $sslActive = (App::$domain['ssl_status'] ?? '') === 'active';
            if ($primary && $host !== $primary && !str_ends_with($host, '.localhost')) {
                redirect((($sslActive || is_https()) ? 'https://' : 'http://') . $primary . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
            }
            if ($sslActive && !is_https() && !str_ends_with($host, '.localhost')) {
                redirect('https://' . req_host() . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
            }
        }

        $isAdmin = $path === '/admin' || str_starts_with($path, '/admin/');
        if ($isAdmin) {
            $imp = Auth::session('admin')['imp'] ?? null;
            $isImpRoute = $path === '/admin/impersonate';
            if (!$isImpRoute && !$imp && !Shops::adminAllowed($shop)) {
                self::lockPage($db, $shop);
                return;
            }
            ShopAdmin::dispatch($path);
            return;
        }
        if (!Shops::isOpen($shop)) {
            self::lockPage($db, $shop);
            return;
        }
        Storefront::dispatch($path);
    }

    /** The lock page reveals nothing: no products, no prices, no forms, no admin links. */
    public static function lockPage(PDO $db, array $shop): void
    {
        http_response_code(503);
        header('Retry-After: 86400');
        header('Cache-Control: no-store');
        security_headers();
        noindex();
        $store = ShopSettings::get($db, 'store');
        $app = ShopSettings::get($db, 'appearance');
        $logo = !empty($app['logo_id']) ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$app['logo_id']]) : null;
        $tokens = ShopSettings::tokens($db, (string) $shop['template_key']);
        $msg = setting_get(DB::platform(), 'lock_message', '') ?: 'Αυτό το ηλεκτρονικό κατάστημα δεν είναι προσωρινά διαθέσιμο. Θα επιστρέψουμε σύντομα κοντά σας.';
        echo View::render('system/lock', ['name' => ShopSettings::storeName($db, $shop), 'logo' => $logo, 'tokens' => $tokens, 'message' => $msg,
            'font' => Templates::manifest((string) $shop['template_key'])['fonts']['display'] ?? null, 'templateKey' => $shop['template_key']]);
    }
}
