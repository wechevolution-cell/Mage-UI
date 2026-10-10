<?php
declare(strict_types=1);

final class Pages
{
    public static function bySlug(PDO $db, string $slug): ?array
    {
        return db_one($db, "SELECT * FROM pages WHERE slug = ? AND status = 'published' AND deleted_at IS NULL", [$slug]);
    }

    public static function save(PDO $db, array $in, ?int $id = null): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'Ο τίτλος είναι υποχρεωτικός.'];
        $slug = slugify(($in['slug'] ?? '') !== '' ? $in['slug'] : $title, 80);
        $reserved = ['admin', 'product', 'products', 'category', 'cart', 'checkout', 'account', 'search', 'blog', 'feed', 'api', 'wishlist', 'order', 'sitemap-xml', 'robots-txt', 'uploads', 'assets', 'templates', 'newsletter', 'unsubscribe', 'superadmin', 'cron'];
        if (in_array($slug, $reserved, true)) return ['ok' => false, 'error' => 'Το slug «' . $slug . '» είναι δεσμευμένο.'];
        if (db_val($db, 'SELECT 1 FROM pages WHERE slug = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), $id ? [$slug, $id] : [$slug])) return ['ok' => false, 'error' => 'Υπάρχει ήδη σελίδα με αυτό το slug.'];
        $old = $id ? db_one($db, 'SELECT * FROM pages WHERE id = ?', [$id]) : null;
        $row = ['title' => mb_substr($title, 0, 200), 'slug' => $slug, 'content' => Sanitizer::html($in['content'] ?? ''), 'excerpt' => Sanitizer::text($in['excerpt'] ?? '', 500) ?: null,
            'kind' => in_array($in['kind'] ?? 'page', ['page', 'contact', 'faq', 'legal'], true) ? $in['kind'] : 'page',
            'status' => ($in['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'seo_title' => Sanitizer::text($in['seo_title'] ?? '', 200) ?: null, 'seo_description' => Sanitizer::text($in['seo_description'] ?? '', 320) ?: null,
            'canonical_url' => Sanitizer::url($in['canonical_url'] ?? '') ?: null, 'robots' => in_array($in['robots'] ?? '', ['noindex', 'noindex,nofollow'], true) ? $in['robots'] : null,
            'faq' => je(array_values(array_filter(array_map(fn($f) => ['q' => Sanitizer::text($f['q'] ?? '', 300), 'a' => Sanitizer::html($f['a'] ?? '')], (array) ($in['faq'] ?? [])), fn($f) => $f['q'] !== '' && $f['a'] !== ''))),
            'show_in_footer' => !empty($in['show_in_footer']) ? 1 : 0, 'sort' => (int) ($in['sort'] ?? 0), 'updated_at' => now()];
        if ($id) {
            db_update($db, 'pages', $row, 'id = ?', [$id]);
            if ($old && $old['slug'] !== $slug) Redirects::autoCreate($db, '/' . $old['slug'] . '/', '/' . $slug . '/');
            return ['ok' => true, 'id' => $id];
        }
        $row['created_at'] = now();
        return ['ok' => true, 'id' => db_insert($db, 'pages', $row)];
    }
}

final class Posts
{
    public static function save(PDO $db, array $in, ?int $id = null): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'Ο τίτλος είναι υποχρεωτικός.'];
        $slug = slugify(($in['slug'] ?? '') !== '' ? $in['slug'] : $title, 90);
        if (db_val($db, 'SELECT 1 FROM posts WHERE slug = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), $id ? [$slug, $id] : [$slug])) return ['ok' => false, 'error' => 'Υπάρχει ήδη άρθρο με αυτό το slug.'];
        $old = $id ? db_one($db, 'SELECT * FROM posts WHERE id = ?', [$id]) : null;
        $status = in_array($in['status'] ?? 'draft', ['draft', 'published', 'scheduled'], true) ? $in['status'] : 'draft';
        $pub = trim((string) ($in['published_at'] ?? '')) ?: ($status !== 'draft' ? now() : null);
        if ($pub) $pub = date('Y-m-d H:i:s', strtotime($pub) ?: time());
        $row = ['title' => mb_substr($title, 0, 250), 'slug' => $slug, 'excerpt' => Sanitizer::text($in['excerpt'] ?? '', 600) ?: null, 'tldr' => Sanitizer::text($in['tldr'] ?? '', 800) ?: null,
            'content' => Sanitizer::html($in['content'] ?? ''), 'image_id' => !empty($in['image_id']) ? (int) $in['image_id'] : null, 'author' => Sanitizer::text($in['author'] ?? '', 120) ?: null,
            'category' => Sanitizer::text($in['category'] ?? '', 120) ?: null, 'tags' => Sanitizer::text($in['tags'] ?? '', 300) ?: null, 'sources' => Sanitizer::text($in['sources'] ?? '', 3000) ?: null,
            'faq' => je(array_values(array_filter(array_map(fn($f) => ['q' => Sanitizer::text($f['q'] ?? '', 300), 'a' => Sanitizer::html($f['a'] ?? '')], (array) ($in['faq'] ?? [])), fn($f) => $f['q'] !== '' && $f['a'] !== ''))),
            'status' => $status, 'published_at' => $pub, 'seo_title' => Sanitizer::text($in['seo_title'] ?? '', 200) ?: null,
            'seo_description' => Sanitizer::text($in['seo_description'] ?? '', 320) ?: null, 'focus_keyword' => Sanitizer::text($in['focus_keyword'] ?? '', 120) ?: null, 'updated_at' => now()];
        if ($id) {
            db_update($db, 'posts', $row, 'id = ?', [$id]);
            if ($old && $old['slug'] !== $slug) Redirects::autoCreate($db, '/blog/' . $old['slug'] . '/', '/blog/' . $slug . '/');
            return ['ok' => true, 'id' => $id];
        }
        $row['created_at'] = now();
        return ['ok' => true, 'id' => db_insert($db, 'posts', $row)];
    }

    public static function publishedSql(): string
    {
        return "deleted_at IS NULL AND status IN ('published','scheduled') AND published_at IS NOT NULL AND published_at <= '" . now() . "'";
    }

    /** Builds a table of contents from h2/h3 and injects ids. */
    public static function toc(string $html): array
    {
        $toc = [];
        $html = preg_replace_callback('/<(h[23])([^>]*)>(.*?)<\/\1>/isu', function ($m) use (&$toc) {
            $text = trim(strip_tags($m[3]));
            $id = preg_match('/id="([^"]+)"/', $m[2], $mm) ? $mm[1] : slugify($text, 60);
            $toc[] = ['level' => (int) substr($m[1], 1), 'text' => $text, 'id' => $id];
            $attrs = preg_replace('/\s*id="[^"]*"/', '', $m[2]);
            return '<' . $m[1] . $attrs . ' id="' . e($id) . '">' . $m[3] . '</' . $m[1] . '>';
        }, $html);
        return [$html, $toc];
    }
}

final class Menus
{
    public const LOCATIONS = ['header' => 'Κύριο μενού', 'footer' => 'Μενού υποσέλιδου', 'footer2' => 'Υποσέλιδο (εξυπηρέτηση)'];

    public static function ensure(PDO $db): void
    {
        foreach (self::LOCATIONS as $loc => $name) {
            if (!db_val($db, 'SELECT 1 FROM menus WHERE location = ?', [$loc])) db_insert($db, 'menus', ['location' => $loc, 'name' => $name]);
        }
    }

    /** Resolved nested items for the storefront (links computed from referenced entities). */
    public static function get(PDO $db, string $location): array
    {
        $menuId = db_val($db, 'SELECT id FROM menus WHERE location = ?', [$location]);
        if (!$menuId) return [];
        $rows = db_all($db, 'SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort, id', [$menuId]);
        $by = [];
        foreach ($rows as $r) {
            $r['href'] = self::href($db, $r);
            if ($r['href'] === null) continue;
            $r['children'] = [];
            $by[$r['id']] = $r;
        }
        $root = [];
        foreach ($by as $id => &$r) {
            if ($r['parent_id'] && isset($by[$r['parent_id']])) $by[$r['parent_id']]['children'][] = &$r;
            else $root[] = &$r;
        }
        unset($r);
        return $root;
    }

    public static function href(PDO $db, array $item): ?string
    {
        switch ($item['type']) {
            case 'home': return '/';
            case 'products': return '/products/';
            case 'blog': return '/blog/';
            case 'category':
                $p = db_val($db, "SELECT path FROM categories WHERE id = ? AND deleted_at IS NULL AND status = 'active'", [$item['ref_id']]);
                return $p ? '/category/' . $p . '/' : null;
            case 'product':
                $s = db_val($db, 'SELECT slug FROM products WHERE id = ? AND deleted_at IS NULL AND is_active = 1', [$item['ref_id']]);
                return $s ? '/product/' . $s . '/' : null;
            case 'page':
                $s = db_val($db, "SELECT slug FROM pages WHERE id = ? AND deleted_at IS NULL AND status = 'published'", [$item['ref_id']]);
                return $s ? '/' . $s . '/' : null;
            case 'post':
                $s = db_val($db, 'SELECT slug FROM posts WHERE id = ? AND deleted_at IS NULL', [$item['ref_id']]);
                return $s ? '/blog/' . $s . '/' : null;
            default:
                return Sanitizer::url($item['url'] ?? '') ?: null;
        }
    }

    /** Replace a menu from a structured list [{label,type,ref_id,url,new_tab,children:[...]}]. */
    public static function saveTree(PDO $db, string $location, array $items): void
    {
        self::ensure($db);
        $menuId = (int) db_val($db, 'SELECT id FROM menus WHERE location = ?', [$location]);
        db_tx($db, function () use ($db, $menuId, $items) {
            db_exec($db, 'DELETE FROM menu_items WHERE menu_id = ?', [$menuId]);
            $ins = function (array $list, ?int $parent) use (&$ins, $db, $menuId) {
                foreach (array_values($list) as $i => $it) {
                    $label = trim((string) ($it['label'] ?? ''));
                    if ($label === '') continue;
                    $type = in_array($it['type'] ?? 'url', ['home', 'products', 'blog', 'category', 'product', 'page', 'post', 'url'], true) ? $it['type'] : 'url';
                    $id = db_insert($db, 'menu_items', ['menu_id' => $menuId, 'parent_id' => $parent, 'label' => mb_substr($label, 0, 80), 'type' => $type,
                        'ref_id' => !empty($it['ref_id']) ? (int) $it['ref_id'] : null, 'url' => $type === 'url' ? Sanitizer::url($it['url'] ?? '') : null, 'sort' => $i, 'new_tab' => !empty($it['new_tab']) ? 1 : 0]);
                    if (!empty($it['children']) && is_array($it['children'])) $ins($it['children'], $id);
                }
            };
            $ins($items, null);
        });
    }

    public static function rawTree(PDO $db, string $location): array
    {
        $menuId = db_val($db, 'SELECT id FROM menus WHERE location = ?', [$location]);
        if (!$menuId) return [];
        $rows = db_all($db, 'SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort, id', [$menuId]);
        $by = [];
        foreach ($rows as $r) { $r['children'] = []; $by[$r['id']] = $r; }
        $root = [];
        foreach ($by as &$r) {
            if ($r['parent_id'] && isset($by[$r['parent_id']])) $by[$r['parent_id']]['children'][] = &$r;
            else $root[] = &$r;
        }
        unset($r);
        return $root;
    }
}

final class Popups
{
    /** Active popup for the current page (home/all), within schedule. */
    public static function active(PDO $db, bool $isHome): ?array
    {
        $n = now();
        return db_one($db, "SELECT * FROM popups WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?)
            AND (pages = 'all' OR (pages = 'home' AND ?)) ORDER BY updated_at DESC LIMIT 1", [$n, $n, $isHome ? 1 : 0]);
    }

    public static function subscribe(PDO $db, string $email, string $source, ?int $popupId, bool $consent, string $consentText): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Συμπληρώστε ένα έγκυρο email.'];
        $existing = db_one($db, 'SELECT * FROM subscribers WHERE email = ?', [$email]);
        if ($existing) {
            db_update($db, 'subscribers', ['unsubscribed_at' => null, 'consent' => $consent ? 1 : $existing['consent'], 'consent_text' => $consent ? $consentText : $existing['consent_text'],
                'consent_at' => $consent ? now() : $existing['consent_at']], 'id = ?', [$existing['id']]);
            return ['ok' => true, 'existing' => true];
        }
        db_insert($db, 'subscribers', ['email' => $email, 'source' => mb_substr($source, 0, 60), 'popup_id' => $popupId, 'consent' => $consent ? 1 : 0, 'consent_text' => $consent ? mb_substr($consentText, 0, 500) : null,
            'consent_at' => $consent ? now() : null, 'ip' => client_ip(), 'unsubscribe_token' => random_token(18), 'created_at' => now()]);
        return ['ok' => true];
    }
}
