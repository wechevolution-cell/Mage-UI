<?php
declare(strict_types=1);

final class AdminCatalog
{
    public static function routes(Router $r): void
    {
        $r->get('/admin/', fn() => self::dashboard());
        $r->post('/admin/onboarding/dismiss', fn() => self::dismissOnboarding());
        $r->get('/admin/products', fn() => self::products());
        $r->get('/admin/products/new', fn() => self::productForm(null));
        $r->get('/admin/products/{id:\d+}', fn($id) => self::productForm((int) $id));
        $r->post('/admin/products/save', fn() => self::productSave());
        $r->post('/admin/products/{id:\d+}/toggle', fn($id) => self::productToggle((int) $id));
        $r->post('/admin/products/{id:\d+}/duplicate', fn($id) => self::productDuplicate((int) $id));
        $r->post('/admin/products/bulk', fn() => self::productBulk());
        $r->get('/admin/categories', fn() => self::categories());
        $r->get('/admin/categories/new', fn() => self::categoryForm(null));
        $r->get('/admin/categories/{id:\d+}', fn($id) => self::categoryForm((int) $id));
        $r->post('/admin/categories/save', fn() => self::categorySave());
        $r->post('/admin/categories/{id:\d+}/delete', fn($id) => self::categoryDelete((int) $id));
        $r->get('/admin/import', fn() => self::importIndex());
        $r->post('/admin/import/upload', fn() => self::importUpload());
        $r->get('/admin/import/{id:\d+}', fn($id) => self::importShow((int) $id));
        $r->post('/admin/import/{id:\d+}/confirm', fn($id) => self::importConfirm((int) $id));
        $r->post('/admin/import/{id:\d+}/process', fn($id) => self::importProcess((int) $id));
        $r->post('/admin/import/{id:\d+}/cancel', fn($id) => self::importCancel((int) $id));
        $r->get('/admin/import/template.csv', fn() => self::importTemplate());
        $r->get('/admin/export', fn() => self::export());
        $r->post('/admin/export', fn() => self::export());
        $r->get('/admin/media', fn() => self::media());
        $r->post('/admin/media/upload', fn() => self::mediaUpload());
        $r->post('/admin/media/{id:\d+}/update', fn($id) => self::mediaUpdate((int) $id));
        $r->post('/admin/media/{id:\d+}/delete', fn($id) => self::mediaDelete((int) $id));
    }

    private static function db(): PDO { return ShopAdmin::$db; }
    private static function shop(): array { return ShopAdmin::$shop; }

    /* ---------- dashboard ---------- */
    private static function dashboard(): void
    {
        $db = self::db();
        $today = date('Y-m-d');
        $month = date('Y-m-01');
        $stats = [
            'orders_today' => (int) db_val($db, 'SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$today]),
            'orders_month' => (int) db_val($db, 'SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$month]),
            'revenue_month' => (float) db_val($db, "SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled','refunded')", [$month]),
            'pending' => (int) db_val($db, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting_payment','processing')"),
            'customers' => (int) db_val($db, 'SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'),
            'subscribers' => (int) db_val($db, 'SELECT COUNT(*) FROM subscribers WHERE unsubscribed_at IS NULL'),
            'low_stock' => (int) db_val($db, 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND is_active = 1 AND manage_stock = 1 AND stock <= 3'),
            'messages' => (int) db_val($db, 'SELECT COUNT(*) FROM contact_messages WHERE read_at IS NULL'),
        ];
        $recent = db_all($db, 'SELECT * FROM orders ORDER BY id DESC LIMIT 8');
        $lowStock = db_all($db, 'SELECT id, name, stock, sku FROM products WHERE deleted_at IS NULL AND is_active = 1 AND manage_stock = 1 AND stock <= 3 ORDER BY stock LIMIT 6');
        $sales = [];
        foreach (db_all($db, "SELECT substr(created_at,1,10) d, SUM(total) t, COUNT(*) n FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled','refunded') GROUP BY d", [date('Y-m-d', strtotime('-13 days'))]) as $r) $sales[$r['d']] = $r;
        $series = [];
        for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $series[] = ['d' => $d, 't' => (float) ($sales[$d]['t'] ?? 0), 'n' => (int) ($sales[$d]['n'] ?? 0)]; }
        $app = ShopSettings::get($db, 'appearance');
        $store = ShopSettings::get($db, 'store');
        $hp = ShopSettings::get($db, 'homepage');
        $onb = [
            ['Ανεβάστε λογότυπο και ορίστε χρώματα', !empty($app['logo_id']) || !empty($app['tokens']), '/admin/appearance'],
            ['Συμπληρώστε στοιχεία επικοινωνίας', ($store['phone'] ?? '') !== '' && ($store['email'] ?? '') !== '', '/admin/settings'],
            ['Δημιουργήστε κατηγορίες', (int) db_val($db, 'SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL') > 0, '/admin/categories'],
            ['Προσθέστε ή εισάγετε προϊόντα', (int) db_val($db, "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND (sku IS NULL OR sku NOT LIKE 'DEMO-%')") > 0, '/admin/import'],
            ['Ρυθμίστε την αρχική σελίδα (hero)', !empty($hp['hero']['image_id']) || ($hp['hero']['title'] ?? '') !== ShopSettings::homepageDefaults()['hero']['title'], '/admin/homepage'],
            ['Ορίστε αποστολές & τρόπους πληρωμής', setting_get($db, 'shipping') !== null, '/admin/settings/shipping'],
            ['Συμπληρώστε τους όρους χρήσης & την πολιτική απορρήτου', (bool) db_val($db, "SELECT 1 FROM pages WHERE slug = 'terms' AND updated_at > created_at"), '/admin/pages'],
            ['Ρυθμίστε SEO & Google Analytics', (ShopSettings::get($db, 'seo')['ga4_id'] ?? '') !== '', '/admin/seo'],
        ];
        $sub = Subscriptions::current(App::shopId());
        ShopAdmin::render('admin/dashboard', ['stats' => $stats, 'recent' => $recent, 'lowStock' => $lowStock, 'series' => $series, 'onboarding' => $onb,
            'onbDismissed' => !empty(ShopSettings::get($db, 'onboarding')['dismissed']), 'sub' => $sub, 'daysLeft' => $sub ? days_until($sub['end_date']) : null], 'Πίνακας ελέγχου');
    }

    private static function dismissOnboarding(): void
    {
        ShopSettings::set(self::db(), 'onboarding', ['dismissed' => true]);
        redirect('/admin/');
    }

    /* ---------- products ---------- */
    private static function products(): void
    {
        $db = self::db();
        $where = 'p.deleted_at IS NULL';
        $params = [];
        if (($q = in_str('q')) !== '') {
            $where .= ' AND (FOLD(p.name) LIKE ? OR p.sku LIKE ? OR p.barcode = ? OR FOLD(COALESCE(p.brand,\'\')) LIKE ?)';
            array_push($params, '%' . str_fold($q) . '%', "%$q%", $q, '%' . str_fold($q) . '%');
        }
        $st = in_str('status');
        if ($st === 'active') $where .= ' AND p.is_active = 1';
        elseif ($st === 'inactive') $where .= ' AND p.is_active = 0';
        elseif ($st === 'out') $where .= " AND (p.availability = 'out_of_stock' OR (p.manage_stock = 1 AND p.stock <= 0))";
        elseif ($st === 'featured') $where .= ' AND p.featured = 1';
        if ($cat = in_int('category')) {
            $ids = Categories::descendantIds($db, $cat);
            $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))';
            $params = array_merge($params, $ids);
        }
        $sorts = ['newest' => 'p.id DESC', 'oldest' => 'p.id ASC', 'name' => 'p.name COLLATE NOCASE', 'price_asc' => 'p.price ASC', 'price_desc' => 'p.price DESC', 'stock' => 'p.stock ASC', 'updated' => 'p.updated_at DESC'];
        $sort = $sorts[in_str('sort', 'newest')] ?? $sorts['newest'];
        $per = in_array(in_int('per', 50), [25, 50, 100, 200], true) ? in_int('per', 50) : 50;
        $total = (int) db_val($db, "SELECT COUNT(*) FROM products p WHERE $where", $params);
        $pg = paginate($total, $per, in_int('page', 1));
        $rows = db_all($db, "SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.primary_category_id WHERE $where ORDER BY $sort LIMIT $per OFFSET {$pg['offset']}", $params);
        $rows = Products::withImages($db, $rows);
        ShopAdmin::render('admin/products', ['rows' => $rows, 'pg' => $pg, 'cats' => Categories::flat($db)], 'Προϊόντα', [['Προϊόντα', null]]);
    }

    private static function productForm(?int $id): void
    {
        $db = self::db();
        $p = $id ? Products::find($db, $id) : null;
        if ($id && !$p) { ShopAdmin::notFound(); return; }
        $data = ['p' => $p, 'cats' => Categories::flat($db), 'catIds' => $p ? Products::categoryIds($db, $id) : [], 'images' => $p ? Products::images($db, $id) : [],
            'variants' => $p ? Products::variants($db, $id) : [], 'attributes' => $p ? Products::attributes($db, $id) : [], 'faq' => $p ? jd($p['faq']) : [],
            'brands' => db_col($db, "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand <> '' AND deleted_at IS NULL ORDER BY brand LIMIT 300"),
            'sold' => $p ? (int) db_val($db, 'SELECT COALESCE(SUM(qty),0) FROM order_items WHERE product_id = ?', [$id]) : 0];
        ShopAdmin::render('admin/product-form', $data, $p ? $p['name'] : 'Νέο προϊόν', [['Προϊόντα', '/admin/products'], [$p ? $p['name'] : 'Νέο προϊόν', null]]);
        clear_old();
    }

    private static function productSave(): void
    {
        $db = self::db();
        $id = in_int('id') ?: null;
        $in = $_POST;
        $in['price'] = in_float('price');
        $in['sale_price'] = in_float('sale_price');
        $in['cost_price'] = in_float('cost_price');
        $in['faq'] = ShopAdmin::faqFromPost();
        $in['category_ids'] = in_arr('category_ids');
        $in['gallery'] = in_arr('gallery');
        $in['attributes'] = array_values(array_filter(in_arr('attributes'), 'is_array'));
        $in['variants'] = array_values(array_filter(in_arr('variants'), 'is_array'));
        $in['variant_option_names'] = array_values(array_filter(array_map('trim', explode('/', in_str('variant_option_names')))));
        $in['_form'] = 1;
        $in['is_active'] = in_bool('is_active');
        $res = Products::save($db, self::shop(), $in, $id);
        if (!$res['ok']) { flash('bad', $res['error']); keep_old(); redirect($id ? '/admin/products/' . $id : '/admin/products/new'); }
        ShopAdmin::audit($id ? 'product.updated' : 'product.created', 'product', $res['id'], ['name' => in_str('name')]);
        if ($res['warning']) flash('warn', $res['warning']); else flash('ok', 'Το προϊόν αποθηκεύτηκε.');
        redirect(in_str('_after') === 'new' ? '/admin/products/new' : '/admin/products/' . $res['id']);
    }

    private static function productToggle(int $id): void
    {
        $db = self::db();
        if (!Products::find($db, $id)) ShopAdmin::json(['ok' => false, 'error' => 'Δεν βρέθηκε'], 404);
        $want = in_bool('active');
        $res = Quota::setActive($db, Shops::find(App::shopId()), [$id], $want);
        ShopAdmin::audit($want ? 'product.activated' : 'product.deactivated', 'product', $id, ['ok' => $res['ok']]);
        if (!$res['ok']) Audit::log('quota.blocked', App::shopId(), 'product', $id, ['active' => $res['active'], 'limit' => $res['limit']], 'denied');
        $q = Quota::status($db, Shops::find(App::shopId()));
        ShopAdmin::json(['ok' => $res['ok'], 'error' => $res['error'] ?? null, 'quota' => $q]);
    }

    private static function productDuplicate(int $id): void
    {
        $db = self::db();
        $p = Products::find($db, $id);
        if (!$p) { ShopAdmin::notFound(); return; }
        $in = $p;
        $in['name'] = $p['name'] . ' (αντίγραφο)';
        $in['slug'] = '';
        $in['sku'] = $p['sku'] ? $p['sku'] . '-COPY' : null;
        $in['is_active'] = false;
        $in['faq'] = jd($p['faq']);
        $in['category_ids'] = Products::categoryIds($db, $id);
        $in['gallery'] = array_column(Products::images($db, $id), 'id');
        $in['attributes'] = Products::attributes($db, $id);
        $in['variants'] = array_map(fn($v) => array_diff_key($v, ['id' => 1]), Products::variants($db, $id));
        $res = Products::save($db, self::shop(), $in);
        if (!$res['ok']) { flash('bad', $res['error']); redirect('/admin/products/' . $id); }
        ShopAdmin::audit('product.duplicated', 'product', $res['id'], ['from' => $id]);
        flash('ok', 'Δημιουργήθηκε αντίγραφο (ανενεργό).');
        redirect('/admin/products/' . $res['id']);
    }

    private static function productBulk(): void
    {
        $db = self::db();
        $ids = array_values(array_filter(array_map('intval', in_arr('ids'))));
        $action = in_str('action');
        $back = '/admin/products' . (in_str('_qs') !== '' ? '?' . in_str('_qs') : '');
        if (!$ids) { flash('warn', 'Δεν επιλέξατε προϊόντα.'); redirect($back); }
        $shop = Shops::find(App::shopId());
        switch ($action) {
            case 'activate':
            case 'deactivate':
                $r = Quota::setActive($db, $shop, $ids, $action === 'activate');
                if ($r['ok']) flash('ok', ($action === 'activate' ? 'Ενεργοποιήθηκαν ' : 'Απενεργοποιήθηκαν ') . $r['changed'] . ' προϊόντα. Ενεργά: ' . $r['active'] . ' / ' . $r['limit'] . '.');
                else { flash('bad', $r['error']); Audit::log('quota.blocked', App::shopId(), 'product', null, ['bulk' => count($ids)], 'denied'); }
                break;
            case 'delete':
                $n = Products::softDelete($db, $ids);
                flash('ok', "Διαγράφηκαν $n προϊόντα. Οι παραγγελίες που τα περιέχουν παραμένουν ανέπαφες.");
                break;
            case 'category':
                $cid = in_int('category_id');
                if (!Categories::find($db, $cid)) { flash('bad', 'Επιλέξτε κατηγορία.'); break; }
                foreach ($ids as $pid) db_exec($db, 'INSERT OR IGNORE INTO product_categories(product_id, category_id) VALUES(?,?)', [$pid, $cid]);
                if (in_bool('set_primary')) db_exec($db, 'UPDATE products SET primary_category_id = ? WHERE id IN (' . in_placeholders($ids) . ')', array_merge([$cid], $ids));
                flash('ok', 'Η κατηγορία ανατέθηκε σε ' . count($ids) . ' προϊόντα.');
                break;
            case 'price':
                $pct = in_float('percent');
                $fixed = in_float('fixed');
                if ($pct === null && $fixed === null) { flash('bad', 'Συμπληρώστε ποσοστό ή σταθερή τιμή.'); break; }
                $field = in_str('price_field') === 'sale_price' ? 'sale_price' : 'price';
                if ($field === 'sale_price' && $pct !== null) {
                    db_exec($db, 'UPDATE products SET sale_price = CASE WHEN ROUND(price * (1 + ? / 100.0), 2) < price AND ROUND(price * (1 + ? / 100.0), 2) > 0 THEN ROUND(price * (1 + ? / 100.0), 2) ELSE NULL END, updated_at = ? WHERE id IN (' . in_placeholders($ids) . ')', array_merge([$pct, $pct, $pct, now()], $ids));
                } elseif ($pct !== null) {
                    db_exec($db, 'UPDATE products SET price = MAX(0, ROUND(price * (1 + ? / 100.0), 2)), updated_at = ? WHERE id IN (' . in_placeholders($ids) . ')', array_merge([$pct, now()], $ids));
                    db_exec($db, 'UPDATE products SET sale_price = NULL WHERE sale_price >= price AND id IN (' . in_placeholders($ids) . ')', $ids);
                } else {
                    db_exec($db, "UPDATE products SET $field = ?, updated_at = ? WHERE id IN (" . in_placeholders($ids) . ')', array_merge([max(0, $fixed), now()], $ids));
                }
                flash('ok', 'Οι τιμές ενημερώθηκαν σε ' . count($ids) . ' προϊόντα.');
                break;
            case 'export':
                Exporter::stream($db, 'selected', $ids);
            default:
                flash('bad', 'Άγνωστη ενέργεια.');
        }
        ShopAdmin::audit('product.bulk.' . $action, 'product', null, ['count' => count($ids)]);
        redirect($back);
    }

    /* ---------- categories ---------- */
    private static function categories(): void
    {
        $db = self::db();
        $counts = [];
        foreach (db_all($db, 'SELECT pc.category_id, COUNT(*) n, SUM(p.is_active) a FROM product_categories pc JOIN products p ON p.id = pc.product_id AND p.deleted_at IS NULL GROUP BY pc.category_id') as $r) $counts[(int) $r['category_id']] = $r;
        ShopAdmin::render('admin/categories', ['rows' => Categories::flat($db), 'counts' => $counts], 'Κατηγορίες', [['Κατηγορίες', null]]);
    }

    private static function categoryForm(?int $id): void
    {
        $db = self::db();
        $c = $id ? Categories::find($db, $id) : null;
        if ($id && !$c) { ShopAdmin::notFound(); return; }
        $img = $c && $c['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$c['image_id']]) : null;
        ShopAdmin::render('admin/category-form', ['c' => $c, 'cats' => array_filter(Categories::flat($db), fn($x) => !$id || !in_array((int) $x['id'], Categories::descendantIds($db, $id), true)), 'img' => $img, 'faq' => $c ? jd($c['faq']) : []],
            $c ? $c['name'] : 'Νέα κατηγορία', [['Κατηγορίες', '/admin/categories'], [$c['name'] ?? 'Νέα', null]]);
    }

    private static function categorySave(): void
    {
        $id = in_int('id') ?: null;
        $in = $_POST;
        $in['faq'] = ShopAdmin::faqFromPost();
        $r = Categories::save(self::db(), $in, $id);
        if (!$r['ok']) { flash('bad', $r['error']); redirect($id ? '/admin/categories/' . $id : '/admin/categories/new'); }
        ShopAdmin::audit($id ? 'category.updated' : 'category.created', 'category', $r['id']);
        flash('ok', 'Η κατηγορία αποθηκεύτηκε.');
        redirect('/admin/categories');
    }

    private static function categoryDelete(int $id): void
    {
        Categories::delete(self::db(), $id);
        ShopAdmin::audit('category.deleted', 'category', $id);
        flash('ok', 'Η κατηγορία διαγράφηκε. Οι υποκατηγορίες μεταφέρθηκαν ένα επίπεδο πάνω· τα προϊόντα δεν διαγράφηκαν.');
        redirect('/admin/categories');
    }

    /* ---------- import / export ---------- */
    private static function importIndex(): void
    {
        $db = self::db();
        ShopAdmin::render('admin/import', ['imports' => db_all($db, 'SELECT * FROM imports ORDER BY id DESC LIMIT 30'), 'cats' => Categories::flat($db), 'canExport' => ShopAdmin::feature('export')],
            'Εισαγωγή / εξαγωγή', [['Εισαγωγή / εξαγωγή', null]]);
    }

    private static function importUpload(): void
    {
        $f = $_FILES['file'] ?? null;
        if (!$f) { flash('bad', 'Επιλέξτε αρχείο.'); redirect('/admin/import'); }
        $r = Importer::stage(self::db(), Shops::find(App::shopId()), $f, (int) ShopAdmin::$user['id']);
        if (!$r['ok']) { flash('bad', $r['error']); redirect('/admin/import'); }
        ShopAdmin::audit('import.uploaded', 'import', $r['import_id'], ['rows' => $r['rows']]);
        redirect('/admin/import/' . $r['import_id']);
    }

    private static function importShow(int $id): void
    {
        $db = self::db();
        $imp = db_one($db, 'SELECT * FROM imports WHERE id = ?', [$id]);
        if (!$imp) { ShopAdmin::notFound(); return; }
        $preview = in_array($imp['status'], ['validated', 'uploaded'], true) ? Importer::preview($db, $id) : null;
        $logs = db_all($db, 'SELECT * FROM import_logs WHERE import_id = ? ORDER BY id DESC LIMIT 200', [$id]);
        $errors = db_all($db, "SELECT row_no, message FROM import_rows WHERE import_id = ? AND status IN ('error','skipped') ORDER BY row_no LIMIT 200", [$id]);
        ShopAdmin::render('admin/import-show', ['imp' => $imp, 'preview' => $preview, 'logs' => $logs, 'errors' => $errors, 'headers' => jd($imp['headers']), 'mapping' => jd($imp['mapping']),
            'progress' => Importer::progress($db, $id), 'quota' => Quota::status($db, self::shop())], 'Εισαγωγή #' . $id, [['Εισαγωγή', '/admin/import'], ['#' . $id, null]]);
    }

    private static function importConfirm(int $id): void
    {
        $r = Importer::confirm(self::db(), $id, in_arr('map'), ['update_existing' => in_bool('update_existing'), 'activate' => in_str('activate'), 'download_images' => in_bool('download_images')]);
        if (!$r['ok']) { flash('bad', $r['error']); redirect('/admin/import/' . $id); }
        ShopAdmin::audit('import.confirmed', 'import', $id);
        redirect('/admin/import/' . $id);
    }

    private static function importProcess(int $id): void
    {
        $p = Importer::processBatch(self::db(), Shops::find(App::shopId()), $id, Importer::BATCH, 6.0);
        ShopAdmin::json(['ok' => true, 'progress' => $p]);
    }

    private static function importCancel(int $id): void
    {
        $db = self::db();
        db_exec($db, "UPDATE imports SET status = 'cancelled', finished_at = ? WHERE id = ? AND status IN ('uploaded','validated','queued','processing')", [now(), $id]);
        Importer::log($db, $id, 'warning', 'Ακυρώθηκε από τον χρήστη.');
        flash('ok', 'Η εισαγωγή ακυρώθηκε. Τα προϊόντα που έχουν ήδη εισαχθεί παραμένουν.');
        redirect('/admin/import');
    }

    private static function importTemplate(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-template.csv"');
        echo "\xEF\xBB\xBF" . "sku,name,price,sale_price,stock,brand,category,short_description,description,images,active,barcode,attributes\n";
        echo "SKU-001,Παράδειγμα προϊόντος,19.90,,10,Μάρκα,Κατηγορία > Υποκατηγορία,Σύντομη περιγραφή,<p>Περιγραφή</p>,https://example.com/image.jpg,1,5200000000000,Χρώμα:Μαύρο;Μέγεθος:M\n";
        exit;
    }

    private static function export(): void
    {
        if (!ShopAdmin::feature('export')) ShopAdmin::forbidden('Η εξαγωγή δεν περιλαμβάνεται στο πακέτο σας.');
        $mode = in_array(in_str('mode'), ['all', 'active', 'inactive', 'category', 'selected'], true) ? in_str('mode') : 'all';
        ShopAdmin::audit('export.csv', 'product', null, ['mode' => $mode]);
        Exporter::stream(self::db(), $mode, in_arr('ids'), in_int('category') ?: null);
    }

    /* ---------- media ---------- */
    private static function media(): void
    {
        $db = self::db();
        $q = in_str('q');
        $where = '1=1';
        $params = [];
        if ($q !== '') { $where = '(title LIKE ? OR alt LIKE ? OR path LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
        if (in_str('json') === '1') {
            $rows = db_all($db, "SELECT * FROM media WHERE $where ORDER BY id DESC LIMIT 120", $params);
            json_out(['items' => array_map(fn($m) => ['id' => (int) $m['id'], 'url' => media_url($m), 'thumb' => media_url($m, 400), 'alt' => $m['alt'], 'title' => $m['title']], $rows)]);
        }
        $total = (int) db_val($db, "SELECT COUNT(*) FROM media WHERE $where", $params);
        $pg = paginate($total, 60, in_int('page', 1));
        $rows = db_all($db, "SELECT * FROM media WHERE $where ORDER BY id DESC LIMIT 60 OFFSET {$pg['offset']}", $params);
        $bytes = (int) db_val($db, 'SELECT COALESCE(SUM(size),0) FROM media');
        ShopAdmin::render('admin/media', ['rows' => $rows, 'pg' => $pg, 'bytes' => $bytes, 'limitMb' => (int) (Shops::plan(self::shop())['storage_limit_mb'] ?? 2048)], 'Πολυμέσα', [['Πολυμέσα', null]]);
    }

    private static function mediaUpload(): void
    {
        $db = self::db();
        $limit = (int) (Shops::plan(self::shop())['storage_limit_mb'] ?? 2048) * 1048576;
        if ((int) db_val($db, 'SELECT COALESCE(SUM(size),0) FROM media') > $limit) {
            $msg = 'Έχετε φτάσει το όριο αποθηκευτικού χώρου του πακέτου.';
            wants_json() ? ShopAdmin::json(['ok' => false, 'error' => $msg]) : (flash('bad', $msg) ?: redirect('/admin/media'));
        }
        $files = $_FILES['file'] ?? null;
        if (!$files) { wants_json() ? ShopAdmin::json(['ok' => false, 'error' => 'Δεν ανέβηκε αρχείο']) : redirect('/admin/media'); }
        $list = is_array($files['name']) ? array_map(fn($i) => ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], array_keys($files['name'])) : [$files];
        $last = null;
        $errors = [];
        foreach ($list as $f) {
            $r = ImageUpload::fromUpload($db, App::shopId(), $f, in_str('alt'));
            if ($r['ok']) { $last = $r['media']; ShopAdmin::audit('media.uploaded', 'media', $r['media']['id']); }
            else $errors[] = $f['name'] . ': ' . $r['error'];
        }
        if (wants_json()) ShopAdmin::json($last ? ['ok' => true, 'id' => (int) $last['id'], 'url' => media_url($last), 'errors' => $errors] : ['ok' => false, 'error' => implode(' ', $errors)]);
        $errors ? flash('bad', implode(' ', $errors)) : flash('ok', 'Οι εικόνες ανέβηκαν.');
        redirect('/admin/media');
    }

    private static function mediaUpdate(int $id): void
    {
        $db = self::db();
        $m = db_one($db, 'SELECT * FROM media WHERE id = ?', [$id]);
        if (!$m) { ShopAdmin::notFound(); return; }
        if (!empty($_FILES['file']['tmp_name'])) {
            // replace: upload new file, then swap references and remove the old one
            $r = ImageUpload::fromUpload($db, App::shopId(), $_FILES['file'], in_str('alt', $m['alt'] ?? ''));
            if (!$r['ok']) { flash('bad', $r['error']); redirect('/admin/media'); }
            $new = (int) $r['media']['id'];
            foreach (['UPDATE products SET image_id = ? WHERE image_id = ?', 'UPDATE product_images SET media_id = ? WHERE media_id = ?', 'UPDATE categories SET image_id = ? WHERE image_id = ?', 'UPDATE posts SET image_id = ? WHERE image_id = ?'] as $sql) db_exec($db, $sql, [$new, $id]);
            ImageUpload::delete($db, $id);
            ShopAdmin::audit('media.replaced', 'media', $new, ['old' => $id]);
            flash('ok', 'Η εικόνα αντικαταστάθηκε σε όλες τις χρήσεις της.');
        } else {
            db_update($db, 'media', ['alt' => in_str('alt', '', 250), 'title' => in_str('title', '', 200)], 'id = ?', [$id]);
            flash('ok', 'Αποθηκεύτηκε.');
        }
        redirect('/admin/media');
    }

    private static function mediaDelete(int $id): void
    {
        $db = self::db();
        $used = (int) db_val($db, 'SELECT (SELECT COUNT(*) FROM product_images WHERE media_id = ?) + (SELECT COUNT(*) FROM products WHERE image_id = ?) + (SELECT COUNT(*) FROM categories WHERE image_id = ?)', [$id, $id, $id]);
        if ($used && !in_bool('force')) { flash('warn', "Η εικόνα χρησιμοποιείται σε $used σημεία. Επιβεβαιώστε τη διαγραφή ξανά."); redirect('/admin/media?confirm=' . $id); }
        db_exec($db, 'DELETE FROM product_images WHERE media_id = ?', [$id]);
        db_exec($db, 'UPDATE products SET image_id = (SELECT media_id FROM product_images pi WHERE pi.product_id = products.id ORDER BY sort LIMIT 1) WHERE image_id = ?', [$id]);
        db_exec($db, 'UPDATE categories SET image_id = NULL WHERE image_id = ?', [$id]);
        ImageUpload::delete($db, $id);
        ShopAdmin::audit('media.deleted', 'media', $id);
        flash('ok', 'Η εικόνα διαγράφηκε.');
        redirect('/admin/media');
    }
}
