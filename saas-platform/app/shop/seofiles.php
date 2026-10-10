<?php
declare(strict_types=1);

/** sitemap.xml (+ image sitemap), robots.txt (AI crawler policy), llms.txt and blog RSS — generated per shop. */
final class SeoFiles
{
    public static function sitemap(PDO $db, array $shop): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $base = rtrim(base_url(), '/');
        $w = new XMLWriter();
        $w->openURI('php://output');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('urlset');
        $w->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $w->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $url = function (string $loc, ?string $mod = null, array $images = []) use ($w) {
            $w->startElement('url');
            $w->writeElement('loc', $loc);
            if ($mod) $w->writeElement('lastmod', date('Y-m-d', strtotime($mod)));
            foreach (array_slice($images, 0, 10) as $img) { $w->startElement('image:image'); $w->writeElement('image:loc', $img); $w->endElement(); }
            $w->endElement();
        };
        $url($base . '/', null);
        $url($base . '/products/', null);
        foreach (db_all($db, "SELECT path, updated_at, robots FROM categories WHERE deleted_at IS NULL AND status = 'active' ORDER BY path") as $c) {
            if (!str_contains((string) $c['robots'], 'noindex')) $url($base . '/category/' . $c['path'] . '/', $c['updated_at']);
        }
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        $st = db_exec($db, "SELECT p.id, p.slug, p.updated_at, p.robots FROM products p WHERE $vis ORDER BY p.id", $vp);
        while ($p = $st->fetch()) {
            if (str_contains((string) $p['robots'], 'noindex')) continue;
            $imgs = array_map(fn($m) => $base . '/uploads/' . $m['path'], db_all($db, 'SELECT m.path FROM product_images pi JOIN media m ON m.id = pi.media_id WHERE pi.product_id = ? ORDER BY pi.sort LIMIT 10', [$p['id']]));
            $url($base . '/product/' . $p['slug'] . '/', $p['updated_at'], $imgs);
        }
        foreach (db_all($db, "SELECT slug, updated_at, robots FROM pages WHERE status = 'published' AND deleted_at IS NULL") as $p) {
            if (!str_contains((string) $p['robots'], 'noindex')) $url($base . '/' . $p['slug'] . '/', $p['updated_at']);
        }
        if (Shops::feature($shop, 'blog')) {
            $posts = db_all($db, 'SELECT slug, updated_at FROM posts WHERE ' . Posts::publishedSql());
            if ($posts) $url($base . '/blog/', null);
            foreach ($posts as $p) $url($base . '/blog/' . $p['slug'] . '/', $p['updated_at']);
        }
        $w->endElement();
        $w->endDocument();
        $w->flush();
    }

    public static function robots(PDO $db, array $shop): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $seo = ShopSettings::get($db, 'seo');
        $base = rtrim(base_url(), '/');
        $sa = App::isPlatformHost(host_without_port(req_host())) ? ['Disallow: /superadmin/'] : [];
        $out = ["User-agent: *", "Disallow: /admin/", ...$sa, "Disallow: /cart/", "Disallow: /checkout/", "Disallow: /account/", "Disallow: /order/", "Disallow: /api/", "Disallow: /*?*sort=", "Allow: /", ""];
        $bots = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended'];
        foreach ($bots as $b) {
            $out[] = "User-agent: $b";
            $out[] = !empty($seo['allow_ai_bots']) ? 'Allow: /' : 'Disallow: /';
            $out[] = 'Disallow: /admin/';
            foreach ($sa as $line) $out[] = $line;
            $out[] = '';
        }
        if (trim((string) $seo['robots_extra']) !== '') { $out[] = '# Custom'; $out[] = trim((string) $seo['robots_extra']); $out[] = ''; }
        $out[] = "Sitemap: $base/sitemap.xml";
        echo implode("\n", $out) . "\n";
    }

    public static function llms(PDO $db, array $shop): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $seo = ShopSettings::get($db, 'seo');
        $store = ShopSettings::get($db, 'store');
        $name = ShopSettings::storeName($db, $shop);
        $base = rtrim(base_url(), '/');
        $o = ["# $name", '', '> ' . ($seo['llms_intro'] ?: ($seo['default_description'] ?: "Ηλεκτρονικό κατάστημα $name.")), ''];
        $info = array_filter(['Διεύθυνση' => trim(($store['address'] ?? '') . ' ' . ($store['city'] ?? '')), 'Τηλέφωνο' => $store['phone'] ?? '', 'Email' => $store['email'] ?? '', 'Ωράριο' => $store['hours'] ?? '']);
        if ($info) { $o[] = '## Στοιχεία'; foreach ($info as $k => $v) $o[] = "- $k: $v"; $o[] = ''; }
        $o[] = '## Κατηγορίες';
        foreach (db_all($db, "SELECT name, path, description FROM categories WHERE deleted_at IS NULL AND status = 'active' ORDER BY path LIMIT 100") as $c) $o[] = "- [{$c['name']}]($base/category/{$c['path']}/)" . ($c['description'] ? ': ' . excerpt($c['description'], 140) : '');
        $o[] = '';
        $o[] = '## Πληροφορίες';
        foreach (db_all($db, "SELECT title, slug, excerpt FROM pages WHERE status = 'published' AND deleted_at IS NULL ORDER BY sort") as $p) $o[] = "- [{$p['title']}]($base/{$p['slug']}/)";
        $o[] = '';
        $o[] = '## Προϊόντα (επιλογή)';
        foreach (Catalog::query($db, $shop, ['per' => 30, 'featured' => 0])['items'] as $p) $o[] = "- [{$p['name']}]($base/product/{$p['slug']}/): " . money(Products::effectivePrice($p)) . ($p['short_description'] ? ' — ' . excerpt($p['short_description'], 120) : '');
        $o[] = '';
        $o[] = "## Περισσότερα\n- Sitemap: $base/sitemap.xml";
        echo implode("\n", $o) . "\n";
    }

    public static function rss(PDO $db, array $shop): void
    {
        if (!Shops::feature($shop, 'blog')) { http_response_code(404); return; }
        header('Content-Type: application/rss+xml; charset=utf-8');
        $base = rtrim(base_url(), '/');
        $w = new XMLWriter();
        $w->openURI('php://output');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('rss');
        $w->writeAttribute('version', '2.0');
        $w->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $w->startElement('channel');
        $w->writeElement('title', ShopSettings::storeName($db, $shop) . ' — Blog');
        $w->writeElement('link', $base . '/blog/');
        $w->writeElement('description', 'Άρθρα και νέα');
        $w->writeElement('language', 'el');
        $w->startElement('atom:link'); $w->writeAttribute('href', $base . '/feed/blog.xml'); $w->writeAttribute('rel', 'self'); $w->writeAttribute('type', 'application/rss+xml'); $w->endElement();
        foreach (db_all($db, 'SELECT * FROM posts WHERE ' . Posts::publishedSql() . ' ORDER BY published_at DESC LIMIT 30') as $p) {
            $w->startElement('item');
            $w->writeElement('title', $p['title']);
            $w->writeElement('link', $base . '/blog/' . $p['slug'] . '/');
            $w->writeElement('guid', $base . '/blog/' . $p['slug'] . '/');
            $w->writeElement('pubDate', date('r', strtotime($p['published_at'])));
            $w->writeElement('description', $p['excerpt'] ?: excerpt($p['content'], 300));
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->endDocument();
        $w->flush();
    }
}
