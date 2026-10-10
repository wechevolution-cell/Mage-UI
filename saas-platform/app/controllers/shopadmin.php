<?php
declare(strict_types=1);

/** Shop Admin panel (/admin/) — scoped strictly to the tenant resolved from the domain. */
final class ShopAdmin
{
    public static ?array $user = null;
    public static array $shop = [];
    public static PDO $db;

    /** route prefix → permission module */
    private const MODULE_MAP = [
        '/admin/products' => 'products', '/admin/categories' => 'categories', '/admin/import' => 'import', '/admin/export' => 'import',
        '/admin/orders' => 'orders', '/admin/customers' => 'customers', '/admin/subscribers' => 'popups', '/admin/pages' => 'pages', '/admin/posts' => 'pages',
        '/admin/media' => 'media', '/admin/appearance' => 'appearance', '/admin/homepage' => 'appearance', '/admin/menus' => 'appearance',
        '/admin/popups' => 'popups', '/admin/feeds' => 'feeds', '/admin/seo' => 'seo', '/admin/settings' => 'settings', '/admin/users' => 'users',
        '/admin/messages' => 'customers', '/admin/activity' => 'settings',
    ];
    /** route prefix → plan feature flag */
    private const FEATURE_MAP = [
        '/admin/import' => 'import', '/admin/export' => 'export', '/admin/popups' => 'popups', '/admin/subscribers' => 'popups', '/admin/posts' => 'blog',
        '/admin/seo/redirects' => 'seo_tools', '/admin/seo/404' => 'seo_tools',
    ];

    public static function dispatch(string $path): void
    {
        self::$shop = App::$shop;
        self::$db = App::$db;
        security_headers(true);
        if ($path === '/admin') redirect('/admin/');
        $r = new Router();
        $r->get('/admin/login', fn() => self::loginForm());
        $r->post('/admin/login', fn() => self::login());
        $r->get('/admin/forgot', fn() => self::forgotForm());
        $r->post('/admin/forgot', fn() => self::forgot());
        $r->get('/admin/reset', fn() => self::resetForm());
        $r->post('/admin/reset', fn() => self::reset());
        $r->get('/admin/impersonate', fn() => self::impersonate());
        $public = ['/admin/login', '/admin/forgot', '/admin/reset', '/admin/impersonate'];
        if (!in_array($path, $public, true)) {
            self::$user = self::currentUser();
            if (!self::$user) {
                if (wants_json()) json_out(['ok' => false, 'error' => 'Η σύνδεση έληξε. Συνδεθείτε ξανά.'], 401);
                redirect('/admin/login');
            }
            $s = Auth::session('admin');
            Audit::$actor = ['type' => 'shop_user', 'id' => (int) self::$user['id'], 'name' => self::$user['name'] . (!empty($s['imp']) ? ' (Super Admin)' : ''), 'impersonator_id' => $s['imp'] ?? null];
            self::guard($path);
        }
        $r->post('/admin/logout', fn() => self::logout());
        AdminCatalog::routes($r);
        AdminContent::routes($r);
        AdminSales::routes($r);
        AdminMarketing::routes($r);
        AdminSettings::routes($r);
        if (!$r->dispatch(req_method(), $path)) self::notFound();
    }

    private static function currentUser(): ?array
    {
        $u = Auth::user(self::$db, 'admin', 'users');
        if (!$u) return null;
        $s = Auth::session('admin');
        if (!empty($s['imp'])) $u['_impersonated'] = (int) $s['imp'];
        elseif (!Shops::adminAllowed(self::$shop)) { Auth::logout('admin'); return null; }
        return $u;
    }

    /** Server-side permission + feature check for every admin route. */
    private static function guard(string $path): void
    {
        $module = 'dashboard';
        foreach (self::MODULE_MAP as $prefix => $m) { if (str_starts_with($path, $prefix)) { $module = $m; break; } }
        if ($path === '/admin/account' || $path === '/admin/subscription') $module = 'dashboard';
        if (!ShopUsers::can(self::$user, $module)) {
            Audit::log('access.denied', App::shopId(), 'route', $path, ['module' => $module], 'denied');
            self::forbidden('Δεν έχετε δικαίωμα πρόσβασης σε αυτή την ενότητα (' . (ShopUsers::MODULES[$module] ?? $module) . ').');
        }
        foreach (self::FEATURE_MAP as $prefix => $f) {
            if (str_starts_with($path, $prefix) && !Shops::feature(self::$shop, $f)) {
                self::forbidden('Η λειτουργία «' . (Plans::FEATURES[$f] ?? $f) . '» δεν περιλαμβάνεται στο πακέτο σας. Επικοινωνήστε με τον πάροχο για αναβάθμιση.', 403, 'Μη διαθέσιμη λειτουργία');
            }
        }
    }

    public static function forbidden(string $msg, int $code = 403, string $title = 'Δεν επιτρέπεται η πρόσβαση'): never
    {
        http_response_code($code);
        if (wants_json()) json_out(['ok' => false, 'error' => $msg], $code);
        self::render('admin/forbidden', ['message' => $msg, 'heading' => $title], $title);
        exit;
    }

    public static function notFound(): void
    {
        http_response_code(404);
        if (wants_json()) json_out(['ok' => false, 'error' => 'Δεν βρέθηκε'], 404);
        if (!self::$user) { echo View::render('system/error', ['code' => 404, 'title' => 'Δεν βρέθηκε', 'message' => '']); return; }
        self::render('admin/forbidden', ['message' => 'Η σελίδα ή η εγγραφή δεν βρέθηκε.', 'heading' => 'Δεν βρέθηκε'], 'Δεν βρέθηκε');
    }

    public static function can(string $module): bool { return ShopUsers::can(self::$user, $module); }
    public static function feature(string $f): bool { return Shops::feature(self::$shop, $f); }

    /* ---------- rendering ---------- */
    public static function render(string $view, array $data, string $title, array $crumbs = []): void
    {
        $path = req_path();
        $act = fn(string $p, bool $exact = false) => $exact ? $path === $p : ($path === $p || str_starts_with($path, $p . '/'));
        $db = self::$db;
        $newOrders = (int) db_val($db, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting_payment')");
        $unreadMsgs = (int) db_val($db, 'SELECT COUNT(*) FROM contact_messages WHERE read_at IS NULL');
        $item = fn($href, $label, $icon, $module, $feature = null, $count = 0) => (self::can($module) && (!$feature || self::feature($feature))) ? ['href' => $href, 'label' => $label, 'icon' => $icon, 'active' => $act(rtrim($href, '/'), $href === '/admin/'), 'count' => $count] : null;
        $sections = [
            ['label' => '', 'items' => [$item('/admin/', 'Πίνακας ελέγχου', 'dashboard', 'dashboard')]],
            ['label' => 'Κατάλογος', 'items' => [$item('/admin/products', 'Προϊόντα', 'box', 'products'), $item('/admin/categories', 'Κατηγορίες', 'tag', 'categories'), $item('/admin/import', 'Εισαγωγή / εξαγωγή', 'upload', 'import', 'import'), $item('/admin/media', 'Πολυμέσα', 'image', 'media')]],
            ['label' => 'Πωλήσεις', 'items' => [$item('/admin/orders', 'Παραγγελίες', 'receipt', 'orders', null, $newOrders), $item('/admin/customers', 'Πελάτες', 'users', 'customers'), $item('/admin/messages', 'Μηνύματα', 'mail', 'customers', null, $unreadMsgs)]],
            ['label' => 'Περιεχόμενο', 'items' => [$item('/admin/homepage', 'Αρχική σελίδα', 'dashboard', 'appearance'), $item('/admin/pages', 'Σελίδες', 'file', 'pages'), $item('/admin/posts', 'Blog', 'blog', 'pages', 'blog'), $item('/admin/menus', 'Μενού', 'menu-builder', 'appearance'), $item('/admin/appearance', 'Εμφάνιση', 'palette', 'appearance')]],
            ['label' => 'Marketing', 'items' => [$item('/admin/popups', 'Pop-ups', 'megaphone', 'popups', 'popups'), $item('/admin/subscribers', 'Συνδρομητές', 'mail', 'popups', 'popups'), $item('/admin/feeds', 'Feeds', 'rss', 'feeds'), $item('/admin/seo', 'SEO', 'search', 'seo')]],
            ['label' => 'Ρυθμίσεις', 'items' => [$item('/admin/settings', 'Ρυθμίσεις καταστήματος', 'settings', 'settings'), $item('/admin/users', 'Χρήστες', 'users', 'users'), $item('/admin/subscription', 'Συνδρομή & πακέτο', 'layers', 'dashboard'), $item('/admin/activity', 'Ιστορικό ενεργειών', 'shield', 'settings')]],
        ];
        $nav = [];
        foreach ($sections as $s) { $items = array_values(array_filter($s['items'])); if ($items) $nav[] = ['label' => $s['label'], 'items' => $items]; }
        $quota = Quota::status($db, self::$shop);
        $qcls = $quota['active'] >= $quota['limit'] ? 'full' : ($quota['pct'] >= 80 ? 'warn' : '');
        $u = self::$user;
        $topRight = '<a class="quota-pill" href="/admin/products?status=active" title="Ενεργά προϊόντα / όριο πακέτου"><span class="bar ' . $qcls . '"><i data-quota-bar style="width:' . $quota['pct'] . '%"></i></span><span>ΕΝΕΡΓΑ: <span class="num" data-quota-text>' . $quota['active'] . ' / ' . $quota['limit'] . '</span></span></a>'
            . '<a class="btn btn-sm btn-ghost" href="/" target="_blank" rel="noopener">' . icon('eye') . '<span class="hide-sm">Κατάστημα</span></a>'
            . '<a class="btn btn-sm btn-ghost" href="/admin/account">' . icon('user') . e($u['name']) . '</a>'
            . '<form method="post" action="/admin/logout">' . csrf_field() . '<button class="btn btn-sm">' . icon('logout') . '</button></form>';
        $app = ShopSettings::get($db, 'appearance');
        $fav = !empty($app['favicon_id']) ? media_url(db_one($db, 'SELECT * FROM media WHERE id = ?', [$app['favicon_id']])) : null;
        echo View::page($view, $data + ['user' => $u, 'shop' => self::$shop, 'db' => $db, 'quota' => $quota, 'title' => $title, 'nav' => $nav, 'scope' => 'shop',
            'brand' => ShopSettings::storeName($db, self::$shop), 'brandSub' => 'Διαχείριση καταστήματος', 'crumbs' => array_merge([['Διαχείριση', '/admin/']], $crumbs),
            'topRight' => $topRight, 'impersonating' => !empty($u['_impersonated']), 'mediaUrl' => '/admin/media', 'favicon' => $fav,
            'sideFoot' => 'Πακέτο ' . e(Shops::plan(self::$shop)['name'] ?? '') . ' · <a href="/admin/subscription">λεπτομέρειες</a>'], 'layouts/admin');
    }

    public static function audit(string $action, ?string $type = null, string|int|null $id = null, array $details = []): void
    {
        Audit::log($action, App::shopId(), $type, $id, $details);
    }

    /* ---------- auth ---------- */
    private static function authPage(string $view, array $data = []): void
    {
        echo View::page($view, $data + ['title' => 'Διαχείριση καταστήματος', 'brand' => ShopSettings::storeName(self::$db, self::$shop), 'scope' => 'shop'], 'layouts/auth');
    }

    private static function loginForm(): void
    {
        if (self::currentUser()) redirect('/admin/');
        self::authPage('admin/login');
    }

    private static function login(): void
    {
        $email = in_str('email', '', 190);
        $res = Auth::attempt(self::$db, 'admin', 'users', $email, (string) ($_POST['password'] ?? ''));
        if (!$res['ok']) {
            Audit::$actor = ['type' => 'shop_user', 'id' => null, 'name' => $email];
            self::audit('auth.login_failed', 'user', null, ['email' => $email]);
            flash('bad', $res['error']);
            redirect('/admin/login');
        }
        Auth::login('admin', $res['user']);
        db_exec(self::$db, 'UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $res['user']['id']]);
        Audit::$actor = ['type' => 'shop_user', 'id' => (int) $res['user']['id'], 'name' => $res['user']['name']];
        self::audit('auth.login');
        redirect('/admin/');
    }

    private static function logout(): void
    {
        $imp = Auth::session('admin')['imp'] ?? null;
        self::audit($imp ? 'shop.impersonation.end' : 'auth.logout');
        Auth::logout('admin');
        if ($imp) {
            $hosts = (array) Config::get('platform_hosts', []);
            $port = preg_match('/:(\d+)$/', req_host(), $m) ? $m[0] : '';
            redirect((is_https() ? 'https://' : 'http://') . ($hosts[0] ?? '') . $port . '/superadmin/shops/' . App::shopId());
        }
        redirect('/admin/login');
    }

    private static function impersonate(): void
    {
        $saId = Shops::consumeImpersonationToken(in_str('token'), App::shopId());
        if (!$saId) {
            Audit::log('shop.impersonation.rejected', App::shopId(), 'shop', App::shopId(), [], 'denied');
            self::forbiddenPlain();
        }
        $owner = db_one(self::$db, "SELECT * FROM users WHERE role = 'owner' AND deleted_at IS NULL ORDER BY id LIMIT 1")
            ?: db_one(self::$db, "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT 1");
        if (!$owner) self::forbiddenPlain();
        Auth::login('admin', $owner, ['imp' => $saId]);
        Audit::$actor = ['type' => 'super_admin', 'id' => $saId, 'name' => 'Super Admin #' . $saId, 'impersonator_id' => $saId];
        self::audit('shop.impersonation.session', 'user', $owner['id']);
        redirect('/admin/');
    }

    private static function forbiddenPlain(): never
    {
        http_response_code(403);
        echo View::render('system/error', ['code' => 403, 'title' => 'Μη έγκυρος σύνδεσμος', 'message' => 'Ο σύνδεσμος υποστήριξης έληξε ή έχει ήδη χρησιμοποιηθεί.']);
        exit;
    }

    private static function forgotForm(): void { self::authPage('admin/forgot'); }

    private static function forgot(): void
    {
        $email = in_str('email', '', 190);
        if (!rate_limit(self::$db, 'forgot|' . client_ip(), 5, 900)) { flash('bad', 'Πολλά αιτήματα. Δοκιμάστε ξανά σε λίγο.'); redirect('/admin/forgot'); }
        $u = db_one(self::$db, "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL AND status = 'active'", [$email]);
        if ($u) {
            $token = random_token(32);
            db_update(self::$db, 'users', ['reset_token_hash' => hash('sha256', $token), 'reset_expires' => time() + 3600], 'id = ?', [$u['id']]);
            $url = base_url() . '/admin/reset?token=' . urlencode($token) . '&email=' . urlencode($u['email']);
            Mailer::send($u['email'], 'Επαναφορά κωδικού διαχείρισης', Mailer::layout('Επαναφορά κωδικού', '<p>Ορίστε νέο κωδικό από τον σύνδεσμο (ισχύει 1 ώρα):</p><p><a href="' . e($url) . '">' . e($url) . '</a></p><p>Αν δεν το ζητήσατε, αγνοήστε το μήνυμα.</p>', ShopSettings::storeName(self::$db, self::$shop)), ['shop_id' => App::shopId()]);
            self::audit('auth.password_reset_requested', 'user', $u['id']);
        }
        flash('ok', 'Αν το email αντιστοιχεί σε λογαριασμό, θα λάβετε σύνδεσμο επαναφοράς.');
        redirect('/admin/login');
    }

    private static function resetForm(): void { self::authPage('admin/reset', ['token' => in_str('token'), 'email' => in_str('email')]); }

    private static function reset(): void
    {
        $email = in_str('email', '', 190);
        $u = db_one(self::$db, 'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        $ok = $u && $u['reset_token_hash'] && hash_equals($u['reset_token_hash'], hash('sha256', in_str('token'))) && (int) $u['reset_expires'] > time();
        if (!$ok) { flash('bad', 'Ο σύνδεσμος επαναφοράς δεν είναι έγκυρος ή έληξε.'); redirect('/admin/forgot'); }
        $p = (string) ($_POST['password'] ?? '');
        if ($e = Auth::validatePassword($p)) { flash('bad', $e); redirect('/admin/reset?token=' . urlencode(in_str('token')) . '&email=' . urlencode($email)); }
        db_update(self::$db, 'users', ['password_hash' => password_hash($p, PASSWORD_DEFAULT), 'reset_token_hash' => null, 'reset_expires' => null, 'updated_at' => now()], 'id = ?', [$u['id']]);
        Auth::clearFailures(self::$db, Auth::lockKey('admin', $email));
        self::audit('auth.password_reset', 'user', $u['id']);
        flash('ok', 'Ο κωδικός άλλαξε. Συνδεθείτε με τον νέο κωδικό.');
        redirect('/admin/login');
    }

    /* ---------- shared helpers for handlers ---------- */
    public static function json(array $data, int $code = 200): never { json_out($data, $code); }

    public static function faqFromPost(): array
    {
        $out = [];
        foreach (in_arr('faq') as $f) {
            if (!is_array($f)) continue;
            $q = trim((string) ($f['q'] ?? ''));
            $a = trim((string) ($f['a'] ?? ''));
            if ($q !== '' && $a !== '') $out[] = ['q' => $q, 'a' => str_starts_with($a, '<') ? $a : '<p>' . nl2br(e($a)) . '</p>'];
        }
        return $out;
    }
}
