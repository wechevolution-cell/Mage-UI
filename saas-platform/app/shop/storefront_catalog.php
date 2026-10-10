<?php
declare(strict_types=1);

/** Storefront catalog queries. Every query applies Quota::visibleSql (active + within plan limit). */
final class Catalog
{
    public const SORTS = ['recommended' => 'Προτεινόμενα', 'newest' => 'Νεότερα', 'price_asc' => 'Τιμή: χαμηλή → υψηλή', 'price_desc' => 'Τιμή: υψηλή → χαμηλή', 'name' => 'Όνομα Α–Ω'];

    /** @return array{items:array,total:int,pager:array} */
    public static function query(PDO $db, array $shop, array $o = []): array
    {
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        $where = [$vis];
        $params = $vp;
        if (!empty($o['category_ids'])) {
            $ids = array_map('intval', $o['category_ids']);
            $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))';
            $params = array_merge($params, $ids);
        }
        if (!empty($o['ids'])) {
            $ids = array_map('intval', $o['ids']);
            $where[] = 'p.id IN (' . in_placeholders($ids) . ')';
            $params = array_merge($params, $ids);
        }
        if (!empty($o['exclude'])) {
            $ids = array_map('intval', (array) $o['exclude']);
            $where[] = 'p.id NOT IN (' . in_placeholders($ids) . ')';
            $params = array_merge($params, $ids);
        }
        if (!empty($o['featured'])) $where[] = 'p.featured = 1';
        if (!empty($o['on_sale'])) $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price';
        if (($q = trim((string) ($o['q'] ?? ''))) !== '') {
            $f = '%' . str_fold($q) . '%';
            $where[] = '(FOLD(p.name) LIKE ? OR FOLD(COALESCE(p.brand,\'\')) LIKE ? OR p.sku = ? OR FOLD(COALESCE(p.tags,\'\')) LIKE ? OR p.barcode = ?)';
            array_push($params, $f, $f, $q, $f, $q);
        }
        $price = 'CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END';
        if (isset($o['min']) && $o['min'] !== '' && $o['min'] !== null) { $where[] = "$price >= ?"; $params[] = (float) $o['min']; }
        if (isset($o['max']) && $o['max'] !== '' && $o['max'] !== null) { $where[] = "$price <= ?"; $params[] = (float) $o['max']; }
        if (!empty($o['in_stock'])) $where[] = "p.availability <> 'out_of_stock' AND (p.manage_stock = 0 OR p.stock > 0 OR p.availability IN ('preorder','backorder'))";
        if (!empty($o['brands'])) {
            $b = array_values((array) $o['brands']);
            $where[] = 'p.brand IN (' . in_placeholders($b) . ')';
            $params = array_merge($params, $b);
        }
        foreach ((array) ($o['attrs'] ?? []) as $name => $values) {
            $values = array_values(array_filter((array) $values, 'strlen'));
            if (!$values) continue;
            $where[] = 'p.id IN (SELECT product_id FROM product_attributes WHERE name = ? AND value IN (' . in_placeholders($values) . '))';
            $params = array_merge($params, [$name], $values);
        }
        $order = match ($o['sort'] ?? 'recommended') {
            'newest' => 'p.created_at DESC, p.id DESC',
            'price_asc' => "$price ASC, p.id",
            'price_desc' => "$price DESC, p.id",
            'name' => 'p.name COLLATE NOCASE ASC',
            default => 'p.featured DESC, p.activated_at DESC, p.id DESC',
        };
        $w = implode(' AND ', $where);
        $total = (int) db_val($db, "SELECT COUNT(*) FROM products p WHERE $w", $params);
        $per = max(1, min(96, (int) ($o['per'] ?? 24)));
        $pager = paginate($total, $per, (int) ($o['page'] ?? 1));
        $items = db_all($db, "SELECT p.* FROM products p WHERE $w ORDER BY $order LIMIT $per OFFSET {$pager['offset']}", $params);
        return ['items' => Products::withImages($db, $items), 'total' => $total, 'pager' => $pager];
    }

    /** Single visible product by slug (null → 404, inactive/over-quota products are hidden). */
    public static function product(PDO $db, array $shop, string $slug): ?array
    {
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        $p = db_one($db, "SELECT p.* FROM products p WHERE p.slug = ? AND $vis", array_merge([$slug], $vp));
        if (!$p) return null;
        $p['images'] = Products::images($db, (int) $p['id']);
        $p['variants'] = Products::variants($db, (int) $p['id'], true);
        $p['attributes'] = Products::attributes($db, (int) $p['id']);
        $p['category'] = $p['primary_category_id'] ? Categories::find($db, (int) $p['primary_category_id']) : null;
        if (!$p['category']) {
            $cid = db_val($db, 'SELECT category_id FROM product_categories WHERE product_id = ? LIMIT 1', [$p['id']]);
            $p['category'] = $cid ? Categories::find($db, (int) $cid) : null;
        }
        return $p;
    }

    public static function isVisible(PDO $db, array $shop, int $productId): bool
    {
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        return (bool) db_val($db, "SELECT 1 FROM products p WHERE p.id = ? AND $vis", array_merge([$productId], $vp));
    }

    /** Facets (attributes, brands, price range) for a category scope. */
    public static function facets(PDO $db, array $shop, array $categoryIds = []): array
    {
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        $scope = $vis;
        $params = $vp;
        if ($categoryIds) {
            $scope .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($categoryIds) . '))';
            $params = array_merge($params, array_map('intval', $categoryIds));
        }
        $attrs = [];
        foreach (db_all($db, "SELECT a.name, a.value, COUNT(DISTINCT a.product_id) AS n FROM product_attributes a JOIN products p ON p.id = a.product_id WHERE a.filterable = 1 AND $scope GROUP BY a.name, a.value ORDER BY a.name, a.value LIMIT 400", $params) as $r) {
            $attrs[$r['name']][] = ['value' => $r['value'], 'n' => (int) $r['n']];
        }
        $attrs = array_filter($attrs, fn($v) => count($v) > 1 || count($v) === 1);
        $brands = db_all($db, "SELECT p.brand AS value, COUNT(*) AS n FROM products p WHERE $scope AND p.brand IS NOT NULL AND p.brand <> '' GROUP BY p.brand ORDER BY p.brand", $params);
        $range = db_one($db, "SELECT MIN(CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END) AS min, MAX(p.price) AS max FROM products p WHERE $scope", $params);
        return ['attrs' => array_slice($attrs, 0, 8, true), 'brands' => $brands, 'min' => (float) ($range['min'] ?? 0), 'max' => (float) ($range['max'] ?? 0)];
    }

    public static function related(PDO $db, array $shop, array $product, int $n = 4): array
    {
        $cats = Products::categoryIds($db, (int) $product['id']);
        $r = $cats ? self::query($db, $shop, ['category_ids' => $cats, 'exclude' => [$product['id']], 'per' => $n]) : ['items' => []];
        if (count($r['items']) < $n) {
            $more = self::query($db, $shop, ['exclude' => array_merge([$product['id']], array_column($r['items'], 'id')), 'per' => $n - count($r['items']), 'sort' => 'newest']);
            $r['items'] = array_merge($r['items'], $more['items']);
        }
        return $r['items'];
    }

    public static function counts(PDO $db, array $shop): array
    {
        [$vis, $vp] = Quota::visibleSql($shop, 'p');
        $out = [];
        foreach (db_all($db, "SELECT pc.category_id, COUNT(*) n FROM product_categories pc JOIN products p ON p.id = pc.product_id WHERE $vis GROUP BY pc.category_id", $vp) as $r) $out[(int) $r['category_id']] = (int) $r['n'];
        return $out;
    }
}
