<?php
declare(strict_types=1);

/** Data-driven plans: prices, limits, durations and feature flags live in the DB — never in code. */
final class Plans
{
    public const FEATURES = [
        'import' => 'Μαζική εισαγωγή CSV / XML',
        'export' => 'Εξαγωγή CSV',
        'feeds_marketplace' => 'Feeds Skroutz / BestPrice / Google',
        'feeds_custom' => 'Custom XML feeds',
        'popups' => 'Pop-ups & συλλογή email',
        'blog' => 'Blog / άρθρα',
        'seo_tools' => 'Εργαλεία SEO (redirects, 404, analyzer)',
        'staff' => 'Επιπλέον χρήστες προσωπικού',
        'custom_fonts' => 'Επιλογή γραμματοσειράς',
    ];

    public static function all(bool $activeOnly = false): array
    {
        return db_all(DB::platform(), 'SELECT * FROM subscription_plans' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY sort, price');
    }

    public static function find(?int $id): ?array
    {
        return $id ? db_one(DB::platform(), 'SELECT * FROM subscription_plans WHERE id = ?', [$id]) : null;
    }

    public static function features(?array $plan): array
    {
        $f = jd($plan['features'] ?? '{}');
        $out = [];
        foreach (array_keys(self::FEATURES) as $k) { $out[$k] = array_key_exists($k, $f) ? (bool) $f[$k] : true; }
        return $out;
    }

    public static function durations(?array $plan): array
    {
        $d = array_values(array_filter(array_map('intval', (array) jd($plan['duration_options'] ?? '[1,3,6,12]')), fn($x) => $x > 0));
        return $d ?: [1, 3, 6, 12];
    }

    public static function save(array $data, ?int $id = null): int
    {
        $p = DB::platform();
        $row = [
            'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null,
            'price' => (float) $data['price'], 'setup_fee' => (float) $data['setup_fee'], 'currency' => 'EUR',
            'active_product_limit' => max(0, (int) $data['active_product_limit']),
            'stored_product_limit' => max(1, (int) $data['stored_product_limit']),
            'staff_limit' => max(1, (int) $data['staff_limit']),
            'storage_limit_mb' => max(50, (int) ($data['storage_limit_mb'] ?? 2048)),
            'duration_options' => je(array_values($data['duration_options'])),
            'features' => je($data['features']), 'status' => $data['status'], 'sort' => (int) ($data['sort'] ?? 0), 'updated_at' => now(),
        ];
        if ($id) {
            db_update($p, 'subscription_plans', $row, 'id = ?', [$id]);
            return $id;
        }
        $row['created_at'] = now();
        return db_insert($p, 'subscription_plans', $row);
    }

    /** Default seed on first install (editable later in the panel). */
    public static function seedDefaults(): void
    {
        $p = DB::platform();
        if (db_val($p, 'SELECT COUNT(*) FROM subscription_plans')) return;
        $all = array_fill_keys(array_keys(self::FEATURES), true);
        $starter = $all;
        $starter['feeds_custom'] = false;
        $starter['staff'] = false;
        foreach ([
            ['Starter', 'starter', 20, 20, 1, $starter, 1],
            ['Business', 'business', 45, 50, 3, $all, 2],
            ['Pro', 'pro', 80, 100, 10, $all, 3],
        ] as [$name, $slug, $price, $limit, $staff, $features, $sort]) {
            self::save(['name' => $name, 'slug' => $slug, 'price' => $price, 'setup_fee' => 150, 'active_product_limit' => $limit,
                'stored_product_limit' => 10000, 'staff_limit' => $staff, 'duration_options' => [1, 3, 6, 12], 'features' => $features, 'status' => 'active', 'sort' => $sort]);
        }
    }
}
