<?php
declare(strict_types=1);

/** Template registry. A template is presentation only: templates/{key}/theme.json + assets + optional view overrides. */
final class Templates
{
    private static array $manifests = [];

    public static function keys(): array
    {
        $keys = [];
        foreach (glob(ROOT . '/templates/*/theme.json') as $f) { $keys[] = basename(dirname($f)); }
        sort($keys);
        return $keys;
    }

    public static function manifest(string $key): array
    {
        if (!isset(self::$manifests[$key])) {
            $f = ROOT . '/templates/' . preg_replace('/[^a-z0-9-]/', '', $key) . '/theme.json';
            self::$manifests[$key] = is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
            self::$manifests[$key]['key'] = $key;
        }
        return self::$manifests[$key];
    }

    /** Registers templates found on disk into the platform DB (new ones enabled by default). */
    public static function sync(): void
    {
        $p = DB::platform();
        foreach (self::keys() as $i => $key) {
            $m = self::manifest($key);
            $row = db_one($p, 'SELECT id FROM templates WHERE key = ?', [$key]);
            if ($row) {
                db_update($p, 'templates', ['name' => $m['name'] ?? $key, 'industry' => $m['industry'] ?? null, 'description' => $m['description'] ?? null, 'updated_at' => now()], 'id = ?', [$row['id']]);
            } else {
                db_insert($p, 'templates', ['key' => $key, 'name' => $m['name'] ?? $key, 'industry' => $m['industry'] ?? null, 'description' => $m['description'] ?? null,
                    'enabled' => 1, 'sort' => $i + 1, 'created_at' => now()]);
            }
        }
    }

    public static function all(): array { return db_all(DB::platform(), 'SELECT * FROM templates ORDER BY sort, key'); }

    public static function find(string $key): ?array { return db_one(DB::platform(), 'SELECT * FROM templates WHERE key = ?', [$key]); }

    /** Templates the Super Admin allows for a given plan (enabled + plan restriction). */
    public static function availableForPlan(?int $planId): array
    {
        return array_values(array_filter(self::all(), function ($t) use ($planId) {
            if (!$t['enabled']) return false;
            $allowed = jd($t['allowed_plans'], null);
            return !$allowed || ($planId && in_array($planId, array_map('intval', $allowed), true));
        }));
    }

    public static function isAllowed(string $key, ?int $planId): bool
    {
        foreach (self::availableForPlan($planId) as $t) { if ($t['key'] === $key) return true; }
        return false;
    }

    /** Default design tokens for a template (customer overrides are stored per shop). */
    public static function tokens(string $key): array
    {
        return (array) (self::manifest($key)['tokens'] ?? []);
    }
}
