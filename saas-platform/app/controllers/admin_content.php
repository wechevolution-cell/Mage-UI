<?php
declare(strict_types=1);

final class AdminContent
{
    public static function routes(Router $r): void
    {
        $r->get('/admin/pages', fn() => self::pages());
        $r->get('/admin/pages/new', fn() => self::pageForm(null));
        $r->get('/admin/pages/{id:\d+}', fn($id) => self::pageForm((int) $id));
        $r->post('/admin/pages/save', fn() => self::pageSave());
        $r->post('/admin/pages/{id:\d+}/delete', fn($id) => self::pageDelete((int) $id));
        $r->get('/admin/posts', fn() => self::posts());
        $r->get('/admin/posts/new', fn() => self::postForm(null));
        $r->get('/admin/posts/{id:\d+}', fn($id) => self::postForm((int) $id));
        $r->post('/admin/posts/save', fn() => self::postSave());
        $r->post('/admin/posts/{id:\d+}/delete', fn($id) => self::postDelete((int) $id));
        $r->post('/admin/pages/markdown', fn() => self::markdownPreview());
        $r->get('/admin/appearance', fn() => self::appearance());
        $r->post('/admin/appearance', fn() => self::appearanceSave());
        $r->get('/admin/homepage', fn() => self::homepage());
        $r->post('/admin/homepage', fn() => self::homepageSave());
        $r->get('/admin/menus', fn() => self::menus());
        $r->post('/admin/menus', fn() => self::menusSave());
    }

    private static function db(): PDO { return ShopAdmin::$db; }

    /* ---------- pages ---------- */
    private static function pages(): void
    {
        ShopAdmin::render('admin/pages', ['rows' => db_all(self::db(), 'SELECT * FROM pages WHERE deleted_at IS NULL ORDER BY sort, title')], 'Σελίδες', [['Σελίδες', null]]);
    }

    private static function pageForm(?int $id): void
    {
        $p = $id ? db_one(self::db(), 'SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id && !$p) { ShopAdmin::notFound(); return; }
        ShopAdmin::render('admin/page-form', ['p' => $p, 'faq' => $p ? jd($p['faq']) : [], 'kind' => 'page'], $p['title'] ?? 'Νέα σελίδα', [['Σελίδες', '/admin/pages'], [$p['title'] ?? 'Νέα', null]]);
    }

    private static function applyMarkdown(array &$in): void
    {
        $md = (string) ($_POST['markdown'] ?? '');
        if (!empty($_FILES['md_file']['tmp_name']) && is_uploaded_file($_FILES['md_file']['tmp_name']) && $_FILES['md_file']['size'] < 2_000_000) {
            $md = (string) file_get_contents($_FILES['md_file']['tmp_name']);
        }
        if (trim($md) === '') return;
        $c = Markdown::convert($md);
        $in['content'] = $c['html'];
        if ($c['faq']) $in['faq'] = array_merge($in['faq'] ?? [], $c['faq']);
        if (trim((string) ($in['title'] ?? '')) === '' && $c['h1']) $in['title'] = $c['h1'];
    }

    private static function pageSave(): void
    {
        $id = in_int('id') ?: null;
        $in = $_POST;
        $in['faq'] = ShopAdmin::faqFromPost();
        self::applyMarkdown($in);
        $r = Pages::save(self::db(), $in, $id);
        if (!$r['ok']) { flash('bad', $r['error']); redirect($id ? '/admin/pages/' . $id : '/admin/pages/new'); }
        ShopAdmin::audit($id ? 'page.updated' : 'page.created', 'page', $r['id']);
        flash('ok', 'Η σελίδα αποθηκεύτηκε.');
        redirect('/admin/pages/' . $r['id']);
    }

    private static function pageDelete(int $id): void
    {
        $db = self::db();
        $p = db_one($db, 'SELECT * FROM pages WHERE id = ?', [$id]);
        if ($p) {
            db_update($db, 'pages', ['deleted_at' => now(), 'slug' => $p['slug'] . '-deleted-' . $id], 'id = ?', [$id]);
            Redirects::save($db, ['source' => '/' . $p['slug'] . '/', 'code' => 410, 'note' => 'Διαγραμμένη σελίδα']);
            ShopAdmin::audit('page.deleted', 'page', $id);
        }
        flash('ok', 'Η σελίδα διαγράφηκε (η παλιά διεύθυνση επιστρέφει 410).');
        redirect('/admin/pages');
    }

    /* ---------- blog ---------- */
    private static function posts(): void
    {
        ShopAdmin::render('admin/posts', ['rows' => db_all(self::db(), 'SELECT * FROM posts WHERE deleted_at IS NULL ORDER BY COALESCE(published_at, created_at) DESC')], 'Blog', [['Blog', null]]);
    }

    private static function postForm(?int $id): void
    {
        $db = self::db();
        $p = $id ? db_one($db, 'SELECT * FROM posts WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id && !$p) { ShopAdmin::notFound(); return; }
        $img = $p && $p['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$p['image_id']]) : null;
        ShopAdmin::render('admin/page-form', ['p' => $p, 'faq' => $p ? jd($p['faq']) : [], 'kind' => 'post', 'img' => $img], $p['title'] ?? 'Νέο άρθρο', [['Blog', '/admin/posts'], [$p['title'] ?? 'Νέο', null]]);
    }

    private static function postSave(): void
    {
        $id = in_int('id') ?: null;
        $in = $_POST;
        $in['faq'] = ShopAdmin::faqFromPost();
        self::applyMarkdown($in);
        $r = Posts::save(self::db(), $in, $id);
        if (!$r['ok']) { flash('bad', $r['error']); redirect($id ? '/admin/posts/' . $id : '/admin/posts/new'); }
        ShopAdmin::audit($id ? 'post.updated' : 'post.created', 'post', $r['id']);
        flash('ok', 'Το άρθρο αποθηκεύτηκε.');
        redirect('/admin/posts/' . $r['id']);
    }

    private static function postDelete(int $id): void
    {
        $db = self::db();
        $p = db_one($db, 'SELECT * FROM posts WHERE id = ?', [$id]);
        if ($p) {
            db_update($db, 'posts', ['deleted_at' => now(), 'slug' => $p['slug'] . '-deleted-' . $id], 'id = ?', [$id]);
            Redirects::save($db, ['source' => '/blog/' . $p['slug'] . '/', 'code' => 410, 'note' => 'Διαγραμμένο άρθρο']);
            ShopAdmin::audit('post.deleted', 'post', $id);
        }
        flash('ok', 'Το άρθρο διαγράφηκε.');
        redirect('/admin/posts');
    }

    private static function markdownPreview(): void
    {
        $c = Markdown::convert((string) ($_POST['markdown'] ?? ''));
        ShopAdmin::json(['ok' => true, 'html' => $c['html'], 'faq' => $c['faq'], 'h1' => $c['h1']]);
    }

    /* ---------- appearance ---------- */
    private static function appearance(): void
    {
        $db = self::db();
        $shop = ShopAdmin::$shop;
        $app = ShopSettings::get($db, 'appearance');
        $m = fn($id) => $id ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$id]) : null;
        $choices = [];
        if ($shop['allow_template_choice']) {
            $allowed = jd($shop['allowed_templates'], []);
            foreach (Templates::availableForPlan((int) $shop['plan_id']) as $t) { if (!$allowed || in_array($t['key'], $allowed, true)) $choices[] = $t; }
        }
        ShopAdmin::render('admin/appearance', ['app' => $app, 'logo' => $m($app['logo_id']), 'favicon' => $m($app['favicon_id']), 'tokens' => ShopSettings::tokens($db, $shop['template_key']),
            'defaults' => Templates::tokens($shop['template_key']), 'manifest' => Templates::manifest($shop['template_key']), 'choices' => $choices, 'canFonts' => ShopAdmin::feature('custom_fonts')],
            'Εμφάνιση', [['Εμφάνιση', null]]);
    }

    private static function appearanceSave(): void
    {
        $db = self::db();
        $shop = ShopAdmin::$shop;
        $app = ShopSettings::get($db, 'appearance');
        $defaults = Templates::tokens($shop['template_key']);
        if (in_bool('reset_tokens')) {
            $app['tokens'] = [];
        } else {
            $tokens = [];
            foreach (array_keys($defaults) as $k) {
                $v = Sanitizer::color(in_str('t_' . $k), $defaults[$k]);
                if (strtoupper($v) !== strtoupper($defaults[$k])) $tokens[$k] = $v;
            }
            $app['tokens'] = $tokens;
        }
        $app['logo_id'] = in_int('logo_id') ?: null;
        $app['favicon_id'] = in_int('favicon_id') ?: null;
        $app['logo_text'] = in_str('logo_text', '', 60);
        if (ShopAdmin::feature('custom_fonts')) $app['font'] = in_array(in_str('font'), ['default', 'serif', 'sans', 'rounded'], true) ? in_str('font') : 'default';
        ShopSettings::set($db, 'appearance', $app);
        // Template choice (only when the Super Admin allows it)
        $tpl = in_str('template_key');
        if ($tpl !== '' && $tpl !== $shop['template_key'] && $shop['allow_template_choice']) {
            $allowed = jd($shop['allowed_templates'], []);
            if ((!$allowed || in_array($tpl, $allowed, true)) && Templates::isAllowed($tpl, (int) $shop['plan_id'])) {
                Shops::changeTemplate((int) $shop['id'], $tpl);
                flash('ok', 'Το template άλλαξε. Τα δεδομένα σας δεν επηρεάστηκαν.');
            }
        }
        ShopAdmin::audit('appearance.updated', 'settings', 'appearance');
        flash('ok', 'Η εμφάνιση αποθηκεύτηκε.');
        redirect('/admin/appearance');
    }

    /* ---------- homepage CMS ---------- */
    private static function homepage(): void
    {
        $db = self::db();
        $hp = ShopSettings::get($db, 'homepage');
        $mediaIds = array_filter(array_merge([$hp['hero']['image_id'] ?? null, $hp['story']['image_id'] ?? null], array_column($hp['banners']['items'] ?? [], 'image_id'), array_column($hp['brands']['items'] ?? [], 'image_id')));
        $media = [];
        if ($mediaIds) foreach (db_all($db, 'SELECT * FROM media WHERE id IN (' . in_placeholders(array_values($mediaIds)) . ')', array_values($mediaIds)) as $m) $media[$m['id']] = $m;
        ShopAdmin::render('admin/homepage', ['hp' => $hp, 'media' => $media, 'cats' => Categories::flat($db)], 'Αρχική σελίδα', [['Αρχική σελίδα', null]]);
    }

    private static function homepageSave(): void
    {
        $db = self::db();
        $hp = ShopSettings::get($db, 'homepage');
        $t = fn($k, $max = 300) => Sanitizer::text($_POST[$k] ?? '', $max);
        $u = fn($k) => Sanitizer::url($_POST[$k] ?? '');
        $hp['hero'] = ['enabled' => in_bool('hero_enabled'), 'eyebrow' => $t('hero_eyebrow', 80), 'title' => $t('hero_title', 160), 'subtitle' => $t('hero_subtitle', 400),
            'cta_text' => $t('hero_cta_text', 40), 'cta_url' => $u('hero_cta_url'), 'cta2_text' => $t('hero_cta2_text', 40), 'cta2_url' => $u('hero_cta2_url'), 'image_id' => in_int('hero_image_id') ?: null];
        $hp['benefits'] = ['enabled' => in_bool('benefits_enabled'), 'items' => array_values(array_filter(array_map(fn($b) => ['icon' => in_array($b['icon'] ?? '', ['truck', 'return', 'card', 'shield', 'gift', 'leaf', 'star', 'clock', 'phone'], true) ? $b['icon'] : 'star',
            'title' => Sanitizer::text($b['title'] ?? '', 60), 'text' => Sanitizer::text($b['text'] ?? '', 140)], array_filter(in_arr('benefits'), 'is_array')), fn($b) => $b['title'] !== ''))];
        $hp['categories'] = ['enabled' => in_bool('categories_enabled'), 'title' => $t('categories_title', 80), 'ids' => array_values(array_map('intval', in_arr('categories_ids')))];
        $hp['featured'] = ['enabled' => in_bool('featured_enabled'), 'title' => $t('featured_title', 80), 'count' => max(2, min(24, in_int('featured_count', 8)))];
        $hp['newest'] = ['enabled' => in_bool('newest_enabled'), 'title' => $t('newest_title', 80), 'count' => max(2, min(24, in_int('newest_count', 8)))];
        $hp['banners'] = ['enabled' => in_bool('banners_enabled'), 'items' => array_values(array_map(fn($b) => ['title' => Sanitizer::text($b['title'] ?? '', 100), 'text' => Sanitizer::text($b['text'] ?? '', 200),
            'cta' => Sanitizer::text($b['cta'] ?? '', 40), 'url' => Sanitizer::url($b['url'] ?? ''), 'image_id' => !empty($b['image_id']) ? (int) $b['image_id'] : null], array_slice(array_filter(in_arr('banners'), 'is_array'), 0, 3)))];
        $hp['story'] = ['enabled' => in_bool('story_enabled'), 'eyebrow' => $t('story_eyebrow', 60), 'title' => $t('story_title', 160), 'text' => $t('story_text', 1200),
            'cta_text' => $t('story_cta_text', 40), 'cta_url' => $u('story_cta_url'), 'image_id' => in_int('story_image_id') ?: null];
        $hp['brands'] = ['enabled' => in_bool('brands_enabled'), 'title' => $t('brands_title', 80), 'items' => array_values(array_filter(array_map(fn($b) => ['name' => Sanitizer::text($b['name'] ?? '', 60),
            'url' => Sanitizer::url($b['url'] ?? ''), 'image_id' => !empty($b['image_id']) ? (int) $b['image_id'] : null], array_filter(in_arr('brands'), 'is_array')), fn($b) => $b['name'] !== '' || $b['image_id']))];
        $hp['newsletter'] = ['enabled' => in_bool('newsletter_enabled'), 'title' => $t('newsletter_title', 100), 'text' => $t('newsletter_text', 300)];
        $order = array_values(array_intersect(in_arr('order'), ShopSettings::homepageDefaults()['order']));
        $hp['order'] = $order ?: ShopSettings::homepageDefaults()['order'];
        ShopSettings::set($db, 'homepage', $hp);
        ShopAdmin::audit('homepage.updated', 'settings', 'homepage');
        flash('ok', 'Η αρχική σελίδα αποθηκεύτηκε.');
        redirect('/admin/homepage');
    }

    /* ---------- menus ---------- */
    private static function menus(): void
    {
        $db = self::db();
        Menus::ensure($db);
        $loc = array_key_exists(in_str('location'), Menus::LOCATIONS) ? in_str('location') : 'header';
        $opts = [
            'category' => array_map(fn($c) => ['id' => $c['id'], 'label' => str_repeat('— ', (int) $c['depth']) . $c['name']], Categories::flat($db)),
            'page' => array_map(fn($p) => ['id' => $p['id'], 'label' => $p['title']], db_all($db, 'SELECT id, title FROM pages WHERE deleted_at IS NULL ORDER BY title')),
            'post' => array_map(fn($p) => ['id' => $p['id'], 'label' => $p['title']], db_all($db, 'SELECT id, title FROM posts WHERE deleted_at IS NULL ORDER BY title LIMIT 300')),
            'product' => array_map(fn($p) => ['id' => $p['id'], 'label' => $p['name']], db_all($db, 'SELECT id, name FROM products WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name LIMIT 500')),
        ];
        ShopAdmin::render('admin/menus', ['loc' => $loc, 'tree' => Menus::rawTree($db, $loc), 'opts' => $opts], 'Μενού', [['Μενού', null]]);
    }

    private static function menusSave(): void
    {
        $loc = array_key_exists(in_str('location'), Menus::LOCATIONS) ? in_str('location') : 'header';
        $tree = json_decode((string) ($_POST['tree'] ?? '[]'), true);
        Menus::saveTree(self::db(), $loc, is_array($tree) ? $tree : []);
        ShopAdmin::audit('menu.updated', 'menu', $loc);
        flash('ok', 'Το μενού αποθηκεύτηκε.');
        redirect('/admin/menus?location=' . $loc);
    }
}
