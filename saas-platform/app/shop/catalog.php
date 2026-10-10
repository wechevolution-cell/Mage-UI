<?php
declare(strict_types=1);

/** Unlimited, arbitrary-depth category tree (independent of product quotas). */
final class Categories
{
    public static function all(PDO $db, bool $activeOnly = false): array
    {
        return db_all($db, 'SELECT * FROM categories WHERE deleted_at IS NULL' . ($activeOnly ? " AND status = 'active'" : '') . ' ORDER BY sort, name');
    }

    public static function find(PDO $db, int $id): ?array { return db_one($db, 'SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL', [$id]); }

    public static function byPath(PDO $db, string $path): ?array
    {
        return db_one($db, "SELECT * FROM categories WHERE path = ? AND deleted_at IS NULL AND status = 'active'", [trim($path, '/')]);
    }

    /** Nested tree: each node gets 'children'. */
    public static function tree(PDO $db, bool $activeOnly = false): array
    {
        $rows = self::all($db, $activeOnly);
        $by = [];
        foreach ($rows as $r) { $r['children'] = []; $by[$r['id']] = $r; }
        $root = [];
        foreach ($by as $id => &$r) {
            if ($r['parent_id'] && isset($by[$r['parent_id']])) $by[$r['parent_id']]['children'][] = &$r;
            else $root[] = &$r;
        }
        unset($r);
        return $root;
    }

    /** Flat list with depth for selects. */
    public static function flat(PDO $db, bool $activeOnly = false): array
    {
        $out = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$out) {
            foreach ($nodes as $n) {
                $c = $n['children'];
                unset($n['children']);
                $n['depth'] = $depth;
                $out[] = $n;
                $walk($c, $depth + 1);
            }
        };
        $walk(self::tree($db, $activeOnly), 0);
        return $out;
    }

    public static function descendantIds(PDO $db, int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $kids = db_col($db, 'SELECT id FROM categories WHERE deleted_at IS NULL AND parent_id IN (' . in_placeholders($frontier) . ')', $frontier);
            $kids = array_diff(array_map('intval', $kids), $ids);
            $ids = array_merge($ids, $kids);
            $frontier = $kids;
        }
        return $ids;
    }

    public static function ancestors(PDO $db, array $cat): array
    {
        $out = [];
        $guard = 0;
        while ($cat && $cat['parent_id'] && $guard++ < 50) {
            $cat = self::find($db, (int) $cat['parent_id']);
            if ($cat) array_unshift($out, $cat);
        }
        return $out;
    }

    public static function url(array $cat): string { return '/category/' . $cat['path'] . '/'; }

    public static function save(PDO $db, array $in, ?int $id = null): array
    {
        $name = trim($in['name'] ?? '');
        if ($name === '') return ['ok' => false, 'error' => 'Το όνομα είναι υποχρεωτικό.'];
        $parent = !empty($in['parent_id']) ? (int) $in['parent_id'] : null;
        if ($id && $parent && in_array($parent, self::descendantIds($db, $id), true)) return ['ok' => false, 'error' => 'Μια κατηγορία δεν μπορεί να είναι υποκατηγορία του εαυτού της.'];
        $slug = slugify(($in['slug'] ?? '') !== '' ? $in['slug'] : $name, 80);
        $parentRow = $parent ? self::find($db, $parent) : null;
        $path = ($parentRow ? $parentRow['path'] . '/' : '') . $slug;
        $dupe = db_val($db, 'SELECT id FROM categories WHERE path = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), $id ? [$path, $id] : [$path]);
        if ($dupe) return ['ok' => false, 'error' => 'Υπάρχει ήδη κατηγορία με αυτό το slug στο ίδιο επίπεδο.'];
        $row = [
            'parent_id' => $parent, 'name' => mb_substr($name, 0, 150), 'slug' => $slug, 'path' => $path,
            'description' => Sanitizer::text($in['description'] ?? '', 1000), 'content' => Sanitizer::html($in['content'] ?? ''),
            'image_id' => !empty($in['image_id']) ? (int) $in['image_id'] : null, 'sort' => (int) ($in['sort'] ?? 0),
            'status' => ($in['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'seo_title' => Sanitizer::text($in['seo_title'] ?? '', 200) ?: null, 'seo_description' => Sanitizer::text($in['seo_description'] ?? '', 320) ?: null,
            'canonical_url' => Sanitizer::url($in['canonical_url'] ?? '') ?: null, 'robots' => in_array($in['robots'] ?? '', ['noindex', 'noindex,nofollow'], true) ? $in['robots'] : null,
            'faq' => isset($in['faq']) ? je(array_values(array_filter((array) $in['faq'], fn($f) => trim($f['q'] ?? '') !== ''))) : null,
            'updated_at' => now(),
        ];
        return db_tx($db, function () use ($db, $row, $id) {
            if ($id) {
                $old = self::find($db, $id);
                db_update($db, 'categories', $row, 'id = ?', [$id]);
                if ($old && $old['path'] !== $row['path']) {
                    self::rebuildPaths($db);
                    Redirects::autoCreate($db, '/category/' . $old['path'] . '/', '/category/' . $row['path'] . '/');
                }
                return ['ok' => true, 'id' => $id];
            }
            $row['created_at'] = now();
            return ['ok' => true, 'id' => db_insert($db, 'categories', $row)];
        });
    }

    public static function rebuildPaths(PDO $db): void
    {
        $walk = function (?int $parent, string $prefix) use (&$walk, $db) {
            $rows = $parent === null
                ? db_all($db, 'SELECT id, slug FROM categories WHERE parent_id IS NULL AND deleted_at IS NULL')
                : db_all($db, 'SELECT id, slug FROM categories WHERE parent_id = ? AND deleted_at IS NULL', [$parent]);
            foreach ($rows as $r) {
                $path = $prefix . $r['slug'];
                db_exec($db, 'UPDATE categories SET path = ? WHERE id = ?', [$path, $r['id']]);
                $walk((int) $r['id'], $path . '/');
            }
        };
        $walk(null, '');
    }

    /** Delete moves children up one level; products keep their other categories. */
    public static function delete(PDO $db, int $id): void
    {
        $c = self::find($db, $id);
        if (!$c) return;
        db_tx($db, function () use ($db, $c, $id) {
            db_exec($db, 'UPDATE categories SET parent_id = ? WHERE parent_id = ?', [$c['parent_id'], $id]);
            db_exec($db, 'DELETE FROM product_categories WHERE category_id = ?', [$id]);
            db_exec($db, 'UPDATE products SET primary_category_id = NULL WHERE primary_category_id = ?', [$id]);
            db_exec($db, 'UPDATE categories SET deleted_at = ?, path = path || ? WHERE id = ?', [now(), '#deleted-' . $id, $id]);
            self::rebuildPaths($db);
        });
    }

    /** Find or create a category chain from "Parent > Child > Grandchild" (imports). */
    public static function ensurePath(PDO $db, string $chain): ?int
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*(>|\/|»|\|)\s*/u', $chain))));
        $parent = null;
        foreach ($parts as $name) {
            $id = $parent === null
                ? db_val($db, 'SELECT id FROM categories WHERE parent_id IS NULL AND (name = ? OR slug = ?) AND deleted_at IS NULL', [$name, slugify($name)])
                : db_val($db, 'SELECT id FROM categories WHERE parent_id = ? AND (name = ? OR slug = ?) AND deleted_at IS NULL', [$parent, $name, slugify($name)]);
            if (!$id) {
                $r = self::save($db, ['name' => $name, 'parent_id' => $parent]);
                if (!$r['ok']) return $parent;
                $id = $r['id'];
            }
            $parent = (int) $id;
        }
        return $parent;
    }
}

final class Products
{
    public static function find(PDO $db, int $id): ?array { return db_one($db, 'SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]); }

    public static function effectivePrice(array $p): float
    {
        $s = $p['sale_price'] ?? null;
        return ($s !== null && $s !== '' && (float) $s > 0 && (float) $s < (float) $p['price']) ? (float) $s : (float) $p['price'];
    }

    public static function onSale(array $p): bool { return self::effectivePrice($p) < (float) $p['price']; }

    public static function url(array $p): string { return '/product/' . $p['slug'] . '/'; }

    public static function inStock(array $p): bool
    {
        if (in_array($p['availability'], ['out_of_stock'], true)) return false;
        if ((int) $p['manage_stock'] && (int) $p['stock'] <= 0 && !in_array($p['availability'], ['preorder', 'backorder'], true)) return false;
        return true;
    }

    public static function uniqueSlug(PDO $db, string $slug, ?int $exceptId = null): string
    {
        $base = $slug;
        $i = 2;
        while (db_val($db, 'SELECT 1 FROM products WHERE slug = ? AND deleted_at IS NULL' . ($exceptId ? ' AND id <> ?' : ''), $exceptId ? [$slug, $exceptId] : [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Create/update a product. Activation is validated by Quota (never bypassed).
     * @return array{ok:bool,id?:int,error?:string,warning?:string}
     */
    public static function save(PDO $db, array $shop, array $in, ?int $id = null): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'error' => 'Το όνομα προϊόντος είναι υποχρεωτικό.'];
        $price = $in['price'] ?? null;
        if ($price === null || !is_numeric($price) || $price < 0) return ['ok' => false, 'error' => 'Συμπληρώστε έγκυρη τιμή.'];
        $sale = isset($in['sale_price']) && $in['sale_price'] !== '' && $in['sale_price'] !== null ? (float) $in['sale_price'] : null;
        if ($sale !== null && ($sale <= 0 || $sale >= (float) $price)) $sale = null;
        $sku = trim((string) ($in['sku'] ?? '')) ?: null;
        if ($sku && db_val($db, 'SELECT 1 FROM products WHERE sku = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), $id ? [$sku, $id] : [$sku])) {
            return ['ok' => false, 'error' => 'Ο κωδικός SKU χρησιμοποιείται ήδη από άλλο προϊόν.'];
        }
        if (!$id && Quota::storedLimitReached($db, $shop)) {
            return ['ok' => false, 'error' => 'Έχετε φτάσει το όριο αποθηκευμένων προϊόντων (' . Shops::storedLimit($shop) . ').'];
        }
        $old = $id ? self::find($db, $id) : null;
        $slug = self::uniqueSlug($db, slugify(($in['slug'] ?? '') !== '' ? $in['slug'] : $name, 90), $id);
        $avail = in_array($in['availability'] ?? '', ['in_stock', 'out_of_stock', 'preorder', 'backorder'], true) ? $in['availability'] : 'in_stock';
        $row = [
            'name' => mb_substr($name, 0, 250), 'slug' => $slug, 'sku' => $sku, 'barcode' => trim((string) ($in['barcode'] ?? '')) ?: null, 'mpn' => trim((string) ($in['mpn'] ?? '')) ?: null,
            'short_description' => Sanitizer::html($in['short_description'] ?? ''), 'description' => Sanitizer::html($in['description'] ?? ''),
            'price' => round((float) $price, 2), 'sale_price' => $sale !== null ? round($sale, 2) : null,
            'cost_price' => isset($in['cost_price']) && $in['cost_price'] !== '' && $in['cost_price'] !== null ? round((float) $in['cost_price'], 2) : null,
            'stock' => (int) ($in['stock'] ?? 0), 'manage_stock' => !empty($in['manage_stock']) ? 1 : 0, 'availability' => $avail,
            'weight' => self::num($in['weight'] ?? null), 'length' => self::num($in['length'] ?? null), 'width' => self::num($in['width'] ?? null), 'height' => self::num($in['height'] ?? null),
            'brand' => trim((string) ($in['brand'] ?? '')) ?: null,
            'primary_category_id' => !empty($in['primary_category_id']) ? (int) $in['primary_category_id'] : null,
            'featured' => !empty($in['featured']) ? 1 : 0, 'tags' => trim((string) ($in['tags'] ?? '')) ?: null,
            'seo_title' => Sanitizer::text($in['seo_title'] ?? '', 200) ?: null, 'seo_description' => Sanitizer::text($in['seo_description'] ?? '', 320) ?: null,
            'focus_keyword' => Sanitizer::text($in['focus_keyword'] ?? '', 120) ?: null, 'canonical_url' => Sanitizer::url($in['canonical_url'] ?? '') ?: null,
            'robots' => in_array($in['robots'] ?? '', ['noindex', 'noindex,nofollow'], true) ? $in['robots'] : null,
            'schema_json' => self::validJson($in['schema_json'] ?? ''),
            'faq' => isset($in['faq']) ? je(array_values(array_filter(array_map(fn($f) => ['q' => Sanitizer::text($f['q'] ?? '', 300), 'a' => Sanitizer::html($f['a'] ?? '')], (array) $in['faq']), fn($f) => $f['q'] !== '' && $f['a'] !== ''))) : ($old['faq'] ?? null),
            'updated_at' => now(),
        ];
        $wantActive = !empty($in['is_active']);
        $warning = null;
        $id = db_tx($db, function () use ($db, $row, $id, $in) {
            if ($id) { db_update($db, 'products', $row, 'id = ?', [$id]); }
            else { $row['created_at'] = now(); $row['is_active'] = 0; $id = db_insert($db, 'products', $row); }
            if (array_key_exists('category_ids', $in)) {
                db_exec($db, 'DELETE FROM product_categories WHERE product_id = ?', [$id]);
                $cats = array_unique(array_filter(array_map('intval', (array) $in['category_ids'])));
                if ($row['primary_category_id']) $cats[] = $row['primary_category_id'];
                foreach (array_unique($cats) as $c) db_exec($db, 'INSERT OR IGNORE INTO product_categories(product_id, category_id) VALUES(?,?)', [$id, $c]);
            }
            if (array_key_exists('gallery', $in)) {
                db_exec($db, 'DELETE FROM product_images WHERE product_id = ?', [$id]);
                $ids = array_values(array_unique(array_filter(array_map('intval', (array) $in['gallery']))));
                foreach ($ids as $i => $mid) db_insert($db, 'product_images', ['product_id' => $id, 'media_id' => $mid, 'sort' => $i]);
                db_exec($db, 'UPDATE products SET image_id = ? WHERE id = ?', [$ids[0] ?? null, $id]);
            }
            if (array_key_exists('attributes', $in)) {
                db_exec($db, 'DELETE FROM product_attributes WHERE product_id = ?', [$id]);
                foreach (array_values((array) $in['attributes']) as $i => $a) {
                    $n = trim((string) ($a['name'] ?? ''));
                    $v = trim((string) ($a['value'] ?? ''));
                    if ($n !== '' && $v !== '') db_insert($db, 'product_attributes', ['product_id' => $id, 'name' => mb_substr($n, 0, 100), 'value' => mb_substr($v, 0, 250), 'filterable' => !empty($a['filterable']) || !isset($a['filterable']) ? 1 : 0, 'sort' => $i]);
                }
            }
            if (array_key_exists('variants', $in)) {
                $keep = [];
                foreach (array_values((array) $in['variants']) as $i => $v) {
                    $vname = trim((string) ($v['name'] ?? ''));
                    if ($vname === '') continue;
                    $opts = [];
                    foreach (preg_split('/\s*\/\s*/u', $vname) as $k => $part) {
                        $label = trim((string) (($v['option_names'] ?? [])[$k] ?? ($in['variant_option_names'][$k] ?? 'Επιλογή ' . ($k + 1))));
                        $opts[$label] = $part;
                    }
                    $data = ['product_id' => $id, 'name' => mb_substr($vname, 0, 150), 'sku' => trim((string) ($v['sku'] ?? '')) ?: null, 'options' => je($opts),
                        'price' => ($v['price'] ?? '') !== '' ? round((float) str_replace(',', '.', (string) $v['price']), 2) : null,
                        'sale_price' => ($v['sale_price'] ?? '') !== '' ? round((float) str_replace(',', '.', (string) $v['sale_price']), 2) : null,
                        'stock' => (int) ($v['stock'] ?? 0), 'barcode' => trim((string) ($v['barcode'] ?? '')) ?: null, 'sort' => $i, 'status' => 'active'];
                    if (!empty($v['id']) && db_val($db, 'SELECT 1 FROM product_variants WHERE id = ? AND product_id = ?', [(int) $v['id'], $id])) {
                        db_update($db, 'product_variants', $data, 'id = ?', [(int) $v['id']]);
                        $keep[] = (int) $v['id'];
                    } else {
                        $keep[] = db_insert($db, 'product_variants', $data);
                    }
                }
                if ($keep) db_exec($db, 'DELETE FROM product_variants WHERE product_id = ? AND id NOT IN (' . in_placeholders($keep) . ')', array_merge([$id], $keep));
                else db_exec($db, 'DELETE FROM product_variants WHERE product_id = ?', [$id]);
            }
            return $id;
        });
        // Slug change → automatic 301
        if ($old && $old['slug'] !== $slug) Redirects::autoCreate($db, '/product/' . $old['slug'] . '/', '/product/' . $slug . '/');
        // Activation state through the quota engine
        $isActive = (int) db_val($db, 'SELECT is_active FROM products WHERE id = ?', [$id]);
        if ($wantActive && !$isActive) {
            $r = Quota::setActive($db, $shop, [$id], true);
            if (!$r['ok']) $warning = 'Το προϊόν αποθηκεύτηκε ως ανενεργό. ' . $r['error'];
        } elseif (!$wantActive && $isActive && array_key_exists('is_active', $in)) {
            Quota::setActive($db, $shop, [$id], false);
        } elseif (!$wantActive && $isActive && !array_key_exists('is_active', $in) && isset($in['_form'])) {
            Quota::setActive($db, $shop, [$id], false);
        }
        return ['ok' => true, 'id' => $id, 'warning' => $warning];
    }

    private static function num(mixed $v): ?float { return ($v === null || $v === '') ? null : round((float) str_replace(',', '.', (string) $v), 3); }

    private static function validJson(?string $s): ?string
    {
        $s = trim((string) $s);
        if ($s === '') return null;
        $d = json_decode($s, true);
        return is_array($d) ? je($d) : null;
    }

    public static function softDelete(PDO $db, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        return db_exec($db, 'UPDATE products SET deleted_at = ?, is_active = 0, sku = NULL, slug = slug || \'-deleted-\' || id WHERE deleted_at IS NULL AND id IN (' . in_placeholders($ids) . ')', array_merge([now()], $ids))->rowCount();
    }

    public static function images(PDO $db, int $productId): array
    {
        return db_all($db, 'SELECT m.*, pi.sort FROM product_images pi JOIN media m ON m.id = pi.media_id WHERE pi.product_id = ? ORDER BY pi.sort', [$productId]);
    }

    public static function variants(PDO $db, int $productId, bool $activeOnly = false): array
    {
        return db_all($db, 'SELECT * FROM product_variants WHERE product_id = ?' . ($activeOnly ? " AND status = 'active'" : '') . ' ORDER BY sort, id', [$productId]);
    }

    public static function attributes(PDO $db, int $productId): array
    {
        return db_all($db, 'SELECT * FROM product_attributes WHERE product_id = ? ORDER BY sort, id', [$productId]);
    }

    public static function categoryIds(PDO $db, int $productId): array
    {
        return array_map('intval', db_col($db, 'SELECT category_id FROM product_categories WHERE product_id = ?', [$productId]));
    }

    /** Attach first media row (as 'image') to a list of products in one query. */
    public static function withImages(PDO $db, array $products): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn($p) => (int) ($p['image_id'] ?? 0), $products))));
        $media = [];
        if ($ids) foreach (db_all($db, 'SELECT * FROM media WHERE id IN (' . in_placeholders($ids) . ')', $ids) as $m) $media[$m['id']] = $m;
        $pids = array_map(fn($p) => (int) $p['id'], $products);
        $second = [];
        if ($pids) {
            foreach (db_all($db, 'SELECT pi.product_id, m.* FROM product_images pi JOIN media m ON m.id = pi.media_id WHERE pi.product_id IN (' . in_placeholders($pids) . ') AND pi.sort = 1', $pids) as $m) $second[$m['product_id']] = $m;
        }
        $hasVariants = [];
        if ($pids) foreach (db_col($db, 'SELECT DISTINCT product_id FROM product_variants WHERE status = \'active\' AND product_id IN (' . in_placeholders($pids) . ')', $pids) as $v) $hasVariants[(int) $v] = true;
        foreach ($products as &$p) {
            $p['image'] = $media[$p['image_id'] ?? 0] ?? null;
            $p['image2'] = $second[$p['id']] ?? null;
            $p['has_variants'] = $hasVariants[(int) $p['id']] ?? false;
        }
        return $products;
    }
}
