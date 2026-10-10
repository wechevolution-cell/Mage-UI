<?php
declare(strict_types=1);

/** Public storefront for an OPEN shop. All templates render the same data through Theme views. */
final class Storefront
{
    private static PDO $db;
    private static array $shop;

    public static function dispatch(string $path): void
    {
        self::$db = App::$db;
        self::$shop = App::$shop;
        security_headers();
        header('X-Content-Type-Options: nosniff');

        // Signed Super Admin template preview (no data change)
        if (isset($_GET['__preview'], $_GET['__exp'], $_GET['__sig'])) {
            $k = (string) $_GET['__preview'];
            $exp = (int) $_GET['__exp'];
            $ok = $exp > time() && hash_equals(hash_hmac('sha256', self::$shop['id'] . '|' . $k . '|' . $exp, (string) Config::get('app_key')), (string) $_GET['__sig']);
            if ($ok && in_array($k, Templates::keys(), true)) { $_SESSION['preview_tpl'] = [$k, $exp]; redirect('/'); }
        }
        if (isset($_GET['__exit_preview'])) { unset($_SESSION['preview_tpl']); redirect('/'); }
        $tpl = self::$shop['template_key'];
        $preview = null;
        if (!empty($_SESSION['preview_tpl']) && $_SESSION['preview_tpl'][1] > time()) { $tpl = $_SESSION['preview_tpl'][0]; $preview = $tpl; noindex(); }
        Theme::boot($tpl);

        $method = req_method();
        // Redirect manager first (GET/HEAD)
        if (in_array($method, ['GET', 'HEAD'], true)) {
            $qs = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
            if ($r = Redirects::match(self::$db, $path)) {
                if (in_array($r['code'], [410, 451], true)) { self::gone($r['code']); return; }
                redirect($r['target'] . ($qs && !str_contains((string) $r['target'], '?') ? '?' . $qs : ''), $r['code']);
            }
            // Trailing slash consistency for HTML routes
            if ($path !== '/' && !str_ends_with($path, '/') && !preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
                redirect($path . '/' . ($qs ? '?' . $qs : ''), 301);
            }
        }
        self::shareData($preview);

        $r = new Router();
        $r->get('/', fn() => self::home());
        $r->get('/products/', fn() => self::listing(null));
        $r->get('/category/{path:[a-z0-9\-/]+}/', fn($p) => self::listing($p));
        $r->get('/product/{slug:[a-z0-9\-]+}/', fn($s) => self::product($s));
        $r->get('/search/', fn() => self::search());
        $r->get('/cart/', fn() => self::cartPage());
        $r->get('/cart/drawer/', fn() => self::cartDrawer());
        $r->post('/cart/add/', fn() => self::cartAdd());
        $r->post('/cart/update/', fn() => self::cartUpdate());
        $r->get('/checkout/', fn() => self::checkout());
        $r->post('/checkout/', fn() => self::checkoutPlace());
        $r->get('/order/{number:[A-Z0-9\-]+}/', fn($n) => self::orderView($n));
        $r->get('/wishlist/', fn() => self::wishlist());
        $r->get('/api/products/', fn() => self::apiProducts());
        $r->get('/api/search/', fn() => self::apiSearch());
        $r->post('/api/wishlist/', fn() => self::apiWishlist());
        $r->post('/newsletter/', fn() => self::newsletter());
        $r->get('/unsubscribe/', fn() => self::unsubscribe());
        $r->post('/contact/send/', fn() => self::contactSend());
        $r->get('/account/', fn() => self::account());
        $r->get('/account/login/', fn() => self::accountPage('login'));
        $r->post('/account/login/', fn() => self::accountLogin());
        $r->get('/account/register/', fn() => self::accountPage('register'));
        $r->post('/account/register/', fn() => self::accountRegister());
        $r->post('/account/logout/', fn() => self::accountLogout());
        $r->get('/account/forgot/', fn() => self::accountPage('forgot'));
        $r->post('/account/forgot/', fn() => self::accountForgot());
        $r->get('/account/reset/', fn() => self::accountPage('reset'));
        $r->post('/account/reset/', fn() => self::accountReset());
        $r->get('/account/orders/{number:[A-Z0-9\-]+}/', fn($n) => self::accountOrder($n));
        $r->post('/account/addresses/', fn() => self::accountAddress());
        $r->get('/blog/', fn() => self::blog());
        $r->get('/blog/{slug:[a-z0-9\-]+}/', fn($s) => self::post($s));
        $r->get('/sitemap.xml', fn() => SeoFiles::sitemap(self::$db, self::$shop));
        $r->get('/robots.txt', fn() => SeoFiles::robots(self::$db, self::$shop));
        $r->get('/llms.txt', fn() => SeoFiles::llms(self::$db, self::$shop));
        $r->get('/feed/blog.xml', fn() => SeoFiles::rss(self::$db, self::$shop));
        $r->get('/feed/{slug:[a-z0-9\-]+}.xml', fn($s) => self::feed($s));
        $r->get('/{slug:[a-z0-9\-]+}/', fn($s) => self::page($s));
        if (!$r->dispatch($method, $path)) self::notFound();
    }

    /* ---------- shared view data ---------- */
    private static function shareData(?string $preview): void
    {
        $db = self::$db;
        $shop = self::$shop;
        $app = ShopSettings::get($db, 'appearance');
        $logo = !empty($app['logo_id']) ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$app['logo_id']]) : null;
        $favicon = !empty($app['favicon_id']) ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$app['favicon_id']]) : null;
        $customer = Auth::user($db, 'customer', 'customers');
        Theme::$data = [
            'shop' => $shop, 'db' => $db, 'store' => ShopSettings::get($db, 'store'), 'seoCfg' => ShopSettings::get($db, 'seo'), 'storeName' => ShopSettings::storeName($db, $shop),
            'logo' => $logo, 'favicon' => $favicon, 'logoText' => $app['logo_text'] ?: ShopSettings::storeName($db, $shop), 'tokens' => ShopSettings::tokens($db, Theme::$key),
            'fontChoice' => Shops::feature($shop, 'custom_fonts') ? ($app['font'] ?? 'default') : 'default',
            'menu' => Menus::get($db, 'header'), 'footerMenu' => Menus::get($db, 'footer'), 'footerMenu2' => Menus::get($db, 'footer2'),
            'catTree' => Categories::tree($db, true), 'cartCount' => Cart::count(), 'customer' => $customer, 'layout' => Theme::opt('layout', []),
            'tpl' => Theme::$key, 'preview' => $preview, 'shipping' => ShopSettings::get($db, 'shipping'), 'cookies' => ShopSettings::get($db, 'cookies'),
            'popup' => Shops::feature($shop, 'popups') ? Popups::active($db, req_path() === '/') : null, 'hasBlog' => Shops::feature($shop, 'blog') && (bool) db_val($db, 'SELECT 1 FROM posts WHERE ' . Posts::publishedSql() . ' LIMIT 1'),
        ];
        Seo::reset();
        $store = Theme::$data['store'];
        $base = rtrim(base_url(), '/');
        $same = array_values(array_filter($store['socials'] ?? []));
        $org = ['@type' => Theme::$data['seoCfg']['org_type'] ?: 'OnlineStore', '@id' => $base . '/#organization', 'name' => Theme::$data['storeName'], 'url' => $base . '/'];
        if ($logo) $org['logo'] = $base . media_url($logo);
        if (!empty($store['email'])) $org['email'] = $store['email'];
        if (!empty($store['phone'])) $org['telephone'] = $store['phone'];
        if (!empty($store['address'])) $org['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $store['address'], 'addressLocality' => $store['city'] ?? '', 'postalCode' => $store['postcode'] ?? '', 'addressCountry' => 'GR'];
        if (!empty($store['vat_number'])) $org['vatID'] = $store['vat_number'];
        if ($same) $org['sameAs'] = $same;
        Seo::add($org);
        Seo::add(['@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $base . '/', 'name' => Theme::$data['storeName'], 'inLanguage' => 'el', 'publisher' => ['@id' => $base . '/#organization'],
            'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base . '/search/?q={search_term_string}'], 'query-input' => 'required name=search_term_string']]);
    }

    private static function meta(string $title, string $description = '', array $extra = []): void
    {
        $cfg = Theme::$data['seoCfg'];
        $suffix = trim((string) $cfg['title_suffix']);
        $full = $title . ($suffix !== '' && !str_contains($title, $suffix) ? ' ' . $suffix : '');
        $og = !empty($cfg['default_og_image_id']) ? db_one(self::$db, 'SELECT * FROM media WHERE id = ?', [$cfg['default_og_image_id']]) : null;
        Seo::set(['title' => $full, 'description' => $description ?: $cfg['default_description'], 'site_name' => Theme::$data['storeName'],
            'canonical' => abs_url(req_path()), 'image' => $og ? abs_url(media_url($og)) : (Theme::$data['logo'] ? abs_url(media_url(Theme::$data['logo'])) : null)] + $extra);
        Seo::add(['@type' => 'WebPage', '@id' => ($extra['canonical'] ?? abs_url(req_path())) . '#webpage', 'url' => $extra['canonical'] ?? abs_url(req_path()), 'name' => $title,
            'isPartOf' => ['@id' => rtrim(base_url(), '/') . '/#website'], 'inLanguage' => 'el']);
    }

    private static function render(string $view, array $data = [], int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        Theme::$data = array_merge(Theme::$data, $data);
        $content = Theme::partial($view, $data);
        echo Theme::partial('layout', ['content' => $content, 'view' => $view]);
    }

    /* ---------- pages ---------- */
    private static function home(): void
    {
        $db = self::$db;
        $hp = ShopSettings::get($db, 'homepage');
        $cfg = Theme::$data['seoCfg'];
        $featured = $hp['featured']['enabled'] ? Catalog::query($db, self::$shop, ['featured' => 1, 'per' => $hp['featured']['count']])['items'] : [];
        if ($hp['featured']['enabled'] && !$featured) $featured = Catalog::query($db, self::$shop, ['per' => $hp['featured']['count']])['items'];
        $newest = $hp['newest']['enabled'] ? Catalog::query($db, self::$shop, ['sort' => 'newest', 'per' => $hp['newest']['count']])['items'] : [];
        $catIds = array_map('intval', $hp['categories']['ids'] ?? []);
        $cats = $catIds ? db_all($db, 'SELECT * FROM categories WHERE deleted_at IS NULL AND status = \'active\' AND id IN (' . in_placeholders($catIds) . ') ORDER BY sort, name', $catIds)
            : db_all($db, "SELECT * FROM categories WHERE deleted_at IS NULL AND status = 'active' AND parent_id IS NULL ORDER BY sort, name LIMIT 8");
        $counts = Catalog::counts($db, self::$shop);
        foreach ($cats as &$c) {
            $c['count'] = $counts[(int) $c['id']] ?? 0;
            $c['image'] = $c['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$c['image_id']]) : null;
            if (!$c['image']) {
                $pid = db_one($db, 'SELECT p.image_id FROM products p JOIN product_categories pc ON pc.product_id = p.id WHERE pc.category_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL AND p.image_id IS NOT NULL LIMIT 1', [$c['id']]);
                $c['image'] = $pid ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$pid['image_id']]) : null;
            }
        }
        unset($c);
        $mediaIds = array_filter(array_merge([$hp['hero']['image_id'] ?? null, $hp['story']['image_id'] ?? null], array_column($hp['banners']['items'] ?? [], 'image_id'), array_column($hp['brands']['items'] ?? [], 'image_id')));
        $media = [];
        if ($mediaIds) foreach (db_all($db, 'SELECT * FROM media WHERE id IN (' . in_placeholders(array_values($mediaIds)) . ')', array_values($mediaIds)) as $m) $media[(int) $m['id']] = $m;
        self::meta($cfg['site_title'] ?: Theme::$data['storeName'], $cfg['default_description'], ['canonical' => rtrim(base_url(), '/') . '/']);
        if ($featured) {
            Seo::add(['@type' => 'ItemList', 'name' => $hp['featured']['title'], 'itemListElement' => array_map(fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url(Products::url($p)), 'name' => $p['name']], $featured, array_keys($featured))]);
        }
        self::render('home', ['hp' => $hp, 'featured' => $featured, 'newest' => $newest, 'homeCats' => $cats, 'hpMedia' => $media]);
    }

    private static function listing(?string $path): void
    {
        $db = self::$db;
        $cat = null;
        $catIds = [];
        $crumbs = [['Αρχική', '/']];
        if ($path !== null) {
            $cat = Categories::byPath($db, $path);
            if (!$cat) { self::notFound(); return; }
            $catIds = Categories::descendantIds($db, (int) $cat['id']);
            foreach (Categories::ancestors($db, $cat) as $a) $crumbs[] = [$a['name'], Categories::url($a)];
            $crumbs[] = [$cat['name'], Categories::url($cat)];
        } else {
            $crumbs[] = ['Προϊόντα', '/products/'];
        }
        $attrs = [];
        foreach ((array) ($_GET['f'] ?? []) as $name => $vals) { if (is_string($name)) $attrs[mb_substr($name, 0, 100)] = array_slice(array_map('strval', (array) $vals), 0, 30); }
        $opt = ['category_ids' => $catIds, 'sort' => array_key_exists(in_str('sort'), Catalog::SORTS) ? in_str('sort') : 'recommended', 'page' => in_int('page', 1),
            'per' => in_int('per', (int) (Theme::opt('layout.grid', 4)) * 4) ?: 24, 'min' => in_str('min'), 'max' => in_str('max'), 'in_stock' => in_str('stock') === '1',
            'on_sale' => in_str('sale') === '1', 'brands' => array_slice(array_map('strval', (array) ($_GET['brand'] ?? [])), 0, 30), 'attrs' => $attrs];
        $res = Catalog::query($db, self::$shop, $opt);
        $facets = Catalog::facets($db, self::$shop, $catIds);
        $children = $cat ? db_all($db, "SELECT * FROM categories WHERE parent_id = ? AND deleted_at IS NULL AND status = 'active' ORDER BY sort, name", [$cat['id']])
            : db_all($db, "SELECT * FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL AND status = 'active' ORDER BY sort, name");
        $title = $cat ? ($cat['seo_title'] ?: $cat['name']) : 'Όλα τα προϊόντα';
        $desc = $cat ? ($cat['seo_description'] ?: excerpt($cat['description'] ?: $cat['content'], 155)) : '';
        $filtered = $_GET && array_diff(array_keys($_GET), ['page']);
        self::meta($title . ($res['pager']['page'] > 1 ? ' — Σελίδα ' . $res['pager']['page'] : ''), $desc, [
            'canonical' => $cat && $cat['canonical_url'] ? $cat['canonical_url'] : abs_url(req_path()) . ($res['pager']['page'] > 1 ? '?page=' . $res['pager']['page'] : ''),
            'robots' => ($cat['robots'] ?? null) ?: ($filtered ? 'noindex, follow' : null)]);
        Seo::crumbs($crumbs);
        if ($res['items']) Seo::add(['@type' => 'ItemList', 'itemListElement' => array_map(fn($p, $i) => ['@type' => 'ListItem', 'position' => $res['pager']['offset'] + $i + 1, 'url' => abs_url(Products::url($p))], $res['items'], array_keys($res['items']))]);
        if ($cat && ($faq = jd($cat['faq']))) { if ($n = Seo::faqNode($faq, abs_url(req_path()))) Seo::add($n); }
        self::render('listing', ['cat' => $cat, 'res' => $res, 'facets' => $facets, 'children' => $children, 'crumbs' => $crumbs, 'opt' => $opt, 'faq' => $cat ? jd($cat['faq']) : [],
            'heading' => $cat ? $cat['name'] : 'Όλα τα προϊόντα']);
    }

    private static function search(): void
    {
        $q = in_str('q', '', 120);
        $res = $q !== '' ? Catalog::query(self::$db, self::$shop, ['q' => $q, 'page' => in_int('page', 1), 'per' => 24, 'sort' => array_key_exists(in_str('sort'), Catalog::SORTS) ? in_str('sort') : 'recommended'])
            : ['items' => [], 'total' => 0, 'pager' => paginate(0, 24, 1)];
        self::meta($q !== '' ? 'Αναζήτηση: ' . $q : 'Αναζήτηση', '', ['robots' => 'noindex, follow']);
        Seo::crumbs([['Αρχική', '/'], ['Αναζήτηση', '/search/']]);
        self::render('listing', ['cat' => null, 'res' => $res, 'facets' => ['attrs' => [], 'brands' => [], 'min' => 0, 'max' => 0], 'children' => [], 'crumbs' => [['Αρχική', '/'], ['Αναζήτηση', null]],
            'opt' => ['sort' => in_str('sort', 'recommended')], 'faq' => [], 'heading' => $q !== '' ? 'Αποτελέσματα για «' . $q . '»' : 'Αναζήτηση', 'searchQuery' => $q]);
    }

    private static function product(string $slug): void
    {
        $db = self::$db;
        $p = Catalog::product($db, self::$shop, $slug);
        if (!$p) { self::notFound(); return; }
        $crumbs = [['Αρχική', '/']];
        if ($p['category']) {
            foreach (Categories::ancestors($db, $p['category']) as $a) $crumbs[] = [$a['name'], Categories::url($a)];
            $crumbs[] = [$p['category']['name'], Categories::url($p['category'])];
        }
        $crumbs[] = [$p['name'], Products::url($p)];
        $url = abs_url(Products::url($p));
        $img = $p['images'][0] ?? null;
        self::meta($p['seo_title'] ?: $p['name'], $p['seo_description'] ?: excerpt($p['short_description'] ?: $p['description'], 155), [
            'canonical' => $p['canonical_url'] ?: $url, 'robots' => $p['robots'] ?: null, 'og_type' => 'product', 'image' => $img ? abs_url(media_url($img)) : null, 'price' => number_format(Products::effectivePrice($p), 2, '.', '')]);
        Seo::crumbs($crumbs);
        $prodNode = ['@type' => 'Product', '@id' => $url . '#product', 'name' => $p['name'], 'url' => $url, 'description' => excerpt($p['description'] ?: $p['short_description'], 5000) ?: $p['name']];
        if ($p['images']) $prodNode['image'] = array_map(fn($m) => abs_url(media_url($m)), array_slice($p['images'], 0, 8));
        if ($p['sku']) $prodNode['sku'] = $p['sku'];
        if ($p['mpn']) $prodNode['mpn'] = $p['mpn'];
        if ($p['barcode'] && preg_match('/^\d{8,14}$/', $p['barcode'])) $prodNode['gtin' . strlen($p['barcode'])] = $p['barcode'];
        if ($p['brand']) $prodNode['brand'] = ['@type' => 'Brand', 'name' => $p['brand']];
        if ($p['category']) $prodNode['category'] = $p['category']['name'];
        $avail = !Products::inStock($p) ? 'OutOfStock' : ($p['availability'] === 'preorder' ? 'PreOrder' : ($p['availability'] === 'backorder' ? 'BackOrder' : 'InStock'));
        $offer = ['@type' => 'Offer', 'url' => $url, 'priceCurrency' => 'EUR', 'price' => number_format(Products::effectivePrice($p), 2, '.', ''), 'availability' => 'https://schema.org/' . $avail,
            'itemCondition' => 'https://schema.org/NewCondition', 'seller' => ['@id' => rtrim(base_url(), '/') . '/#organization']];
        if ($p['variants']) {
            $prices = array_map(fn($v) => $v['price'] !== null ? (float) ($v['sale_price'] && $v['sale_price'] < $v['price'] ? $v['sale_price'] : $v['price']) : Products::effectivePrice($p), $p['variants']);
            $offer = ['@type' => 'AggregateOffer', 'priceCurrency' => 'EUR', 'lowPrice' => number_format(min($prices), 2, '.', ''), 'highPrice' => number_format(max($prices), 2, '.', ''), 'offerCount' => count($p['variants']), 'availability' => 'https://schema.org/' . $avail, 'url' => $url];
        }
        $prodNode['offers'] = $offer;
        $custom = jd($p['schema_json'], null);
        if (is_array($custom)) { unset($custom['aggregateRating'], $custom['review'], $custom['@context']); $prodNode = array_merge($prodNode, $custom); }
        Seo::add($prodNode);
        $faq = jd($p['faq']);
        if ($faq && ($n = Seo::faqNode($faq, $url))) Seo::add($n);
        $related = Catalog::related($db, self::$shop, $p, 4);
        $variantsJs = array_map(fn($v) => ['id' => (int) $v['id'], 'name' => $v['name'], 'options' => jd($v['options']), 'sku' => $v['sku'], 'price' => $v['price'] !== null ? (float) $v['price'] : null,
            'sale' => $v['sale_price'] !== null ? (float) $v['sale_price'] : null, 'stock' => (int) $v['stock'], 'available' => !$p['manage_stock'] || (int) $v['stock'] > 0 || in_array($p['availability'], ['preorder', 'backorder'], true)], $p['variants']);
        $options = [];
        foreach ($p['variants'] as $v) foreach (jd($v['options']) as $k => $val) { $options[$k][$val] = true; }
        self::render('product', ['p' => $p, 'crumbs' => $crumbs, 'related' => $related, 'faq' => $faq, 'variantsJs' => $variantsJs, 'options' => array_map('array_keys', $options)]);
    }

    private static function page(string $slug): void
    {
        $db = self::$db;
        $p = Pages::bySlug($db, $slug);
        if (!$p) { self::notFound(); return; }
        $url = abs_url('/' . $p['slug'] . '/');
        self::meta($p['seo_title'] ?: $p['title'], $p['seo_description'] ?: excerpt($p['content'], 155), ['canonical' => $p['canonical_url'] ?: $url, 'robots' => $p['robots'] ?: null]);
        Seo::crumbs([['Αρχική', '/'], [$p['title'], '/' . $p['slug'] . '/']]);
        $faq = jd($p['faq']);
        if ($faq && ($n = Seo::faqNode($faq, $url))) Seo::add($n);
        if ($p['kind'] === 'contact') Seo::add(['@type' => 'ContactPage', 'url' => $url, 'name' => $p['title']]);
        $_SESSION['form_ts'] = time();
        self::render('page', ['page' => $p, 'faq' => $faq]);
    }

    private static function blog(): void
    {
        if (!Shops::feature(self::$shop, 'blog')) { self::notFound(); return; }
        $db = self::$db;
        $total = (int) db_val($db, 'SELECT COUNT(*) FROM posts WHERE ' . Posts::publishedSql());
        $pg = paginate($total, 12, in_int('page', 1));
        $posts = db_all($db, 'SELECT * FROM posts WHERE ' . Posts::publishedSql() . " ORDER BY published_at DESC LIMIT 12 OFFSET {$pg['offset']}");
        foreach ($posts as &$p) $p['image'] = $p['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$p['image_id']]) : null;
        self::meta('Blog', 'Άρθρα και οδηγοί από το ' . Theme::$data['storeName'] . '.');
        Seo::crumbs([['Αρχική', '/'], ['Blog', '/blog/']]);
        self::render('blog', ['posts' => $posts, 'pg' => $pg]);
    }

    private static function post(string $slug): void
    {
        if (!Shops::feature(self::$shop, 'blog')) { self::notFound(); return; }
        $db = self::$db;
        $p = db_one($db, 'SELECT * FROM posts WHERE slug = ? AND ' . Posts::publishedSql(), [$slug]);
        if (!$p) { self::notFound(); return; }
        $p['image'] = $p['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$p['image_id']]) : null;
        [$html, $toc] = Posts::toc((string) $p['content']);
        $url = abs_url('/blog/' . $p['slug'] . '/');
        self::meta($p['seo_title'] ?: $p['title'], $p['seo_description'] ?: ($p['excerpt'] ?: excerpt($p['content'], 155)), ['og_type' => 'article', 'image' => $p['image'] ? abs_url(media_url($p['image'])) : null]);
        Seo::crumbs([['Αρχική', '/'], ['Blog', '/blog/'], [$p['title'], '/blog/' . $p['slug'] . '/']]);
        $node = ['@type' => 'BlogPosting', '@id' => $url . '#article', 'headline' => mb_substr($p['title'], 0, 110), 'url' => $url, 'datePublished' => date('c', strtotime($p['published_at'])),
            'dateModified' => date('c', strtotime($p['updated_at'] ?: $p['published_at'])), 'inLanguage' => 'el', 'publisher' => ['@id' => rtrim(base_url(), '/') . '/#organization'],
            'mainEntityOfPage' => $url, 'description' => $p['excerpt'] ?: excerpt($p['content'], 200)];
        if ($p['author']) $node['author'] = ['@type' => 'Person', 'name' => $p['author']];
        if ($p['image']) $node['image'] = abs_url(media_url($p['image']));
        Seo::add($node);
        $faq = jd($p['faq']);
        if ($faq && ($n = Seo::faqNode($faq, $url))) Seo::add($n);
        $related = db_all($db, 'SELECT * FROM posts WHERE id <> ? AND ' . Posts::publishedSql() . ' ORDER BY (category = ?) DESC, published_at DESC LIMIT 3', [$p['id'], $p['category']]);
        self::render('post', ['post' => $p, 'html' => $html, 'toc' => $toc, 'faq' => $faq, 'related' => $related]);
    }

    /* ---------- cart ---------- */
    private static function cartData(): array
    {
        $lines = Cart::lines(self::$db, self::$shop);
        return ['lines' => $lines, 'totals' => Cart::totals(self::$db, $lines)];
    }

    private static function cartPage(): void
    {
        self::meta('Καλάθι', '', ['robots' => 'noindex, follow']);
        Seo::crumbs([['Αρχική', '/'], ['Καλάθι', '/cart/']]);
        self::render('cart', self::cartData() + ['suggest' => Catalog::query(self::$db, self::$shop, ['featured' => 1, 'per' => 4, 'exclude' => array_map(fn($l) => $l['product']['id'], Cart::lines(self::$db, self::$shop))])['items']]);
    }

    private static function cartDrawer(): void
    {
        header('Cache-Control: no-store');
        $d = self::cartData();
        json_out(['ok' => true, 'html' => Theme::partial('cart-drawer-body', $d), 'count' => $d['totals']['count'], 'total' => money($d['totals']['total'])]);
    }

    private static function cartAdd(): void
    {
        $res = Cart::add(self::$db, self::$shop, in_int('product_id'), in_int('variant_id') ?: null, max(1, in_int('qty', 1)));
        if (wants_json()) {
            $d = self::cartData();
            json_out(['ok' => $res['ok'], 'error' => $res['error'] ?? null, 'html' => Theme::partial('cart-drawer-body', $d), 'count' => $d['totals']['count'], 'total' => money($d['totals']['total'])], $res['ok'] ? 200 : 422);
        }
        $res['ok'] ? flash('ok', 'Το προϊόν προστέθηκε στο καλάθι.') : flash('bad', $res['error']);
        redirect('/cart/');
    }

    private static function cartUpdate(): void
    {
        foreach (in_arr('qty') as $key => $q) { if (is_string($key) && preg_match('/^\d+:\d+$/', $key)) Cart::update($key, (int) $q); }
        if (($rm = in_str('remove')) !== '') Cart::update($rm, 0);
        if (wants_json()) {
            $d = self::cartData();
            json_out(['ok' => true, 'html' => Theme::partial('cart-drawer-body', $d), 'count' => $d['totals']['count'], 'total' => money($d['totals']['total'])]);
        }
        redirect('/cart/');
    }

    /* ---------- checkout ---------- */
    private static function checkout(): void
    {
        $d = self::cartData();
        if (!$d['lines']) { flash('info', 'Το καλάθι σας είναι άδειο.'); redirect('/cart/'); }
        $c = Theme::$data['customer'];
        $addr = $c ? db_one(self::$db, 'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id LIMIT 1', [$c['id']]) : null;
        self::meta('Ολοκλήρωση παραγγελίας', '', ['robots' => 'noindex, nofollow']);
        Seo::crumbs([['Αρχική', '/'], ['Καλάθι', '/cart/'], ['Ταμείο', '/checkout/']]);
        self::render('checkout', $d + ['payments' => ShopSettings::get(self::$db, 'payments'), 'checkoutCfg' => ShopSettings::get(self::$db, 'checkout'), 'errors' => $_SESSION['checkout_errors'] ?? [],
            'prefill' => ($_SESSION['_old'] ?? []) + ($c ? ['email' => $c['email'], 'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'phone' => $c['phone']] : []) + ($addr ? ['address' => $addr['address'], 'city' => $addr['city'], 'postcode' => $addr['postcode']] : []),
            'termsUrl' => '/terms/', 'privacyUrl' => '/privacy/']);
        unset($_SESSION['checkout_errors'], $_SESSION['_old']);
    }

    private static function checkoutPlace(): void
    {
        if (!rate_limit(self::$db, 'checkout|' . client_ip(), 10, 600)) { flash('bad', 'Πολλές προσπάθειες. Δοκιμάστε ξανά σε λίγα λεπτά.'); redirect('/checkout/'); }
        $res = Orders::place(self::$db, self::$shop, $_POST, Theme::$data['customer']);
        if (!$res['ok']) {
            $_SESSION['checkout_errors'] = $res['errors'] ?? [];
            keep_old();
            flash('bad', $res['error']);
            redirect('/checkout/');
        }
        $o = $res['order'];
        Audit::$actor = ['type' => 'customer', 'id' => $o['customer_id'], 'name' => $o['email']];
        $_SESSION['last_order'] = $o['number'];
        redirect('/order/' . $o['number'] . '/?t=' . $o['access_token']); // PRG
    }

    private static function orderView(string $number): void
    {
        $db = self::$db;
        $o = db_one($db, 'SELECT * FROM orders WHERE number = ?', [$number]);
        $c = Theme::$data['customer'];
        $ok = $o && (hash_equals($o['access_token'], in_str('t')) || ($c && (int) $o['customer_id'] === (int) $c['id']));
        if (!$ok) { self::notFound(); return; }
        $fresh = ($_SESSION['last_order'] ?? '') === $number;
        self::meta('Παραγγελία ' . $o['number'], '', ['robots' => 'noindex, nofollow']);
        self::render('order', ['order' => $o, 'items' => Orders::items($db, (int) $o['id']), 'fresh' => $fresh, 'payments' => ShopSettings::get($db, 'payments')]);
    }

    /* ---------- wishlist & APIs ---------- */
    private static function wishlist(): void
    {
        $c = Theme::$data['customer'];
        $items = [];
        if ($c) {
            $ids = array_map('intval', db_col(self::$db, 'SELECT product_id FROM wishlist WHERE customer_id = ? ORDER BY created_at DESC', [$c['id']]));
            $items = $ids ? Catalog::query(self::$db, self::$shop, ['ids' => $ids, 'per' => 96])['items'] : [];
        }
        self::meta('Αγαπημένα', '', ['robots' => 'noindex, follow']);
        Seo::crumbs([['Αρχική', '/'], ['Αγαπημένα', '/wishlist/']]);
        self::render('wishlist', ['items' => $items]);
    }

    private static function apiProducts(): void
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', explode(',', in_str('ids'))))), 0, 96);
        $items = $ids ? Catalog::query(self::$db, self::$shop, ['ids' => $ids, 'per' => 96])['items'] : [];
        json_out(['ok' => true, 'html' => implode('', array_map(fn($p) => Theme::partial('product-card', ['p' => $p]), $items)), 'count' => count($items)]);
    }

    private static function apiSearch(): void
    {
        $q = in_str('q', '', 80);
        if (mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);
        $r = Catalog::query(self::$db, self::$shop, ['q' => $q, 'per' => 6]);
        $cats = db_all(self::$db, "SELECT name, path FROM categories WHERE deleted_at IS NULL AND status = 'active' AND FOLD(name) LIKE ? LIMIT 4", ['%' . str_fold($q) . '%']);
        json_out(['ok' => true, 'total' => $r['total'], 'items' => array_map(fn($p) => ['name' => $p['name'], 'url' => Products::url($p), 'price' => money(Products::effectivePrice($p)), 'img' => $p['image'] ? media_url($p['image'], 400) : null], $r['items']),
            'categories' => array_map(fn($c) => ['name' => $c['name'], 'url' => '/category/' . $c['path'] . '/'], $cats)]);
    }

    private static function apiWishlist(): void
    {
        $c = Theme::$data['customer'];
        $pid = in_int('product_id');
        if (!$c) json_out(['ok' => true, 'guest' => true]);
        if (in_bool('remove')) db_exec(self::$db, 'DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?', [$c['id'], $pid]);
        elseif (Catalog::isVisible(self::$db, self::$shop, $pid)) db_exec(self::$db, 'INSERT OR IGNORE INTO wishlist(customer_id, product_id, created_at) VALUES(?,?,?)', [$c['id'], $pid, now()]);
        foreach (array_slice(array_filter(array_map('intval', explode(',', in_str('merge')))), 0, 100) as $m) {
            if (Catalog::isVisible(self::$db, self::$shop, $m)) db_exec(self::$db, 'INSERT OR IGNORE INTO wishlist(customer_id, product_id, created_at) VALUES(?,?,?)', [$c['id'], $m, now()]);
        }
        json_out(['ok' => true, 'ids' => array_map('intval', db_col(self::$db, 'SELECT product_id FROM wishlist WHERE customer_id = ?', [$c['id']]))]);
    }

    /* ---------- forms ---------- */
    private static function spamCheck(string $scope, int $max = 5): ?string
    {
        if (!empty($_POST['website'])) return 'spam';                                  // honeypot
        $ts = (int) ($_POST['_ts'] ?? 0);
        if ($ts && time() - $ts < 3) return 'Η φόρμα υποβλήθηκε πολύ γρήγορα. Δοκιμάστε ξανά.'; // timing check
        if (!rate_limit(self::$db, $scope . '|' . client_ip(), $max, 3600)) return 'Πολλές υποβολές από τη διεύθυνσή σας. Δοκιμάστε ξανά αργότερα.';
        return null;
    }

    private static function newsletter(): void
    {
        $err = self::spamCheck('newsletter', 10);
        $popupId = in_int('popup_id') ?: null;
        $popup = $popupId ? db_one(self::$db, 'SELECT * FROM popups WHERE id = ?', [$popupId]) : null;
        $needConsent = $popup ? (bool) $popup['require_consent'] : true;
        if (!$err && $needConsent && !in_bool('consent')) $err = 'Απαιτείται η συγκατάθεσή σας.';
        $res = $err ? ['ok' => false, 'error' => $err] : Popups::subscribe(self::$db, in_str('email', '', 190), $popup ? 'popup' : in_str('source', 'newsletter', 30), $popupId, in_bool('consent'), $popup['consent_text'] ?? 'Εγγραφή στο newsletter');
        if ($err === 'spam') $res = ['ok' => true];
        if (wants_json()) json_out(['ok' => $res['ok'], 'error' => $res['error'] ?? null, 'message' => $res['ok'] ? 'Ευχαριστούμε! Η εγγραφή σας ολοκληρώθηκε.' : null]);
        $res['ok'] ? flash('ok', 'Ευχαριστούμε! Η εγγραφή σας ολοκληρώθηκε.') : flash('bad', $res['error']);
        back('/');
    }

    private static function unsubscribe(): void
    {
        $t = in_str('t');
        $n = $t !== '' ? db_exec(self::$db, 'UPDATE subscribers SET unsubscribed_at = ? WHERE unsubscribe_token = ? AND unsubscribed_at IS NULL', [now(), $t])->rowCount() : 0;
        self::meta('Διαγραφή από τη λίστα', '', ['robots' => 'noindex']);
        self::render('message', ['heading' => $n ? 'Η διαγραφή ολοκληρώθηκε' : 'Ο σύνδεσμος δεν είναι έγκυρος', 'text' => $n ? 'Δεν θα λαμβάνετε πλέον ενημερώσεις.' : 'Ίσως έχετε ήδη διαγραφεί.']);
    }

    private static function contactSend(): void
    {
        $back = in_str('_back', '/contact/');
        if (!preg_match('#^/[a-z0-9\-/]*$#', $back)) $back = '/contact/';
        $err = self::spamCheck('contact', 5);
        if ($err === 'spam') { flash('ok', 'Ευχαριστούμε! Λάβαμε το μήνυμά σας.'); redirect($back); }
        $email = in_str('email', '', 190);
        if (!$err && (in_str('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen(in_str('message')) < 5)) $err = 'Συμπληρώστε όνομα, έγκυρο email και μήνυμα.';
        if (!$err && !in_bool('consent')) $err = 'Απαιτείται η αποδοχή της πολιτικής απορρήτου.';
        if ($err) { keep_old(); flash('bad', $err); redirect($back . '#contact-form'); }
        db_insert(self::$db, 'contact_messages', ['name' => in_str('name', '', 120), 'email' => $email, 'phone' => in_str('phone', '', 40), 'subject' => in_str('subject', '', 150),
            'message' => in_str('message', '', 5000), 'consent' => 1, 'ip' => client_ip(), 'created_at' => now()]);
        $store = Theme::$data['store'];
        if (!empty($store['email'])) {
            Mailer::send($store['email'], 'Νέο μήνυμα επικοινωνίας: ' . in_str('subject', 'Χωρίς θέμα', 150), Mailer::layout('Νέο μήνυμα από τη φόρμα επικοινωνίας',
                '<p><b>' . e(in_str('name')) . '</b> (' . e($email) . ', ' . e(in_str('phone')) . ')</p><p>' . nl2br(e(in_str('message', '', 5000))) . '</p>', Theme::$data['storeName']), ['shop_id' => App::shopId(), 'reply_to' => $email]);
        }
        clear_old();
        flash('ok', 'Ευχαριστούμε! Λάβαμε το μήνυμά σας και θα σας απαντήσουμε σύντομα.');
        redirect($back); // PRG
    }

    /* ---------- customer accounts ---------- */
    private static function account(): void
    {
        $c = Theme::$data['customer'];
        if (!$c) redirect('/account/login/');
        self::meta('Ο λογαριασμός μου', '', ['robots' => 'noindex, nofollow']);
        Seo::crumbs([['Αρχική', '/'], ['Λογαριασμός', '/account/']]);
        self::render('account', ['mode' => 'dashboard', 'orders' => db_all(self::$db, 'SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$c['id']]),
            'addresses' => db_all(self::$db, 'SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id', [$c['id']])]);
    }

    private static function accountPage(string $mode): void
    {
        if (Theme::$data['customer'] && in_array($mode, ['login', 'register'], true)) redirect('/account/');
        self::meta(['login' => 'Σύνδεση', 'register' => 'Δημιουργία λογαριασμού', 'forgot' => 'Ξέχασα τον κωδικό', 'reset' => 'Νέος κωδικός'][$mode], '', ['robots' => 'noindex, nofollow']);
        $_SESSION['form_ts'] = time();
        self::render('account', ['mode' => $mode, 'token' => in_str('token'), 'email' => in_str('email')]);
    }

    private static function accountLogin(): void
    {
        $res = Auth::attempt(self::$db, 'customer', 'customers', in_str('email', '', 190), (string) ($_POST['password'] ?? ''));
        if (!$res['ok']) { flash('bad', $res['error']); redirect('/account/login/'); }
        Auth::login('customer', $res['user']);
        db_exec(self::$db, 'UPDATE customers SET last_login_at = ? WHERE id = ?', [now(), $res['user']['id']]);
        redirect(in_str('next') === 'checkout' ? '/checkout/' : '/account/');
    }

    private static function accountRegister(): void
    {
        if ($e = self::spamCheck('register', 5)) { if ($e !== 'spam') flash('bad', $e); redirect('/account/register/'); }
        $email = mb_strtolower(in_str('email', '', 190));
        $pass = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('bad', 'Μη έγκυρο email.'); keep_old(); redirect('/account/register/'); }
        if (mb_strlen($pass) < 8) { flash('bad', 'Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.'); keep_old(); redirect('/account/register/'); }
        if (!in_bool('accept')) { flash('bad', 'Απαιτείται η αποδοχή των όρων.'); keep_old(); redirect('/account/register/'); }
        $existing = db_one(self::$db, 'SELECT * FROM customers WHERE email = ?', [$email]);
        if ($existing && $existing['password_hash']) { flash('bad', 'Υπάρχει ήδη λογαριασμός με αυτό το email. Συνδεθείτε ή επαναφέρετε τον κωδικό.'); redirect('/account/login/'); }
        $data = ['first_name' => in_str('first_name', '', 80), 'last_name' => in_str('last_name', '', 80), 'phone' => in_str('phone', '', 40), 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'marketing_consent' => in_bool('newsletter') ? 1 : 0, 'updated_at' => now()];
        if ($existing) { db_update(self::$db, 'customers', $data, 'id = ?', [$existing['id']]); $id = (int) $existing['id']; } // guest → account (keeps order history)
        else { $data += ['email' => $email, 'status' => 'active', 'created_at' => now()]; $id = db_insert(self::$db, 'customers', $data); }
        if (in_bool('newsletter')) Popups::subscribe(self::$db, $email, 'register', null, true, 'Εγγραφή κατά τη δημιουργία λογαριασμού');
        Auth::login('customer', db_one(self::$db, 'SELECT * FROM customers WHERE id = ?', [$id]));
        flash('ok', 'Ο λογαριασμός δημιουργήθηκε.');
        redirect('/account/');
    }

    private static function accountLogout(): void { Auth::logout('customer'); redirect('/'); }

    private static function accountForgot(): void
    {
        if (!rate_limit(self::$db, 'cforgot|' . client_ip(), 5, 900)) { flash('bad', 'Πολλά αιτήματα. Δοκιμάστε αργότερα.'); redirect('/account/forgot/'); }
        $c = db_one(self::$db, "SELECT * FROM customers WHERE email = ? AND password_hash IS NOT NULL AND deleted_at IS NULL", [in_str('email', '', 190)]);
        if ($c) {
            $token = random_token(32);
            db_update(self::$db, 'customers', ['reset_token_hash' => hash('sha256', $token), 'reset_expires' => time() + 3600], 'id = ?', [$c['id']]);
            $url = base_url() . '/account/reset/?token=' . urlencode($token) . '&email=' . urlencode($c['email']);
            Mailer::send($c['email'], 'Επαναφορά κωδικού', Mailer::layout('Επαναφορά κωδικού', '<p><a href="' . e($url) . '">Ορίστε νέο κωδικό</a> (ισχύει 1 ώρα).</p>', Theme::$data['storeName']), ['shop_id' => App::shopId()]);
        }
        flash('ok', 'Αν υπάρχει λογαριασμός, θα λάβετε email με οδηγίες.');
        redirect('/account/login/');
    }

    private static function accountReset(): void
    {
        $c = db_one(self::$db, 'SELECT * FROM customers WHERE email = ?', [in_str('email', '', 190)]);
        if (!$c || !$c['reset_token_hash'] || !hash_equals($c['reset_token_hash'], hash('sha256', in_str('token'))) || (int) $c['reset_expires'] < time()) { flash('bad', 'Ο σύνδεσμος δεν είναι έγκυρος ή έληξε.'); redirect('/account/forgot/'); }
        if (mb_strlen((string) ($_POST['password'] ?? '')) < 8) { flash('bad', 'Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.'); redirect('/account/reset/?token=' . urlencode(in_str('token')) . '&email=' . urlencode($c['email'])); }
        db_update(self::$db, 'customers', ['password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'reset_token_hash' => null, 'reset_expires' => null], 'id = ?', [$c['id']]);
        flash('ok', 'Ο κωδικός άλλαξε. Συνδεθείτε.');
        redirect('/account/login/');
    }

    private static function accountOrder(string $number): void
    {
        $c = Theme::$data['customer'];
        if (!$c) redirect('/account/login/');
        $o = db_one(self::$db, 'SELECT * FROM orders WHERE number = ? AND customer_id = ?', [$number, $c['id']]);
        if (!$o) { self::notFound(); return; }
        self::meta('Παραγγελία ' . $o['number'], '', ['robots' => 'noindex, nofollow']);
        self::render('order', ['order' => $o, 'items' => Orders::items(self::$db, (int) $o['id']), 'fresh' => false, 'payments' => ShopSettings::get(self::$db, 'payments'),
            'history' => db_all(self::$db, 'SELECT * FROM order_history WHERE order_id = ? AND (notify_customer = 1 OR actor = \'customer\') ORDER BY id', [$o['id']])]);
    }

    private static function accountAddress(): void
    {
        $c = Theme::$data['customer'];
        if (!$c) redirect('/account/login/');
        if (($del = in_int('delete')) > 0) { db_exec(self::$db, 'DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$del, $c['id']]); redirect('/account/#addresses'); }
        if (in_str('address') === '' || in_str('city') === '') { flash('bad', 'Συμπληρώστε διεύθυνση και πόλη.'); redirect('/account/#addresses'); }
        $isDefault = !db_val(self::$db, 'SELECT 1 FROM customer_addresses WHERE customer_id = ?', [$c['id']]);
        db_insert(self::$db, 'customer_addresses', ['customer_id' => $c['id'], 'first_name' => in_str('first_name', '', 80), 'last_name' => in_str('last_name', '', 80), 'phone' => in_str('phone', '', 40),
            'address' => in_str('address', '', 200), 'city' => in_str('city', '', 80), 'postcode' => in_str('postcode', '', 10), 'region' => in_str('region', '', 80), 'country' => 'GR', 'is_default' => $isDefault ? 1 : 0, 'created_at' => now()]);
        flash('ok', 'Η διεύθυνση αποθηκεύτηκε.');
        redirect('/account/#addresses');
    }

    /* ---------- feeds ---------- */
    private static function feed(string $slug): void
    {
        $db = self::$db;
        $f = db_one($db, "SELECT * FROM feeds WHERE slug = ? AND status = 'active'", [$slug]);
        if (!$f || !Feeds::allowed(self::$shop, $f)) { self::notFound(); return; }
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo Feeds::output($db, self::$shop, $f);
    }

    /* ---------- errors ---------- */
    public static function notFound(): void
    {
        Redirects::log404(self::$db, req_path());
        self::meta('Η σελίδα δεν βρέθηκε', '', ['robots' => 'noindex, follow']);
        self::render('404', ['suggest' => Catalog::query(self::$db, self::$shop, ['per' => 4, 'featured' => 1])['items']], 404);
    }

    private static function gone(int $code): void
    {
        self::shareData(null);
        self::meta($code === 451 ? 'Μη διαθέσιμο για νομικούς λόγους' : 'Η σελίδα αφαιρέθηκε', '', ['robots' => 'noindex']);
        self::render('message', ['heading' => $code === 451 ? 'Μη διαθέσιμο για νομικούς λόγους' : 'Η σελίδα αυτή δεν υπάρχει πλέον', 'text' => 'Το περιεχόμενο αφαιρέθηκε οριστικά.', 'code' => $code], $code);
    }
}
