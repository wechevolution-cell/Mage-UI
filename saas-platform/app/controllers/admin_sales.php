<?php
declare(strict_types=1);

final class AdminSales
{
    public static function routes(Router $r): void
    {
        $r->get('/admin/orders', fn() => self::orders());
        $r->get('/admin/orders/export.csv', fn() => self::ordersExport());
        $r->get('/admin/orders/{id:\d+}', fn($id) => self::order((int) $id));
        $r->post('/admin/orders/{id:\d+}/update', fn($id) => self::orderUpdate((int) $id));
        $r->get('/admin/customers', fn() => self::customers());
        $r->get('/admin/customers/{id:\d+}', fn($id) => self::customer((int) $id));
        $r->post('/admin/customers/{id:\d+}/notes', fn($id) => self::customerNotes((int) $id));
        $r->get('/admin/messages', fn() => self::messages());
        $r->post('/admin/messages/{id:\d+}/delete', fn($id) => self::messageDelete((int) $id));
    }

    private static function db(): PDO { return ShopAdmin::$db; }

    private static function orders(): void
    {
        $db = self::db();
        [$where, $params] = self::orderFilter();
        $total = (int) db_val($db, "SELECT COUNT(*) FROM orders o WHERE $where", $params);
        $pg = paginate($total, 50, in_int('page', 1));
        $rows = db_all($db, "SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS items FROM orders o WHERE $where ORDER BY o.id DESC LIMIT 50 OFFSET {$pg['offset']}", $params);
        $counts = [];
        foreach (db_all($db, 'SELECT status, COUNT(*) n FROM orders GROUP BY status') as $c) $counts[$c['status']] = (int) $c['n'];
        ShopAdmin::render('admin/orders', ['rows' => $rows, 'pg' => $pg, 'counts' => $counts], 'Παραγγελίες', [['Παραγγελίες', null]]);
    }

    private static function orderFilter(): array
    {
        $where = '1=1';
        $params = [];
        if (in_array(in_str('status'), Orders::STATUSES, true)) { $where .= ' AND o.status = ?'; $params[] = in_str('status'); }
        if (in_array(in_str('payment'), Orders::PAYMENT_STATUSES, true)) { $where .= ' AND o.payment_status = ?'; $params[] = in_str('payment'); }
        if (($q = in_str('q')) !== '') { $where .= ' AND (o.number LIKE ? OR o.email LIKE ? OR o.last_name LIKE ? OR o.phone LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
        if (($from = in_str('from')) !== '') { $where .= ' AND o.created_at >= ?'; $params[] = $from; }
        if (($to = in_str('to')) !== '') { $where .= ' AND o.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
        return [$where, $params];
    }

    private static function ordersExport(): void
    {
        [$where, $params] = self::orderFilter();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['number', 'date', 'status', 'payment_status', 'shipping_status', 'name', 'email', 'phone', 'payment_method', 'subtotal', 'shipping', 'fee', 'total'], ',', '"', '\\');
        $st = db_exec(self::db(), "SELECT * FROM orders o WHERE $where ORDER BY o.id DESC", $params);
        while ($o = $st->fetch()) fputcsv($out, [$o['number'], $o['created_at'], $o['status'], $o['payment_status'], $o['shipping_status'], $o['first_name'] . ' ' . $o['last_name'], $o['email'], $o['phone'], $o['payment_method'], $o['subtotal'], $o['shipping_cost'], $o['payment_fee'], $o['total']], ',', '"', '\\');
        ShopAdmin::audit('orders.exported');
        exit;
    }

    private static function order(int $id): void
    {
        $db = self::db();
        $o = db_one($db, 'SELECT * FROM orders WHERE id = ?', [$id]);
        if (!$o) { ShopAdmin::notFound(); return; }
        ShopAdmin::render('admin/order', ['o' => $o, 'items' => Orders::items($db, $id), 'history' => db_all($db, 'SELECT * FROM order_history WHERE order_id = ? ORDER BY id DESC', [$id]),
            'customer' => $o['customer_id'] ? db_one($db, 'SELECT * FROM customers WHERE id = ?', [$o['customer_id']]) : null], 'Παραγγελία ' . $o['number'], [['Παραγγελίες', '/admin/orders'], [$o['number'], null]]);
    }

    private static function orderUpdate(int $id): void
    {
        Orders::changeStatus(self::db(), $id, ['status' => in_str('status'), 'payment_status' => in_str('payment_status'), 'shipping_status' => in_str('shipping_status'), 'tracking_number' => in_str('tracking_number')],
            in_str('note', '', 2000), ShopAdmin::$user['name'], in_bool('notify'));
        ShopAdmin::audit('order.updated', 'order', $id, ['status' => in_str('status')]);
        flash('ok', 'Η παραγγελία ενημερώθηκε.');
        redirect('/admin/orders/' . $id);
    }

    private static function customers(): void
    {
        $db = self::db();
        $where = 'c.deleted_at IS NULL';
        $params = [];
        if (($q = in_str('q')) !== '') { $where .= ' AND (c.email LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ? OR c.phone LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
        $total = (int) db_val($db, "SELECT COUNT(*) FROM customers c WHERE $where", $params);
        $pg = paginate($total, 50, in_int('page', 1));
        $rows = db_all($db, "SELECT c.*, (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) AS orders, (SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id = c.id AND status NOT IN ('cancelled','refunded')) AS spent
            FROM customers c WHERE $where ORDER BY c.id DESC LIMIT 50 OFFSET {$pg['offset']}", $params);
        ShopAdmin::render('admin/customers', ['rows' => $rows, 'pg' => $pg], 'Πελάτες', [['Πελάτες', null]]);
    }

    private static function customer(int $id): void
    {
        $db = self::db();
        $c = db_one($db, 'SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$c) { ShopAdmin::notFound(); return; }
        ShopAdmin::render('admin/customer', ['c' => $c, 'orders' => db_all($db, 'SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$id]),
            'addresses' => db_all($db, 'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id', [$id])], trim($c['first_name'] . ' ' . $c['last_name']) ?: $c['email'], [['Πελάτες', '/admin/customers'], [$c['email'], null]]);
    }

    private static function customerNotes(int $id): void
    {
        db_update(self::db(), 'customers', ['notes' => in_str('notes', '', 2000), 'updated_at' => now()], 'id = ?', [$id]);
        flash('ok', 'Οι σημειώσεις αποθηκεύτηκαν.');
        redirect('/admin/customers/' . $id);
    }

    private static function messages(): void
    {
        $db = self::db();
        $rows = db_all($db, 'SELECT * FROM contact_messages ORDER BY id DESC LIMIT 200');
        db_exec($db, 'UPDATE contact_messages SET read_at = ? WHERE read_at IS NULL', [now()]);
        ShopAdmin::render('admin/messages', ['rows' => $rows], 'Μηνύματα', [['Μηνύματα', null]]);
    }

    private static function messageDelete(int $id): void
    {
        db_exec(self::db(), 'DELETE FROM contact_messages WHERE id = ?', [$id]);
        flash('ok', 'Το μήνυμα διαγράφηκε.');
        redirect('/admin/messages');
    }
}

final class AdminMarketing
{
    public static function routes(Router $r): void
    {
        $r->get('/admin/popups', fn() => self::popups());
        $r->get('/admin/popups/new', fn() => self::popupForm(null));
        $r->get('/admin/popups/{id:\d+}', fn($id) => self::popupForm((int) $id));
        $r->post('/admin/popups/save', fn() => self::popupSave());
        $r->post('/admin/popups/{id:\d+}/delete', fn($id) => self::popupDelete((int) $id));
        $r->get('/admin/subscribers', fn() => self::subscribers());
        $r->get('/admin/subscribers/export.csv', fn() => self::subscribersExport());
        $r->post('/admin/subscribers/{id:\d+}/delete', fn($id) => self::subscriberDelete((int) $id));
        $r->get('/admin/feeds', fn() => self::feeds());
        $r->get('/admin/feeds/new', fn() => self::feedForm(null));
        $r->get('/admin/feeds/{id:\d+}', fn($id) => self::feedForm((int) $id));
        $r->post('/admin/feeds/save', fn() => self::feedSave());
        $r->post('/admin/feeds/{id:\d+}/regenerate', fn($id) => self::feedRegenerate((int) $id));
        $r->post('/admin/feeds/{id:\d+}/delete', fn($id) => self::feedDelete((int) $id));
        $r->get('/admin/seo', fn() => self::seo());
        $r->post('/admin/seo', fn() => self::seoSave());
        $r->post('/admin/seo/analyze', fn() => self::analyze());
        $r->get('/admin/seo/redirects', fn() => self::redirects());
        $r->post('/admin/seo/redirects/save', fn() => self::redirectSave());
        $r->post('/admin/seo/redirects/{id:\d+}/delete', fn($id) => self::redirectDelete((int) $id));
        $r->get('/admin/seo/404', fn() => self::notFoundLog());
        $r->post('/admin/seo/404/action', fn() => self::notFoundAction());
    }

    private static function db(): PDO { return ShopAdmin::$db; }

    /* ---------- popups ---------- */
    private static function popups(): void
    {
        $db = self::db();
        $rows = db_all($db, 'SELECT p.*, (SELECT COUNT(*) FROM subscribers s WHERE s.popup_id = p.id) AS subs FROM popups p ORDER BY id DESC');
        ShopAdmin::render('admin/popups', ['rows' => $rows], 'Pop-ups', [['Pop-ups', null]]);
    }

    private static function popupForm(?int $id): void
    {
        $db = self::db();
        $p = $id ? db_one($db, 'SELECT * FROM popups WHERE id = ?', [$id]) : null;
        if ($id && !$p) { ShopAdmin::notFound(); return; }
        $img = $p && $p['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$p['image_id']]) : null;
        ShopAdmin::render('admin/popup-form', ['p' => $p, 'img' => $img], $p['name'] ?? 'Νέο pop-up', [['Pop-ups', '/admin/popups'], [$p['name'] ?? 'Νέο', null]]);
    }

    private static function popupSave(): void
    {
        $db = self::db();
        $id = in_int('id') ?: null;
        $row = ['name' => in_str('name', 'Pop-up', 100) ?: 'Pop-up', 'title' => in_str('title', '', 150), 'body' => Sanitizer::text(in_str('body', '', 1000), 1000), 'button_text' => in_str('button_text', '', 40),
            'cta_url' => Sanitizer::url(in_str('cta_url')), 'collect_email' => in_bool('collect_email') ? 1 : 0, 'require_consent' => in_bool('require_consent') ? 1 : 0,
            'consent_text' => in_str('consent_text', '', 300), 'image_id' => in_int('image_id') ?: null, 'trigger_type' => in_array(in_str('trigger_type'), ['delay', 'exit', 'scroll'], true) ? in_str('trigger_type') : 'delay',
            'delay_seconds' => max(0, min(120, in_int('delay_seconds', 5))), 'frequency_days' => max(0, min(365, in_int('frequency_days', 7))), 'pages' => in_str('pages') === 'all' ? 'all' : 'home',
            'status' => in_bool('active') ? 'active' : 'inactive', 'starts_at' => in_str('starts_at') ?: null, 'ends_at' => in_str('ends_at') ?: null, 'updated_at' => now()];
        if ($row['status'] === 'active') db_exec($db, "UPDATE popups SET status = 'inactive' WHERE id <> ?", [$id ?? 0]);
        if ($id) db_update($db, 'popups', $row, 'id = ?', [$id]);
        else { $row['created_at'] = now(); $id = db_insert($db, 'popups', $row); }
        ShopAdmin::audit('popup.saved', 'popup', $id);
        flash('ok', 'Το pop-up αποθηκεύτηκε.' . ($row['status'] === 'active' ? ' Είναι ενεργό (μόνο ένα pop-up μπορεί να είναι ενεργό).' : ''));
        redirect('/admin/popups/' . $id);
    }

    private static function popupDelete(int $id): void
    {
        db_exec(self::db(), 'DELETE FROM popups WHERE id = ?', [$id]);
        ShopAdmin::audit('popup.deleted', 'popup', $id);
        flash('ok', 'Το pop-up διαγράφηκε (οι συνδρομητές παραμένουν).');
        redirect('/admin/popups');
    }

    private static function subscribers(): void
    {
        $db = self::db();
        $where = '1=1';
        $params = [];
        if (($q = in_str('q')) !== '') { $where = 'email LIKE ?'; $params[] = "%$q%"; }
        $total = (int) db_val($db, "SELECT COUNT(*) FROM subscribers WHERE $where", $params);
        $pg = paginate($total, 100, in_int('page', 1));
        $rows = db_all($db, "SELECT * FROM subscribers WHERE $where ORDER BY id DESC LIMIT 100 OFFSET {$pg['offset']}", $params);
        ShopAdmin::render('admin/subscribers', ['rows' => $rows, 'pg' => $pg], 'Συνδρομητές', [['Συνδρομητές', null]]);
    }

    private static function subscribersExport(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['email', 'source', 'consent', 'consent_text', 'consent_at', 'created_at', 'unsubscribed_at'], ',', '"', '\\');
        $st = db_exec(self::db(), 'SELECT * FROM subscribers ORDER BY id');
        while ($s = $st->fetch()) fputcsv($out, [$s['email'], $s['source'], $s['consent'], $s['consent_text'], $s['consent_at'], $s['created_at'], $s['unsubscribed_at']], ',', '"', '\\');
        ShopAdmin::audit('subscribers.exported');
        exit;
    }

    private static function subscriberDelete(int $id): void
    {
        db_exec(self::db(), 'DELETE FROM subscribers WHERE id = ?', [$id]);
        ShopAdmin::audit('subscriber.deleted', 'subscriber', $id);
        flash('ok', 'Ο συνδρομητής διαγράφηκε (δικαίωμα διαγραφής GDPR).');
        redirect('/admin/subscribers');
    }

    /* ---------- feeds ---------- */
    private static function feeds(): void
    {
        $db = self::db();
        Feeds::ensureDefaults($db);
        $rows = db_all($db, 'SELECT * FROM feeds ORDER BY id');
        $shop = ShopAdmin::$shop;
        foreach ($rows as &$f) { $f['allowed'] = Feeds::allowed($shop, $f); $f['url'] = Shops::url((int) $shop['id'], '/feed/' . $f['slug'] . '.xml'); }
        ShopAdmin::render('admin/feeds', ['rows' => $rows, 'logs' => db_all($db, 'SELECT l.*, f.name FROM feed_logs l JOIN feeds f ON f.id = l.feed_id ORDER BY l.id DESC LIMIT 20')], 'Feeds', [['Feeds', null]]);
    }

    private static function feedForm(?int $id): void
    {
        $db = self::db();
        $f = $id ? db_one($db, 'SELECT * FROM feeds WHERE id = ?', [$id]) : null;
        if ($id && !$f) { ShopAdmin::notFound(); return; }
        ShopAdmin::render('admin/feed-form', ['f' => $f, 'cats' => Categories::flat($db), 'filters' => $f ? jd($f['filters']) : [], 'fields' => $f ? jd($f['fields']) : ['id', 'sku', 'name', 'url', 'image', 'final_price', 'availability', 'brand', 'category_path']],
            $f['name'] ?? 'Νέο feed', [['Feeds', '/admin/feeds'], [$f['name'] ?? 'Νέο', null]]);
    }

    private static function feedSave(): void
    {
        $db = self::db();
        $id = in_int('id') ?: null;
        $type = array_key_exists(in_str('type'), Feeds::TYPES) ? in_str('type') : 'custom';
        $tmp = ['type' => $type];
        if (!Feeds::allowed(ShopAdmin::$shop, $tmp)) { flash('bad', 'Αυτός ο τύπος feed δεν περιλαμβάνεται στο πακέτο σας.'); redirect('/admin/feeds'); }
        $slug = slugify(in_str('slug') ?: in_str('name'), 60);
        if (db_val($db, 'SELECT 1 FROM feeds WHERE slug = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$slug, $id] : [$slug])) { flash('bad', 'Το slug χρησιμοποιείται ήδη.'); redirect($id ? '/admin/feeds/' . $id : '/admin/feeds/new'); }
        $row = ['name' => in_str('name', 'Feed', 100) ?: 'Feed', 'slug' => $slug, 'type' => $type,
            'filters' => je(['category_ids' => array_values(array_map('intval', in_arr('category_ids'))), 'in_stock' => in_bool('in_stock'), 'min_price' => in_float('min_price'), 'brand' => in_str('brand', '', 100)]),
            'fields' => je(array_values(array_intersect(in_arr('fields'), Feeds::CUSTOM_FIELDS))), 'frequency' => array_key_exists(in_str('frequency'), Feeds::FREQ) ? in_str('frequency') : 'hourly',
            'status' => in_bool('active') ? 'active' : 'inactive', 'updated_at' => now()];
        if ($id) db_update($db, 'feeds', $row, 'id = ?', [$id]);
        else { $row['created_at'] = now(); $id = db_insert($db, 'feeds', $row); }
        @unlink(DATA_DIR . '/feeds/shop_' . App::shopId() . '/' . $slug . '.xml');
        ShopAdmin::audit('feed.saved', 'feed', $id);
        flash('ok', 'Το feed αποθηκεύτηκε.');
        redirect('/admin/feeds');
    }

    private static function feedRegenerate(int $id): void
    {
        $db = self::db();
        $f = db_one($db, 'SELECT * FROM feeds WHERE id = ?', [$id]);
        if ($f && Feeds::allowed(ShopAdmin::$shop, $f)) {
            Feeds::output($db, ShopAdmin::$shop, $f, true);
            $f = db_one($db, 'SELECT * FROM feeds WHERE id = ?', [$id]);
            flash('ok', 'Το feed δημιουργήθηκε: ' . (int) $f['last_count'] . ' προϊόντα.');
        }
        redirect('/admin/feeds');
    }

    private static function feedDelete(int $id): void
    {
        $db = self::db();
        $f = db_one($db, 'SELECT * FROM feeds WHERE id = ?', [$id]);
        if ($f) { db_exec($db, 'DELETE FROM feeds WHERE id = ?', [$id]); @unlink(DATA_DIR . '/feeds/shop_' . App::shopId() . '/' . $f['slug'] . '.xml'); ShopAdmin::audit('feed.deleted', 'feed', $id); }
        flash('ok', 'Το feed διαγράφηκε.');
        redirect('/admin/feeds');
    }

    /* ---------- SEO ---------- */
    private static function seo(): void
    {
        $db = self::db();
        $seo = ShopSettings::get($db, 'seo');
        $diag = [
            'missing_meta_products' => (int) db_val($db, "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND is_active = 1 AND (seo_description IS NULL OR seo_description = '')"),
            'missing_alt' => (int) db_val($db, "SELECT COUNT(*) FROM media WHERE alt IS NULL OR alt = ''"),
            'missing_meta_categories' => (int) db_val($db, "SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL AND (seo_description IS NULL OR seo_description = '')"),
            'thin_products' => (int) db_val($db, "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND is_active = 1 AND LENGTH(COALESCE(description,'')) < 300"),
            'redirects' => (int) db_val($db, 'SELECT COUNT(*) FROM redirects WHERE active = 1'),
            'not_found' => (int) db_val($db, 'SELECT COUNT(*) FROM not_found_log WHERE ignored = 0'),
            'no_ga' => ($seo['ga4_id'] ?? '') === '', 'no_gsc' => ($seo['gsc_verification'] ?? '') === '',
            'no_sameas' => !array_filter(ShopSettings::get($db, 'store')['socials'] ?? []), 'no_local' => (ShopSettings::get($db, 'store')['address'] ?? '') === '',
        ];
        $og = !empty($seo['default_og_image_id']) ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$seo['default_og_image_id']]) : null;
        ShopAdmin::render('admin/seo', ['seo' => $seo, 'diag' => $diag, 'og' => $og, 'base' => Shops::url(App::shopId(), ''), 'tools' => ShopAdmin::feature('seo_tools')], 'SEO', [['SEO', null]]);
    }

    private static function seoSave(): void
    {
        $db = self::db();
        $seo = ShopSettings::get($db, 'seo');
        $seo['site_title'] = in_str('site_title', '', 120);
        $seo['title_suffix'] = in_str('title_suffix', '', 60);
        $seo['default_description'] = in_str('default_description', '', 320);
        $ga = strtoupper(in_str('ga4_id', '', 30));
        $seo['ga4_id'] = preg_match('/^G-[A-Z0-9]{4,20}$/', $ga) ? $ga : '';
        if ($ga !== '' && $seo['ga4_id'] === '') flash('warn', 'Το GA4 Measurement ID πρέπει να έχει τη μορφή G-XXXXXXX.');
        $v = in_str('gsc_verification', '', 200);
        if (preg_match('/content="([^"]+)"/', $v, $m)) $v = $m[1];
        $seo['gsc_verification'] = preg_replace('/[^A-Za-z0-9_\-]/', '', $v);
        $seo['allow_ai_bots'] = in_bool('allow_ai_bots');
        $seo['robots_extra'] = Sanitizer::text(in_str('robots_extra', '', 2000), 2000);
        $seo['org_type'] = in_array(in_str('org_type'), ['OnlineStore', 'Store', 'LocalBusiness', 'Organization'], true) ? in_str('org_type') : 'OnlineStore';
        $seo['llms_intro'] = Sanitizer::text(in_str('llms_intro', '', 1500), 1500);
        $seo['default_og_image_id'] = in_int('default_og_image_id') ?: null;
        ShopSettings::set($db, 'seo', $seo);
        ShopAdmin::audit('seo.updated', 'settings', 'seo');
        flash('ok', 'Οι ρυθμίσεις SEO αποθηκεύτηκαν.');
        redirect('/admin/seo');
    }

    private static function analyze(): void
    {
        $faq = [];
        foreach (in_arr('faq') as $f) if (is_array($f) && trim((string) ($f['q'] ?? '')) !== '') $faq[] = $f;
        $in = ['title' => in_str('name') ?: in_str('title'), 'seo_title' => in_str('seo_title'), 'seo_description' => in_str('seo_description'), 'slug' => in_str('slug') ?: slugify(in_str('name') ?: in_str('title')),
            'content' => (string) (in_array(in_str('type'), ['category', 'page', 'post'], true) ? ($_POST['content'] ?? '') : ($_POST['description'] ?? $_POST['content'] ?? '')), 'focus_keyword' => in_str('focus_keyword'), 'faq' => $faq, 'robots' => in_str('robots'),
            'type' => in_str('type', 'page'), 'tldr' => in_str('tldr') ?: in_str('short_description'), 'sources' => in_str('sources'), 'author' => in_str('author')];
        $r = SeoAnalyzer::analyze($in);
        $r['cannibal'] = SeoAnalyzer::cannibalization(self::db(), $in['focus_keyword'], in_str('type') === 'post' ? 'post' : 'product', in_int('id') ?: null);
        ShopAdmin::json(['ok' => true] + $r);
    }

    private static function redirects(): void
    {
        $db = self::db();
        ShopAdmin::render('admin/redirects', ['rows' => db_all($db, 'SELECT * FROM redirects ORDER BY id DESC LIMIT 500'), 'prefill' => ['source' => in_str('source'), 'target' => in_str('target'), 'code' => in_int('code', 301)]],
            'Ανακατευθύνσεις', [['SEO', '/admin/seo'], ['Ανακατευθύνσεις', null]]);
    }

    private static function redirectSave(): void
    {
        $r = Redirects::save(self::db(), $_POST, in_int('id') ?: null);
        $r['ok'] ? flash('ok', 'Η ανακατεύθυνση αποθηκεύτηκε.') : flash('bad', $r['error']);
        if ($r['ok']) ShopAdmin::audit('redirect.saved', 'redirect', $r['id']);
        redirect('/admin/seo/redirects');
    }

    private static function redirectDelete(int $id): void
    {
        db_exec(self::db(), 'DELETE FROM redirects WHERE id = ?', [$id]);
        ShopAdmin::audit('redirect.deleted', 'redirect', $id);
        flash('ok', 'Η ανακατεύθυνση διαγράφηκε.');
        redirect('/admin/seo/redirects');
    }

    private static function notFoundLog(): void
    {
        $db = self::db();
        $rows = db_all($db, 'SELECT * FROM not_found_log WHERE ignored = 0 ORDER BY hits DESC, last_hit_at DESC LIMIT 300');
        foreach ($rows as &$r) $r['suggest'] = Redirects::suggest($db, $r['path']);
        ShopAdmin::render('admin/not-found', ['rows' => $rows], 'Παρακολούθηση 404', [['SEO', '/admin/seo'], ['404', null]]);
    }

    private static function notFoundAction(): void
    {
        $db = self::db();
        $id = in_int('id');
        $row = db_one($db, 'SELECT * FROM not_found_log WHERE id = ?', [$id]);
        $act = in_str('do');
        if ($act === 'cleanup') { $n = db_exec($db, 'DELETE FROM not_found_log WHERE hits <= 1 OR ignored = 1')->rowCount(); flash('ok', "Καθαρίστηκαν $n εγγραφές."); redirect('/admin/seo/404'); }
        if (!$row) redirect('/admin/seo/404');
        if ($act === 'ignore') { db_exec($db, 'UPDATE not_found_log SET ignored = 1 WHERE id = ?', [$id]); flash('ok', 'Αγνοήθηκε.'); }
        elseif (in_array($act, ['301', '302', '410'], true)) {
            $r = Redirects::save($db, ['source' => $row['path'], 'target' => in_str('target'), 'code' => (int) $act]);
            $r['ok'] ? flash('ok', 'Δημιουργήθηκε ανακατεύθυνση ' . $act . '.') : flash('bad', $r['error']);
        }
        redirect('/admin/seo/404');
    }
}
