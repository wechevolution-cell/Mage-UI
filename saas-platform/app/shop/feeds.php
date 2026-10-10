<?php
declare(strict_types=1);

/** Modular XML feed engine: Skroutz, BestPrice, Google Merchant, generic and custom feeds. Only visible products. */
final class Feeds
{
    public const TYPES = ['skroutz' => 'Skroutz', 'bestprice' => 'BestPrice', 'google' => 'Google Merchant', 'generic' => 'Γενικό XML', 'custom' => 'Custom XML'];
    public const FREQ = ['realtime' => 'Σε κάθε αίτημα', 'hourly' => 'Κάθε ώρα', 'daily' => 'Ημερησίως'];
    public const CUSTOM_FIELDS = ['id', 'sku', 'name', 'url', 'image', 'images', 'price', 'sale_price', 'final_price', 'currency', 'stock', 'availability', 'brand', 'barcode', 'mpn', 'category', 'category_path', 'description', 'short_description', 'weight', 'attributes'];

    public static function ensureDefaults(PDO $db): void
    {
        foreach ([['Skroutz', 'skroutz', 'skroutz'], ['BestPrice', 'bestprice', 'bestprice'], ['Google Merchant', 'google', 'google'], ['Όλα τα προϊόντα', 'products', 'generic']] as [$n, $slug, $type]) {
            if (!db_val($db, 'SELECT 1 FROM feeds WHERE slug = ?', [$slug])) {
                db_insert($db, 'feeds', ['name' => $n, 'slug' => $slug, 'type' => $type, 'filters' => '{}', 'fields' => '[]', 'frequency' => 'hourly',
                    'status' => $type === 'generic' ? 'active' : 'inactive', 'created_at' => now()]);
            }
        }
    }

    public static function allowed(array $shop, array $feed): bool
    {
        if ($feed['type'] === 'generic') return true;
        if ($feed['type'] === 'custom') return Shops::feature($shop, 'feeds_custom');
        return Shops::feature($shop, 'feeds_marketplace');
    }

    /** Returns XML (cached per frequency). */
    public static function output(PDO $db, array $shop, array $feed, bool $force = false): string
    {
        $dir = ensure_dir(DATA_DIR . '/feeds/shop_' . (int) $shop['id']);
        $file = $dir . '/' . $feed['slug'] . '.xml';
        $ttl = ['realtime' => 0, 'hourly' => 3600, 'daily' => 86400][$feed['frequency']] ?? 3600;
        if (!$force && $ttl > 0 && is_file($file) && filemtime($file) > time() - $ttl) return (string) file_get_contents($file);
        $t0 = microtime(true);
        try {
            [$xml, $count] = self::generate($db, $shop, $feed);
            file_put_contents($file, $xml, LOCK_EX);
            db_update($db, 'feeds', ['last_generated_at' => now(), 'last_count' => $count], 'id = ?', [$feed['id']]);
            db_insert($db, 'feed_logs', ['feed_id' => $feed['id'], 'status' => 'ok', 'items' => $count, 'duration_ms' => (int) ((microtime(true) - $t0) * 1000), 'created_at' => now()]);
            db_exec($db, 'DELETE FROM feed_logs WHERE feed_id = ? AND id NOT IN (SELECT id FROM feed_logs WHERE feed_id = ? ORDER BY id DESC LIMIT 50)', [$feed['id'], $feed['id']]);
            return $xml;
        } catch (Throwable $e) {
            db_insert($db, 'feed_logs', ['feed_id' => $feed['id'], 'status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 300), 'created_at' => now()]);
            if (is_file($file)) return (string) file_get_contents($file);
            throw $e;
        }
    }

    private static function products(PDO $db, array $shop, array $filters): Generator
    {
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        $where = $vis;
        $params = $vp;
        if (!empty($filters['category_ids'])) {
            $ids = [];
            foreach ((array) $filters['category_ids'] as $c) $ids = array_merge($ids, Categories::descendantIds($db, (int) $c));
            $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))';
            $params = array_merge($params, $ids);
        }
        if (!empty($filters['in_stock'])) $where .= " AND p.availability <> 'out_of_stock' AND (p.manage_stock = 0 OR p.stock > 0)";
        if (!empty($filters['min_price'])) { $where .= ' AND p.price >= ?'; $params[] = (float) $filters['min_price']; }
        if (!empty($filters['brand'])) { $where .= ' AND p.brand = ?'; $params[] = $filters['brand']; }
        $st = db_exec($db, "SELECT p.* FROM products p WHERE $where ORDER BY p.id", $params);
        while ($p = $st->fetch()) yield $p;
    }

    /** @return array{0:string,1:int} */
    public static function generate(PDO $db, array $shop, array $feed): array
    {
        $filters = jd($feed['filters']);
        $base = rtrim(Shops::url((int) $shop['id'], ''), '/');
        $cats = [];
        foreach (Categories::all($db) as $c) $cats[$c['id']] = $c;
        $tax = ShopSettings::get($db, 'tax');
        $ship = ShopSettings::get($db, 'shipping');
        $storeName = ShopSettings::storeName($db, $shop);
        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');
        $n = 0;
        $type = $feed['type'];
        $fields = array_values(array_intersect((array) jd($feed['fields']), self::CUSTOM_FIELDS)) ?: ['id', 'sku', 'name', 'url', 'image', 'final_price', 'availability', 'brand', 'category_path'];

        if ($type === 'skroutz') { $w->startElement('mywebstore'); $w->writeElement('created_at', date('Y-m-d H:i')); $w->startElement('products'); }
        elseif ($type === 'bestprice') { $w->startElement('store'); $w->writeElement('date', date('Y-m-d H:i:s')); $w->startElement('products'); }
        elseif ($type === 'google') {
            $w->startElement('rss'); $w->writeAttribute('version', '2.0'); $w->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            $w->startElement('channel'); $w->writeElement('title', $storeName); $w->writeElement('link', $base . '/'); $w->writeElement('description', 'Product feed');
        } else { $w->startElement('products'); $w->writeAttribute('generated', date('c')); }

        foreach (self::products($db, $shop, $filters) as $p) {
            $imgs = Products::images($db, (int) $p['id']);
            $img = $imgs ? $base . '/uploads/' . $imgs[0]['path'] : '';
            $url = $base . '/product/' . $p['slug'] . '/';
            $final = Products::effectivePrice($p);
            $catPath = $p['primary_category_id'] ? Exporter::catChain($cats, (int) $p['primary_category_id']) : '';
            $inStock = Products::inStock($p);
            $desc = excerpt($p['description'] ?: $p['short_description'], 5000);
            $attrs = Products::attributes($db, (int) $p['id']);
            $attr = fn(array $keys) => (string) (array_values(array_filter($attrs, fn($a) => in_array(str_fold($a['name']), $keys, true)))[0]['value'] ?? '');
            $n++;
            if ($type === 'skroutz') {
                $w->startElement('product');
                $w->writeElement('id', (string) ($p['sku'] ?: $p['id']));
                self::cdata($w, 'name', $p['name']);
                $w->writeElement('link', $url);
                $w->writeElement('image', $img);
                foreach (array_slice($imgs, 1, 10) as $m) $w->writeElement('additionalimage', $base . '/uploads/' . $m['path']);
                $w->startElement('category'); $w->writeAttribute('id', (string) ($p['primary_category_id'] ?? '')); $w->writeCdata($catPath); $w->endElement();
                $w->writeElement('price_with_vat', number_format($final, 2, '.', ''));
                $w->writeElement('vat', number_format((float) ($p['vat_rate'] ?? $tax['vat_rate']), 2, '.', ''));
                self::cdata($w, 'manufacturer', (string) $p['brand']);
                $w->writeElement('mpn', (string) ($p['mpn'] ?: $p['sku']));
                $w->writeElement('ean', (string) $p['barcode']);
                $w->writeElement('availability', $inStock ? ($p['availability'] === 'backorder' ? 'Παράδοση σε 4 - 10 ημέρες' : 'Άμεσα διαθέσιμο') : 'Μη διαθέσιμο');
                if ($p['manage_stock']) $w->writeElement('quantity', (string) max(0, (int) $p['stock']));
                if ($p['weight']) $w->writeElement('weight', (string) round((float) $p['weight'] * 1000));
                if ($s = $attr(['μεγεθος', 'size'])) $w->writeElement('size', $s);
                if ($c = $attr(['χρωμα', 'color'])) $w->writeElement('color', $c);
                self::cdata($w, 'description', $desc);
                $w->endElement();
            } elseif ($type === 'bestprice') {
                $w->startElement('product');
                $w->writeElement('productId', (string) ($p['sku'] ?: $p['id']));
                self::cdata($w, 'title', $p['name']);
                $w->writeElement('productURL', $url);
                $w->writeElement('imageURL', $img);
                foreach (array_slice($imgs, 1, 10) as $m) $w->writeElement('imagesURL', $base . '/uploads/' . $m['path']);
                $w->writeElement('price', number_format($final, 2, '.', ''));
                if (Products::onSale($p)) $w->writeElement('oldPrice', number_format((float) $p['price'], 2, '.', ''));
                self::cdata($w, 'category_path', $catPath);
                $w->writeElement('category_id', (string) ($p['primary_category_id'] ?? ''));
                self::cdata($w, 'brand', (string) $p['brand']);
                $w->writeElement('availability', $inStock ? 'Άμεσα διαθέσιμο' : 'Εξαντλήθηκε');
                $w->writeElement('stock', $inStock ? 'Y' : 'N');
                $w->writeElement('EAN', (string) $p['barcode']);
                $w->writeElement('MPN', (string) ($p['mpn'] ?: $p['sku']));
                $w->writeElement('shipping', number_format((float) ($ship['zones']['mainland']['cost'] ?? 0), 2, '.', ''));
                self::cdata($w, 'description', $desc);
                $w->endElement();
            } elseif ($type === 'google') {
                $w->startElement('item');
                $w->writeElement('g:id', (string) ($p['sku'] ?: $p['id']));
                $w->writeElement('title', mb_substr($p['name'], 0, 150));
                $w->writeElement('description', mb_substr($desc ?: $p['name'], 0, 5000));
                $w->writeElement('link', $url);
                if ($img) $w->writeElement('g:image_link', $img);
                foreach (array_slice($imgs, 1, 10) as $m) $w->writeElement('g:additional_image_link', $base . '/uploads/' . $m['path']);
                $w->writeElement('g:availability', !$inStock ? 'out_of_stock' : ($p['availability'] === 'preorder' ? 'preorder' : ($p['availability'] === 'backorder' ? 'backorder' : 'in_stock')));
                $w->writeElement('g:price', number_format((float) $p['price'], 2, '.', '') . ' EUR');
                if (Products::onSale($p)) $w->writeElement('g:sale_price', number_format($final, 2, '.', '') . ' EUR');
                if ($p['brand']) $w->writeElement('g:brand', $p['brand']);
                if ($p['barcode']) $w->writeElement('g:gtin', $p['barcode']);
                if ($p['mpn'] || $p['sku']) $w->writeElement('g:mpn', (string) ($p['mpn'] ?: $p['sku']));
                if (!$p['barcode'] && !$p['mpn'] && !$p['brand']) $w->writeElement('g:identifier_exists', 'no');
                $w->writeElement('g:condition', 'new');
                if ($catPath) $w->writeElement('g:product_type', $catPath);
                $w->endElement();
            } else {
                $w->startElement('product');
                $use = $type === 'generic' ? ['id', 'sku', 'name', 'url', 'image', 'images', 'price', 'sale_price', 'final_price', 'currency', 'stock', 'availability', 'brand', 'barcode', 'mpn', 'category_path', 'description'] : $fields;
                $vals = ['id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'url' => $url, 'image' => $img, 'images' => implode('|', array_map(fn($m) => $base . '/uploads/' . $m['path'], $imgs)),
                    'price' => number_format((float) $p['price'], 2, '.', ''), 'sale_price' => Products::onSale($p) ? number_format($final, 2, '.', '') : '', 'final_price' => number_format($final, 2, '.', ''),
                    'currency' => 'EUR', 'stock' => (int) $p['stock'], 'availability' => $inStock ? 'in_stock' : 'out_of_stock', 'brand' => $p['brand'], 'barcode' => $p['barcode'], 'mpn' => $p['mpn'],
                    'category' => $p['primary_category_id'] ? ($cats[$p['primary_category_id']]['name'] ?? '') : '', 'category_path' => $catPath, 'description' => $desc,
                    'short_description' => excerpt($p['short_description'], 1000), 'weight' => $p['weight'], 'attributes' => implode(';', array_map(fn($a) => $a['name'] . ':' . $a['value'], $attrs))];
                foreach ($use as $f) {
                    in_array($f, ['name', 'description', 'short_description', 'category_path', 'brand', 'attributes'], true) ? self::cdata($w, $f, (string) $vals[$f]) : $w->writeElement($f, (string) $vals[$f]);
                }
                $w->endElement();
            }
        }
        $w->endDocument();
        return [$w->outputMemory(), $n];
    }

    private static function cdata(XMLWriter $w, string $el, string $v): void
    {
        $w->startElement($el);
        $w->writeCdata(str_replace(']]>', ']]&gt;', $v));
        $w->endElement();
    }
}
