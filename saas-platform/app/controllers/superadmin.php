<?php
declare(strict_types=1);

/** Super Admin (platform owner) panel at /superadmin/ on the platform host. */
final class SuperAdminController
{
    private static ?array $me = null;

    public static function dispatch(string $path): void
    {
        security_headers(true);
        if ($path === '/' || $path === '/superadmin') redirect('/superadmin/');
        if (!str_starts_with($path, '/superadmin/')) { self::notFound(); return; }
        $r = new Router();
        $r->get('/superadmin/login', fn() => self::loginForm());
        $r->post('/superadmin/login', fn() => self::login());
        if ($path !== '/superadmin/login') {
            self::$me = Auth::user(DB::platform(), 'sa', 'super_admins');
            if (!self::$me) {
                if (wants_json()) json_out(['ok' => false, 'error' => 'Απαιτείται σύνδεση'], 401);
                redirect('/superadmin/login');
            }
            Audit::$actor = ['type' => 'super_admin', 'id' => (int) self::$me['id'], 'name' => self::$me['name']];
            Templates::sync();
        }
        $r->post('/superadmin/logout', function () { Audit::log('auth.logout'); Auth::logout('sa'); redirect('/superadmin/login'); });
        $r->get('/superadmin/', fn() => self::dashboard());
        $r->get('/superadmin/shops', fn() => self::shops());
        $r->get('/superadmin/shops/new', fn() => self::shopNew());
        $r->post('/superadmin/shops/new', fn() => self::shopCreate());
        $r->get('/superadmin/shops/{id:\d+}', fn($id) => self::shopShow((int) $id));
        $r->post('/superadmin/shops/{id:\d+}/{action:[a-z\-]+}', fn($id, $action) => self::shopAction((int) $id, $action));
        $r->get('/superadmin/shops/{id:\d+}/backups/{name}', fn($id, $name) => self::backupDownload((int) $id, $name));
        $r->get('/superadmin/plans', fn() => self::plans());
        $r->get('/superadmin/plans/new', fn() => self::planForm(null));
        $r->get('/superadmin/plans/{id:\d+}', fn($id) => self::planForm((int) $id));
        $r->post('/superadmin/plans/save', fn() => self::planSave());
        $r->get('/superadmin/templates', fn() => self::templates());
        $r->post('/superadmin/templates/save', fn() => self::templatesSave());
        $r->get('/superadmin/templates/{key}/preview', fn($key) => self::templatePreview($key));
        $r->get('/superadmin/payments', fn() => self::payments());
        $r->post('/superadmin/payments/{id:\d+}/paid', fn($id) => self::paymentPaid((int) $id));
        $r->get('/superadmin/audit', fn() => self::audit());
        $r->get('/superadmin/notifications', fn() => self::notifications());
        $r->post('/superadmin/notifications/read', fn() => self::notificationsRead());
        $r->get('/superadmin/settings', fn() => self::settings());
        $r->post('/superadmin/settings', fn() => self::settingsSave());
        $r->post('/superadmin/settings/test-mail', fn() => self::testMail());
        $r->post('/superadmin/settings/run-cron', fn() => self::runCron());
        $r->get('/superadmin/backups', fn() => self::backups());
        $r->post('/superadmin/backups', fn() => self::backupPlatform());
        $r->get('/superadmin/backups/{name}', fn($name) => self::backupDownload(null, $name));
        $r->get('/superadmin/admins', fn() => self::admins());
        $r->post('/superadmin/admins', fn() => self::adminSave());
        $r->post('/superadmin/admins/{id:\d+}/delete', fn($id) => self::adminDelete((int) $id));
        $r->get('/superadmin/account', fn() => self::account());
        $r->post('/superadmin/account', fn() => self::accountSave());
        if (!$r->dispatch(req_method(), $path)) self::notFound();
    }

    private static function notFound(): void
    {
        http_response_code(404);
        echo View::render('system/error', ['code' => 404, 'title' => 'Η σελίδα δεν βρέθηκε', 'message' => 'Η διεύθυνση δεν υπάρχει.']);
    }

    /* ---------- rendering ---------- */
    private static function render(string $view, array $data, string $title, array $crumbs = []): void
    {
        $path = req_path();
        $active = fn(string $p, bool $exact = false) => $exact ? $path === $p : str_starts_with($path, $p);
        $unread = Notifications::unreadCount();
        $nav = [
            ['label' => '', 'items' => [
                ['href' => '/superadmin/', 'label' => 'Πίνακας ελέγχου', 'icon' => 'dashboard', 'active' => $active('/superadmin/', true)],
                ['href' => '/superadmin/shops', 'label' => 'Καταστήματα', 'icon' => 'store', 'active' => $active('/superadmin/shops')],
                ['href' => '/superadmin/notifications', 'label' => 'Ειδοποιήσεις', 'icon' => 'bell', 'active' => $active('/superadmin/notifications'), 'count' => $unread],
            ]],
            ['label' => 'Εμπορικά', 'items' => [
                ['href' => '/superadmin/plans', 'label' => 'Πακέτα', 'icon' => 'layers', 'active' => $active('/superadmin/plans')],
                ['href' => '/superadmin/payments', 'label' => 'Πληρωμές', 'icon' => 'receipt', 'active' => $active('/superadmin/payments')],
                ['href' => '/superadmin/templates', 'label' => 'Templates', 'icon' => 'palette', 'active' => $active('/superadmin/templates')],
            ]],
            ['label' => 'Σύστημα', 'items' => [
                ['href' => '/superadmin/audit', 'label' => 'Αρχείο ενεργειών', 'icon' => 'shield', 'active' => $active('/superadmin/audit')],
                ['href' => '/superadmin/backups', 'label' => 'Αντίγραφα ασφαλείας', 'icon' => 'archive', 'active' => $active('/superadmin/backups')],
                ['href' => '/superadmin/admins', 'label' => 'Super Admins', 'icon' => 'users', 'active' => $active('/superadmin/admins')],
                ['href' => '/superadmin/settings', 'label' => 'Ρυθμίσεις πλατφόρμας', 'icon' => 'settings', 'active' => $active('/superadmin/settings')],
            ]],
        ];
        $me = self::$me;
        $topRight = '<a class="btn btn-sm btn-ghost" href="/superadmin/account" aria-label="Ο λογαριασμός μου">' . icon('user') . '<span class="hide-sm">' . e($me['name']) . '</span></a>'
            . '<form method="post" action="/superadmin/logout">' . csrf_field() . '<button class="btn btn-sm">' . icon('logout') . '<span class="hide-sm">Έξοδος</span></button></form>';
        echo View::page('superadmin/' . $view, $data + ['me' => $me, 'title' => $title, 'nav' => $nav, 'scope' => 'sa',
            'brand' => setting_get(DB::platform(), 'platform_name', 'wechevo e-shop'), 'brandSub' => 'Super Admin', 'crumbs' => array_merge([['Super Admin', '/superadmin/']], $crumbs),
            'topRight' => $topRight, 'sideFoot' => 'v' . APP_VERSION], 'layouts/admin');
    }

    /* ---------- auth ---------- */
    private static function loginForm(): void
    {
        if (Auth::user(DB::platform(), 'sa', 'super_admins')) redirect('/superadmin/');
        echo View::page('superadmin/login', ['title' => 'Σύνδεση Super Admin', 'brand' => setting_get(DB::platform(), 'platform_name', 'wechevo e-shop')], 'layouts/auth');
    }

    private static function login(): void
    {
        $p = DB::platform();
        $email = in_str('email', '', 190);
        $res = Auth::attempt($p, 'sa', 'super_admins', $email, (string) ($_POST['password'] ?? ''));
        if (!$res['ok']) {
            Audit::$actor = ['type' => 'super_admin', 'id' => null, 'name' => $email];
            Audit::log('auth.login_failed', null, 'super_admin', null, ['email' => $email], 'failed');
            flash('bad', $res['error']);
            redirect('/superadmin/login');
        }
        Auth::login('sa', $res['user']);
        db_exec($p, 'UPDATE super_admins SET last_login_at = ? WHERE id = ?', [now(), $res['user']['id']]);
        Audit::$actor = ['type' => 'super_admin', 'id' => (int) $res['user']['id'], 'name' => $res['user']['name']];
        Audit::log('auth.login');
        redirect('/superadmin/');
    }

    /* ---------- dashboard ---------- */
    private static function shopRows(string $where = '1=1', array $params = [], int $limit = 500, int $offset = 0): array
    {
        $rows = db_all(DB::platform(), "SELECT s.*, p.name AS plan_name, p.active_product_limit,
            (SELECT domain FROM shop_domains d WHERE d.shop_id = s.id ORDER BY is_primary DESC, id LIMIT 1) AS domain,
            (SELECT start_date FROM subscriptions x WHERE x.shop_id = s.id ORDER BY CASE x.status WHEN 'active' THEN 0 ELSE 1 END, x.end_date DESC LIMIT 1) AS start_date,
            (SELECT end_date FROM subscriptions x WHERE x.shop_id = s.id ORDER BY CASE x.status WHEN 'active' THEN 0 ELSE 1 END, x.end_date DESC LIMIT 1) AS end_date
            FROM shops s LEFT JOIN subscription_plans p ON p.id = s.plan_id WHERE s.deleted_at IS NULL AND $where ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset", $params);
        foreach ($rows as &$r) {
            $c = Shops::productCounts((int) $r['id']);
            $r['active_products'] = $c['active'];
            $r['stored_products'] = $c['stored'];
            $r['limit'] = Shops::activeLimit($r);
            $r['days_left'] = days_until($r['end_date']);
        }
        return $rows;
    }

    private static function dashboard(): void
    {
        Subscriptions::runChecks();
        $p = DB::platform();
        $shops = self::shopRows();
        $kpi = ['total' => count($shops), 'active' => 0, 'locked' => 0, 'expiring' => 0, 'pending' => 0, 'suspended' => 0, 'active_products' => 0, 'stored_products' => 0];
        foreach ($shops as $s) {
            if (in_array($s['status'], ['active', 'expiring'], true)) $kpi['active']++;
            if (in_array($s['status'], ['locked', 'expired'], true)) $kpi['locked']++;
            if ($s['status'] === 'suspended') $kpi['suspended']++;
            if ($s['status'] === 'pending') $kpi['pending']++;
            if ($s['days_left'] !== null && $s['days_left'] >= 0 && $s['days_left'] <= 10 && in_array($s['status'], ['active', 'expiring'], true)) $kpi['expiring']++;
            $kpi['active_products'] += $s['active_products'];
            $kpi['stored_products'] += $s['stored_products'];
        }
        $mrr = (float) db_val($p, "SELECT COALESCE(SUM(pl.price),0) FROM shops s JOIN subscription_plans pl ON pl.id = s.plan_id WHERE s.deleted_at IS NULL AND s.status IN ('active','expiring')");
        $pending = (float) db_val($p, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'pending'");
        $notifs = db_all($p, 'SELECT n.*, s.name AS shop_name FROM notifications n LEFT JOIN shops s ON s.id = n.shop_id ORDER BY n.id DESC LIMIT 8');
        $expiring = array_values(array_filter($shops, fn($s) => $s['days_left'] !== null && $s['days_left'] <= 10 && in_array($s['status'], ['active', 'expiring', 'expired'], true)));
        usort($expiring, fn($a, $b) => $a['days_left'] <=> $b['days_left']);
        self::render('dashboard', ['shops' => array_slice($shops, 0, 50), 'kpi' => $kpi, 'mrr' => $mrr, 'pendingPayments' => $pending, 'notifs' => $notifs, 'expiring' => $expiring,
            'cronLast' => setting_get($p, 'cron_last_run')], 'Πίνακας ελέγχου');
    }

    /* ---------- shops ---------- */
    private static function shops(): void
    {
        $where = '1=1';
        $params = [];
        $st = in_str('status');
        if ($st === 'expiring_soon') { $where .= " AND s.status IN ('active','expiring')"; }
        elseif (in_array($st, Shops::STATUSES, true)) { $where .= ' AND s.status = ?'; $params[] = $st; }
        if ($plan = in_int('plan')) { $where .= ' AND s.plan_id = ?'; $params[] = $plan; }
        if ($tpl = in_str('template')) { $where .= ' AND s.template_key = ?'; $params[] = $tpl; }
        if (($q = in_str('q')) !== '') {
            $where .= ' AND (s.name LIKE ? OR s.company_name LIKE ? OR s.email LIKE ? OR s.id IN (SELECT shop_id FROM shop_domains WHERE domain LIKE ?))';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $rows = self::shopRows($where, $params);
        if ($st === 'expiring_soon') $rows = array_values(array_filter($rows, fn($r) => $r['days_left'] !== null && $r['days_left'] <= 10));
        self::render('shops', ['rows' => $rows, 'plans' => Plans::all(), 'templates' => Templates::all()], 'Καταστήματα', [['Καταστήματα', null]]);
    }

    private static function shopNew(): void
    {
        self::render('shop-new', ['plans' => Plans::all(true), 'templates' => Templates::all()], 'Νέο κατάστημα', [['Καταστήματα', '/superadmin/shops'], ['Νέο κατάστημα', null]]);
        clear_old();
    }

    private static function shopCreate(): void
    {
        $in = [];
        foreach (['name', 'slug', 'company_name', 'owner_name', 'email', 'phone', 'vat_number', 'address', 'notes', 'domain', 'template_key', 'start_date', 'status', 'admin_name', 'admin_email', 'hosting_status', 'hosting_notes'] as $k) $in[$k] = in_str($k, '', 2000);
        $in['plan_id'] = in_int('plan_id');
        $in['duration'] = in_int('duration');
        $in['admin_password'] = (string) ($_POST['admin_password'] ?? '');
        foreach (['setup_paid', 'subscription_paid', 'send_credentials', 'demo_content'] as $k) $in[$k] = in_bool($k);
        if ($in['name'] === '') { flash('bad', 'Συμπληρώστε την επωνυμία καταστήματος.'); keep_old(); redirect('/superadmin/shops/new'); }
        $res = Shops::provision($in);
        if (!$res['ok']) { flash('bad', $res['error']); keep_old(); redirect('/superadmin/shops/new'); }
        flash('ok', 'Το κατάστημα δημιουργήθηκε και είναι έτοιμο.');
        redirect('/superadmin/shops/' . $res['shop_id']);
    }

    private static function shopShow(int $id): void
    {
        $shop = Shops::find($id);
        if ($shop && $shop['status'] === 'deleted') {
            // Audit entries and old notifications still link here: explain instead of a bare 404.
            flash('warn', 'Το κατάστημα «' . $shop['name'] . '» έχει διαγραφεί οριστικά' . ($shop['deleted_at'] ? ' (' . fmt_date($shop['deleted_at']) . ')' : '') . '. Το ιστορικό του παραμένει στο αρχείο καταγραφής.');
            redirect('/superadmin/audit?shop=' . $id);
        }
        if (!$shop) { self::notFound(); return; }
        Subscriptions::refreshShopStatus($id);
        $shop = Shops::find($id);
        $p = DB::platform();
        $db = DB::shop($id);
        $owner = db_one($db, "SELECT * FROM users WHERE role = 'owner' AND deleted_at IS NULL ORDER BY id LIMIT 1");
        self::render('shop', [
            'shop' => $shop, 'plan' => Shops::plan($shop), 'plans' => Plans::all(), 'templates' => Templates::all(), 'domains' => Shops::domains($id),
            'sub' => Subscriptions::current($id), 'history' => db_all($p, 'SELECT * FROM subscription_history WHERE shop_id = ? ORDER BY id DESC LIMIT 100', [$id]),
            'payments' => db_all($p, 'SELECT * FROM payments WHERE shop_id = ? ORDER BY id DESC LIMIT 50', [$id]),
            'audit' => db_all($p, 'SELECT * FROM audit_logs WHERE shop_id = ? ORDER BY id DESC LIMIT 100', [$id]),
            'quota' => Quota::status($db, $shop), 'features' => Shops::features($shop), 'overrides' => jd($shop['feature_overrides']),
            'planFeatures' => Plans::features(Shops::plan($shop)), 'backups' => Backups::list($id), 'owner' => $owner,
            'staff' => (int) db_val($db, 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'orders' => (int) db_val($db, 'SELECT COUNT(*) FROM orders'), 'revenue' => (float) db_val($db, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('cancelled','refunded')"),
            'customers' => (int) db_val($db, 'SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'),
            'shopUrl' => Shops::url($id), 'allowedTemplates' => jd($shop['allowed_templates'], []),
        ], $shop['name'], [['Καταστήματα', '/superadmin/shops'], [$shop['name'], null]]);
    }

    private static function shopAction(int $id, string $action): void
    {
        $shop = Shops::find($id);
        if (!$shop || $shop['status'] === 'deleted') { self::notFound(); return; }
        $p = DB::platform();
        $back = '/superadmin/shops/' . $id;
        switch ($action) {
            case 'update':
                $data = [];
                foreach (['name', 'company_name', 'owner_name', 'email', 'phone', 'vat_number', 'address', 'notes', 'hosting_notes'] as $k) $data[$k] = in_str($k, '', 2000) ?: null;
                if (!$data['name']) { flash('bad', 'Η επωνυμία είναι υποχρεωτική.'); redirect($back); }
                $data['hosting_status'] = in_array(in_str('hosting_status'), ['pending', 'active', 'error'], true) ? in_str('hosting_status') : 'pending';
                $data['updated_at'] = now();
                db_update($p, 'shops', $data, 'id = ?', [$id]);
                Audit::log('shop.updated', $id, 'shop', $id);
                flash('ok', 'Τα στοιχεία αποθηκεύτηκαν.');
                break;
            case 'status':
                $st = in_str('status');
                if (!in_array($st, ['active', 'locked', 'suspended', 'pending'], true)) { flash('bad', 'Μη έγκυρη κατάσταση.'); break; }
                if ($st === 'active') {
                    $sub = Subscriptions::current($id);
                    if (!$sub || days_until($sub['end_date']) < 0) { flash('bad', 'Η συνδρομή έχει λήξει. Χρησιμοποιήστε «Ανανέωση» για ενεργοποίηση.'); break; }
                }
                Shops::setStatus($id, $st, in_str('note', '', 300));
                Subscriptions::refreshShopStatus($id);
                flash('ok', 'Η κατάσταση άλλαξε σε «' . status_label($st) . '».');
                break;
            case 'renew':
                $r = Subscriptions::renew($id, in_int('months'), in_str('mode') === 'reset' ? 'reset' : 'extend', in_bool('paid'), in_str('note', '', 300));
                $r['ok'] ? flash('ok', 'Η συνδρομή ανανεώθηκε έως ' . fmt_date($r['end']) . '. Το κατάστημα είναι ενεργό.') : flash('bad', $r['error']);
                break;
            case 'plan':
                $r = Subscriptions::changePlan($id, in_int('plan_id'), in_str('downgrade_mode') === 'deactivate_excess' ? 'deactivate_excess' : 'shop_choose', in_str('note', '', 300));
                if (!$r['ok']) { flash('bad', $r['error']); break; }
                flash('ok', 'Το πακέτο άλλαξε.' . ($r['deactivated'] ? ' Απενεργοποιήθηκαν ' . $r['deactivated'] . ' προϊόντα (δεν διαγράφηκαν).' : '')
                    . (!empty($r['over']) ? ' Το κατάστημα έχει ' . $r['over'] . ' ενεργά προϊόντα πάνω από το όριο: το storefront δείχνει μόνο όσα επιτρέπει το πακέτο και ο διαχειριστής καλείται να επιλέξει.' : ''));
                break;
            case 'limit':
                $v = trim(in_str('active_limit_override'));
                db_update($p, 'shops', ['active_limit_override' => $v === '' ? null : max(0, (int) $v), 'updated_at' => now()], 'id = ?', [$id]);
                Audit::log('shop.limit_override', $id, 'shop', $id, ['value' => $v]);
                flash('ok', $v === '' ? 'Χρησιμοποιείται το όριο του πακέτου.' : 'Ορίστηκε ειδικό όριο ενεργών προϊόντων: ' . (int) $v);
                break;
            case 'template':
                $r = Shops::changeTemplate($id, in_str('template_key'));
                $allowed = array_values(array_intersect(in_arr('allowed_templates'), Templates::keys()));
                db_update($p, 'shops', ['allow_template_choice' => in_bool('allow_template_choice') ? 1 : 0, 'allowed_templates' => $allowed ? je($allowed) : null], 'id = ?', [$id]);
                $r['ok'] ? flash('ok', 'Το template ενημερώθηκε. Τα δεδομένα του καταστήματος δεν άλλαξαν.') : flash('bad', $r['error']);
                break;
            case 'features':
                $ov = [];
                foreach (array_keys(Plans::FEATURES) as $k) { $v = in_str('f_' . $k); if (in_array($v, ['on', 'off'], true)) $ov[$k] = $v; }
                db_update($p, 'shops', ['feature_overrides' => je($ov), 'admin_login_enabled' => in_bool('admin_login_enabled') ? 1 : 0, 'updated_at' => now()], 'id = ?', [$id]);
                Audit::log('shop.access_updated', $id, 'shop', $id, ['overrides' => $ov, 'admin_login' => in_bool('admin_login_enabled')]);
                flash('ok', 'Τα δικαιώματα πρόσβασης αποθηκεύτηκαν.');
                break;
            case 'domain-add':
                $r = Shops::addDomain($id, in_str('domain'), in_bool('primary'));
                $r['ok'] ? flash('ok', 'Το domain προστέθηκε.') : flash('bad', $r['error']);
                break;
            case 'domain-update':
                $did = in_int('domain_id');
                $d = db_one($p, 'SELECT * FROM shop_domains WHERE id = ? AND shop_id = ?', [$did, $id]);
                if (!$d) break;
                $do = in_str('do');
                if ($do === 'primary') { db_exec($p, 'UPDATE shop_domains SET is_primary = (id = ?) WHERE shop_id = ?', [$did, $id]); flash('ok', 'Ορίστηκε κύριο domain.'); }
                elseif ($do === 'delete') {
                    if ((int) db_val($p, 'SELECT COUNT(*) FROM shop_domains WHERE shop_id = ?', [$id]) <= 1) { flash('bad', 'Το κατάστημα πρέπει να έχει τουλάχιστον ένα domain.'); break; }
                    db_exec($p, 'DELETE FROM shop_domains WHERE id = ?', [$did]);
                    if (!db_val($p, 'SELECT 1 FROM shop_domains WHERE shop_id = ? AND is_primary = 1', [$id])) db_exec($p, 'UPDATE shop_domains SET is_primary = 1 WHERE id = (SELECT id FROM shop_domains WHERE shop_id = ? ORDER BY id LIMIT 1)', [$id]);
                    Audit::log('domain.removed', $id, 'domain', $did, ['domain' => $d['domain']]);
                    flash('ok', 'Το domain αφαιρέθηκε.');
                } elseif ($do === 'check') { $s = Shops::checkDns($did); flash($s === 'ok' ? 'ok' : 'warn', 'Έλεγχος DNS: ' . ['ok' => 'σωστό', 'wrong_target' => 'δείχνει σε άλλο server', 'not_resolving' => 'δεν επιλύεται'][$s] ?? $s); }
                elseif ($do === 'status') {
                    $ok = ['pending', 'active', 'error', 'not_required', 'ok', 'wrong_target', 'not_resolving'];
                    db_update($p, 'shop_domains', ['ssl_status' => in_array(in_str('ssl_status'), $ok, true) ? in_str('ssl_status') : $d['ssl_status'],
                        'hosting_status' => in_array(in_str('hosting_status'), $ok, true) ? in_str('hosting_status') : $d['hosting_status']], 'id = ?', [$did]);
                    flash('ok', 'Η κατάσταση domain ενημερώθηκε.');
                }
                break;
            case 'impersonate':
                if (!in_array($shop['status'], ['active', 'expiring', 'pending', 'locked', 'expired', 'suspended'], true)) break;
                $token = Shops::createImpersonationToken((int) self::$me['id'], $id);
                Audit::log('shop.impersonation.start', $id, 'shop', $id);
                redirect(Shops::url($id, '/admin/impersonate?token=' . urlencode($token)));
            case 'owner-password':
                $pass = (string) ($_POST['password'] ?? '');
                if ($e = Auth::validatePassword($pass)) { flash('bad', $e); break; }
                $db = DB::shop($id);
                $owner = db_one($db, "SELECT * FROM users WHERE role = 'owner' AND deleted_at IS NULL ORDER BY id LIMIT 1");
                if (!$owner) { flash('bad', 'Δεν βρέθηκε ιδιοκτήτης.'); break; }
                db_update($db, 'users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'updated_at' => now()], 'id = ?', [$owner['id']]);
                db_exec($db, 'DELETE FROM login_attempts');
                Audit::log('shop.owner_password_reset', $id, 'user', $owner['id']);
                flash('ok', 'Ο κωδικός του ιδιοκτήτη άλλαξε.');
                break;
            case 'backup':
                $r = Backups::shop($id, 'manual');
                $r['ok'] ? flash('ok', 'Δημιουργήθηκε αντίγραφο ασφαλείας: ' . basename($r['path'])) : flash('bad', $r['error']);
                break;
            case 'payment':
                Payments::markPaid(in_int('payment_id'), in_str('reference', '', 120));
                flash('ok', 'Η πληρωμή σημειώθηκε ως εξοφλημένη.');
                break;
            case 'delete':
                $domain = Shops::primaryDomain($id) ?? $shop['slug'];
                if (trim(in_str('confirm_text')) !== $domain) { flash('bad', 'Για διαγραφή πληκτρολογήστε ακριβώς το domain: ' . $domain); break; }
                $r = Shops::deletePermanently($id);
                if ($r['ok']) { flash('ok', 'Το κατάστημα διαγράφηκε οριστικά. Αντίγραφο ασφαλείας: ' . basename($r['backup'])); redirect('/superadmin/shops'); }
                flash('bad', $r['error']);
                break;
            default:
                self::notFound();
                return;
        }
        redirect($back . (in_str('_tab') ? '#' . preg_replace('/[^a-z]/', '', in_str('_tab')) : ''));
    }

    /* ---------- plans ---------- */
    private static function plans(): void
    {
        $plans = Plans::all();
        foreach ($plans as &$pl) $pl['shops'] = (int) db_val(DB::platform(), 'SELECT COUNT(*) FROM shops WHERE plan_id = ? AND deleted_at IS NULL', [$pl['id']]);
        self::render('plans', ['plans' => $plans], 'Πακέτα', [['Πακέτα', null]]);
    }

    private static function planForm(?int $id): void
    {
        $plan = $id ? Plans::find($id) : null;
        if ($id && !$plan) { self::notFound(); return; }
        self::render('plan-form', ['plan' => $plan], $plan ? 'Πακέτο ' . $plan['name'] : 'Νέο πακέτο', [['Πακέτα', '/superadmin/plans'], [$plan['name'] ?? 'Νέο', null]]);
    }

    private static function planSave(): void
    {
        $id = in_int('id') ?: null;
        $name = in_str('name', '', 80);
        if ($name === '') { flash('bad', 'Το όνομα είναι υποχρεωτικό.'); back('/superadmin/plans'); }
        $slug = slugify(in_str('slug') ?: $name, 40);
        if (db_val(DB::platform(), 'SELECT 1 FROM subscription_plans WHERE slug = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$slug, $id] : [$slug])) { flash('bad', 'Το slug υπάρχει ήδη.'); back('/superadmin/plans'); }
        $durations = array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,]+/', in_str('duration_options', '1,3,6,12'))), fn($x) => $x > 0 && $x <= 60)));
        sort($durations);
        $features = [];
        foreach (array_keys(Plans::FEATURES) as $k) $features[$k] = in_bool('feat_' . $k);
        $old = $id ? Plans::find($id) : null;
        $pid = Plans::save(['name' => $name, 'slug' => $slug, 'description' => in_str('description', '', 500), 'price' => in_float('price', 0), 'setup_fee' => in_float('setup_fee', 0),
            'active_product_limit' => in_int('active_product_limit'), 'stored_product_limit' => in_int('stored_product_limit', 10000), 'staff_limit' => in_int('staff_limit', 1),
            'storage_limit_mb' => in_int('storage_limit_mb', 2048), 'duration_options' => $durations ?: [1, 3, 6, 12], 'features' => $features,
            'status' => in_str('status') === 'inactive' ? 'inactive' : 'active', 'sort' => in_int('sort')], $id);
        Audit::log($id ? 'plan.updated' : 'plan.created', null, 'plan', $pid, ['name' => $name, 'old_limit' => $old['active_product_limit'] ?? null, 'new_limit' => in_int('active_product_limit')]);
        flash('ok', 'Το πακέτο αποθηκεύτηκε.' . ($old && (int) $old['active_product_limit'] > in_int('active_product_limit') ? ' Προσοχή: καταστήματα με περισσότερα ενεργά προϊόντα θα εμφανίζουν μόνο μέχρι το νέο όριο.' : ''));
        redirect('/superadmin/plans');
    }

    /* ---------- templates ---------- */
    private static function templates(): void
    {
        $tpls = Templates::all();
        foreach ($tpls as &$t) {
            $t['manifest'] = Templates::manifest($t['key']);
            $t['shops'] = (int) db_val(DB::platform(), 'SELECT COUNT(*) FROM shops WHERE template_key = ? AND deleted_at IS NULL', [$t['key']]);
        }
        $previewShops = db_all(DB::platform(), "SELECT id, name FROM shops WHERE deleted_at IS NULL AND status <> 'deleted' ORDER BY name");
        self::render('templates', ['templates' => $tpls, 'plans' => Plans::all(), 'previewShops' => $previewShops], 'Templates', [['Templates', null]]);
    }

    private static function templatesSave(): void
    {
        $p = DB::platform();
        foreach (Templates::all() as $t) {
            $k = $t['key'];
            $enabled = in_array($k, in_arr('enabled'), true) ? 1 : 0;
            $plans = array_values(array_map('intval', (array) (in_arr('plans')[$k] ?? [])));
            $all = count(Plans::all());
            db_update($p, 'templates', ['enabled' => $enabled, 'allowed_plans' => ($plans && count($plans) < $all) ? je($plans) : null, 'updated_at' => now()], 'id = ?', [$t['id']]);
        }
        Audit::log('templates.updated');
        flash('ok', 'Οι ρυθμίσεις templates αποθηκεύτηκαν. Τα καταστήματα που ήδη χρησιμοποιούν ένα απενεργοποιημένο template συνεχίζουν κανονικά.');
        redirect('/superadmin/templates');
    }

    /** Signed, short-lived preview link: renders a shop with another template without changing anything. */
    private static function templatePreview(string $key): void
    {
        $shopId = in_int('shop');
        if (!$shopId || !Shops::find($shopId) || !in_array($key, Templates::keys(), true)) { flash('bad', 'Επιλέξτε κατάστημα για προεπισκόπηση.'); redirect('/superadmin/templates'); }
        $exp = time() + 3600;
        $sig = hash_hmac('sha256', $shopId . '|' . $key . '|' . $exp, (string) Config::get('app_key'));
        redirect(Shops::url($shopId, '/?__preview=' . urlencode($key) . '&__exp=' . $exp . '&__sig=' . $sig));
    }

    /* ---------- payments ---------- */
    private static function payments(): void
    {
        $where = '1=1';
        $params = [];
        if (in_array(in_str('status'), ['pending', 'paid', 'failed', 'refunded'], true)) { $where = 'pay.status = ?'; $params[] = in_str('status'); }
        $rows = db_all(DB::platform(), "SELECT pay.*, s.name AS shop_name FROM payments pay JOIN shops s ON s.id = pay.shop_id WHERE $where ORDER BY pay.id DESC LIMIT 300", $params);
        $providers = db_all(DB::platform(), 'SELECT * FROM payment_providers ORDER BY id');
        self::render('payments', ['rows' => $rows, 'providers' => $providers], 'Πληρωμές', [['Πληρωμές', null]]);
    }

    private static function paymentPaid(int $id): void
    {
        Payments::markPaid($id, in_str('reference', '', 120));
        flash('ok', 'Η πληρωμή σημειώθηκε ως εξοφλημένη.');
        back('/superadmin/payments');
    }

    /* ---------- audit & notifications ---------- */
    private static function audit(): void
    {
        $where = '1=1';
        $params = [];
        if ($s = in_int('shop')) { $where .= ' AND a.shop_id = ?'; $params[] = $s; }
        if (($q = in_str('q')) !== '') { $where .= ' AND (a.action LIKE ? OR a.actor_name LIKE ? OR a.ip LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
        if (in_array(in_str('outcome'), ['success', 'denied', 'failed'], true)) { $where .= ' AND a.outcome = ?'; $params[] = in_str('outcome'); }
        $total = (int) db_val(DB::platform(), "SELECT COUNT(*) FROM audit_logs a WHERE $where", $params);
        $pg = paginate($total, 50, in_int('page', 1));
        $rows = db_all(DB::platform(), "SELECT a.*, s.name AS shop_name FROM audit_logs a LEFT JOIN shops s ON s.id = a.shop_id WHERE $where ORDER BY a.id DESC LIMIT 50 OFFSET {$pg['offset']}", $params);
        self::render('audit', ['rows' => $rows, 'pg' => $pg, 'shops' => db_all(DB::platform(), 'SELECT id, name FROM shops ORDER BY name')], 'Αρχείο ενεργειών', [['Αρχείο ενεργειών', null]]);
    }

    private static function notifications(): void
    {
        $rows = db_all(DB::platform(), 'SELECT n.*, s.name AS shop_name FROM notifications n LEFT JOIN shops s ON s.id = n.shop_id ORDER BY n.id DESC LIMIT 200');
        self::render('notifications', ['rows' => $rows], 'Ειδοποιήσεις', [['Ειδοποιήσεις', null]]);
    }

    private static function notificationsRead(): void
    {
        db_exec(DB::platform(), 'UPDATE notifications SET read_at = ? WHERE read_at IS NULL', [now()]);
        redirect('/superadmin/notifications');
    }

    /* ---------- settings ---------- */
    private static function settings(): void
    {
        $p = DB::platform();
        $keys = ['platform_name' => 'wechevo e-shop', 'notify_email' => '', 'grace_days' => 0, 'mail_driver' => 'log', 'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465,
            'smtp_secure' => 'ssl', 'smtp_user' => '', 'mail_from' => '', 'mail_from_name' => 'wechevo e-shop', 'server_ip' => '', 'lock_message' => '', 'cron_last_run' => null];
        $s = settings_many($p, $keys);
        $s['smtp_pass_set'] = (string) setting_get($p, 'smtp_pass', '') !== '';
        $cronUrl = base_url() . '/cron?key=' . Config::get('cron_key');
        self::render('settings', ['s' => $s, 'cronUrl' => $cronUrl, 'hosts' => Config::get('platform_hosts', []), 'mailLog' => db_all($p, 'SELECT * FROM mail_log ORDER BY id DESC LIMIT 15')],
            'Ρυθμίσεις πλατφόρμας', [['Ρυθμίσεις', null]]);
    }

    private static function settingsSave(): void
    {
        $p = DB::platform();
        setting_set($p, 'platform_name', in_str('platform_name', 'wechevo e-shop', 80) ?: 'wechevo e-shop');
        setting_set($p, 'notify_email', filter_var(in_str('notify_email'), FILTER_VALIDATE_EMAIL) ?: '');
        setting_set($p, 'grace_days', max(0, min(60, in_int('grace_days'))));
        setting_set($p, 'mail_driver', in_array(in_str('mail_driver'), ['log', 'mail', 'smtp'], true) ? in_str('mail_driver') : 'log');
        setting_set($p, 'smtp_host', in_str('smtp_host', '', 190));
        setting_set($p, 'smtp_port', in_int('smtp_port', 465));
        setting_set($p, 'smtp_secure', in_array(in_str('smtp_secure'), ['ssl', 'tls', 'none'], true) ? in_str('smtp_secure') : 'ssl');
        setting_set($p, 'smtp_user', in_str('smtp_user', '', 190));
        if (($pw = (string) ($_POST['smtp_pass'] ?? '')) !== '') setting_set($p, 'smtp_pass', $pw);
        setting_set($p, 'mail_from', filter_var(in_str('mail_from'), FILTER_VALIDATE_EMAIL) ?: '');
        setting_set($p, 'mail_from_name', in_str('mail_from_name', '', 80));
        setting_set($p, 'server_ip', filter_var(in_str('server_ip'), FILTER_VALIDATE_IP) ?: '');
        setting_set($p, 'lock_message', in_str('lock_message', '', 300));
        $hosts = array_values(array_filter(array_map(fn($h) => Shops::normalizeDomain($h), preg_split('/[\s,]+/', in_str('platform_hosts')))));
        if ($hosts) {
            if (!in_array(host_without_port(req_host()), $hosts, true)) { flash('warn', 'Το τρέχον host πρέπει να παραμείνει στη λίστα για να μη χάσετε την πρόσβαση. Προστέθηκε αυτόματα.'); $hosts[] = host_without_port(req_host()); }
            $cfg = Config::all();
            $cfg['platform_hosts'] = array_values(array_unique($hosts));
            Config::write($cfg);
        }
        Audit::log('settings.updated');
        flash('ok', 'Οι ρυθμίσεις αποθηκεύτηκαν.');
        redirect('/superadmin/settings');
    }

    private static function testMail(): void
    {
        $to = setting_get(DB::platform(), 'notify_email', '') ?: self::$me['email'];
        $ok = Mailer::send($to, 'Δοκιμαστικό email πλατφόρμας', Mailer::layout('Δοκιμή', '<p>Η αποστολή email λειτουργεί.</p>'));
        flash($ok ? 'ok' : 'bad', $ok ? "Στάλθηκε δοκιμαστικό email στο $to." : 'Η αποστολή απέτυχε. Ελέγξτε τις ρυθμίσεις SMTP και το αρχείο αποστολών.');
        redirect('/superadmin/settings');
    }

    private static function runCron(): void
    {
        $r = CronController::run(20.0);
        flash('ok', 'Εκτελέστηκαν οι προγραμματισμένες εργασίες: ' . je($r));
        redirect('/superadmin/settings');
    }

    /* ---------- backups ---------- */
    private static function backups(): void
    {
        $shops = db_all(DB::platform(), "SELECT id, name FROM shops WHERE deleted_at IS NULL ORDER BY name");
        $shopBackups = [];
        foreach (db_all(DB::platform(), 'SELECT id, name FROM shops ORDER BY name') as $s) {
            $b = Backups::list((int) $s['id']);
            if ($b) $shopBackups[] = ['shop' => $s, 'items' => $b];
        }
        self::render('backups', ['platform' => Backups::list(null), 'shopBackups' => $shopBackups, 'shops' => $shops], 'Αντίγραφα ασφαλείας', [['Αντίγραφα ασφαλείας', null]]);
    }

    private static function backupPlatform(): void
    {
        $r = Backups::platform('manual');
        $r['ok'] ? flash('ok', 'Δημιουργήθηκε αντίγραφο πλατφόρμας.') : flash('bad', $r['error']);
        redirect('/superadmin/backups');
    }

    private static function backupDownload(?int $shopId, string $name): void
    {
        $f = Backups::path($shopId, $name);
        if (!$f) { self::notFound(); return; }
        Audit::log('backup.downloaded', $shopId, 'backup', $name);
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($f));
        header('Content-Disposition: attachment; filename="' . ($shopId ? 'shop' . $shopId . '-' : 'platform-') . $name . '"');
        readfile($f);
        exit;
    }

    /* ---------- super admins ---------- */
    private static function admins(): void
    {
        self::render('admins', ['rows' => db_all(DB::platform(), 'SELECT * FROM super_admins WHERE deleted_at IS NULL ORDER BY id')], 'Super Admins', [['Super Admins', null]]);
    }

    private static function adminSave(): void
    {
        $email = in_str('email', '', 190);
        $pass = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || in_str('name') === '') { flash('bad', 'Συμπληρώστε όνομα και έγκυρο email.'); redirect('/superadmin/admins'); }
        if ($e = Auth::validatePassword($pass)) { flash('bad', $e); redirect('/superadmin/admins'); }
        if (db_val(DB::platform(), 'SELECT 1 FROM super_admins WHERE email = ?', [$email])) { flash('bad', 'Το email υπάρχει ήδη.'); redirect('/superadmin/admins'); }
        $id = db_insert(DB::platform(), 'super_admins', ['name' => in_str('name', '', 120), 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => now()]);
        Audit::log('super_admin.created', null, 'super_admin', $id, ['email' => $email]);
        flash('ok', 'Ο Super Admin δημιουργήθηκε.');
        redirect('/superadmin/admins');
    }

    private static function adminDelete(int $id): void
    {
        if ($id === (int) self::$me['id']) { flash('bad', 'Δεν μπορείτε να διαγράψετε τον εαυτό σας.'); redirect('/superadmin/admins'); }
        db_update(DB::platform(), 'super_admins', ['deleted_at' => now(), 'status' => 'disabled'], 'id = ?', [$id]);
        Audit::log('super_admin.deleted', null, 'super_admin', $id);
        flash('ok', 'Ο λογαριασμός διαγράφηκε.');
        redirect('/superadmin/admins');
    }

    private static function account(): void
    {
        self::render('account', [], 'Ο λογαριασμός μου', [['Λογαριασμός', null]]);
    }

    private static function accountSave(): void
    {
        $me = self::$me;
        if (!password_verify((string) ($_POST['current'] ?? ''), $me['password_hash'])) { flash('bad', 'Ο τρέχων κωδικός είναι λάθος.'); redirect('/superadmin/account'); }
        $new = (string) ($_POST['password'] ?? '');
        if ($e = Auth::validatePassword($new)) { flash('bad', $e); redirect('/superadmin/account'); }
        if ($new !== (string) ($_POST['password2'] ?? '')) { flash('bad', 'Οι κωδικοί δεν ταιριάζουν.'); redirect('/superadmin/account'); }
        db_update(DB::platform(), 'super_admins', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'updated_at' => now()], 'id = ?', [$me['id']]);
        $u = db_one(DB::platform(), 'SELECT * FROM super_admins WHERE id = ?', [$me['id']]);
        Auth::login('sa', $u);
        Audit::log('super_admin.password_changed', null, 'super_admin', $me['id']);
        flash('ok', 'Ο κωδικός άλλαξε.');
        redirect('/superadmin/account');
    }
}
