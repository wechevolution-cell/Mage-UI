<?php
declare(strict_types=1);

/**
 * Active-product quota. Package limits govern ACTIVE products only (storage up to the plan's stored limit, default 10,000).
 * Every activation path (single toggle, bulk, import, product save) goes through here, inside BEGIN IMMEDIATE.
 * Inactive products are never deleted to enforce a quota.
 */
final class Quota
{
    public static function activeCount(PDO $db): int
    {
        return (int) db_val($db, 'SELECT COUNT(*) FROM products WHERE is_active = 1 AND deleted_at IS NULL');
    }

    public static function storedCount(PDO $db): int
    {
        return (int) db_val($db, 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL');
    }

    public static function limit(array $shop): int { return Shops::activeLimit($shop); }

    public static function status(PDO $db, array $shop): array
    {
        $limit = self::limit($shop);
        $active = self::activeCount($db);
        $stored = self::storedCount($db);
        return ['active' => $active, 'limit' => $limit, 'remaining' => max(0, $limit - $active), 'stored' => $stored,
            'stored_limit' => Shops::storedLimit($shop), 'inactive' => $stored - $active, 'over' => max(0, $active - $limit),
            'pct' => $limit > 0 ? min(100, (int) round($active / $limit * 100)) : 100];
    }

    public static function limitMessage(int $limit, int $active): string
    {
        return "Δεν είναι δυνατή η ενεργοποίηση. Το πακέτο σας επιτρέπει έως $limit ενεργά προϊόντα και αυτή τη στιγμή έχετε $active ενεργά. Απενεργοποιήστε κάποιο προϊόν ή αναβαθμίστε το πακέτο.";
    }

    /**
     * Activate/deactivate a set of products atomically with quota validation.
     * @return array{ok:bool, changed:int, error?:string, active:int, limit:int}
     */
    public static function setActive(PDO $db, array $shop, array $ids, bool $active): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $limit = self::limit($shop);
        if (!$ids) return ['ok' => true, 'changed' => 0, 'active' => self::activeCount($db), 'limit' => $limit];
        return db_tx($db, function () use ($db, $ids, $active, $limit, $shop) {
            $current = self::activeCount($db);
            if (!$active) {
                $n = db_exec($db, 'UPDATE products SET is_active = 0, updated_at = ? WHERE is_active = 1 AND deleted_at IS NULL AND id IN (' . in_placeholders($ids) . ')', array_merge([now()], $ids))->rowCount();
                $now = $current - $n;
                if ($shop['quota_mode'] === 'over_limit' && $now <= $limit) {
                    db_update(DB::platform(), 'shops', ['quota_mode' => 'normal'], 'id = ?', [$shop['id']]);
                }
                return ['ok' => true, 'changed' => $n, 'active' => $now, 'limit' => $limit];
            }
            $toActivate = (int) db_val($db, 'SELECT COUNT(*) FROM products WHERE is_active = 0 AND deleted_at IS NULL AND id IN (' . in_placeholders($ids) . ')', $ids);
            if ($toActivate === 0) return ['ok' => true, 'changed' => 0, 'active' => $current, 'limit' => $limit];
            if ($current + $toActivate > $limit) {
                $room = max(0, $limit - $current);
                $msg = $room === 0
                    ? self::limitMessage($limit, $current)
                    : "Μπορείτε να ενεργοποιήσετε ακόμη $room προϊόντα (επιλέξατε $toActivate). Το πακέτο σας επιτρέπει έως $limit ενεργά προϊόντα και έχετε $current ενεργά.";
                return ['ok' => false, 'changed' => 0, 'error' => $msg, 'active' => $current, 'limit' => $limit];
            }
            $n = db_exec($db, 'UPDATE products SET is_active = 1, activated_at = ?, updated_at = ? WHERE is_active = 0 AND deleted_at IS NULL AND id IN (' . in_placeholders($ids) . ')', array_merge([now(), now()], $ids))->rowCount();
            return ['ok' => true, 'changed' => $n, 'active' => $current + $n, 'limit' => $limit];
        });
    }

    /** Downgrade helper: keep the oldest-activated $limit products active, deactivate the rest. */
    public static function deactivateExcess(PDO $db, int $limit): int
    {
        return db_tx($db, function () use ($db, $limit) {
            $keep = db_col($db, 'SELECT id FROM products WHERE is_active = 1 AND deleted_at IS NULL ORDER BY activated_at ASC, id ASC LIMIT ?', [max(0, $limit)]);
            if (!$keep) return db_exec($db, 'UPDATE products SET is_active = 0 WHERE is_active = 1')->rowCount();
            return db_exec($db, 'UPDATE products SET is_active = 0, updated_at = ? WHERE is_active = 1 AND id NOT IN (' . in_placeholders($keep) . ')', array_merge([now()], $keep))->rowCount();
        });
    }

    /**
     * SQL condition for storefront visibility. Always capped server-side at the plan limit
     * (oldest activations win), so the public store can never show more than the quota.
     */
    public static function visibleSql(array $shop, string $alias = 'p'): array
    {
        $limit = self::limit($shop);
        return ["$alias.is_active = 1 AND $alias.deleted_at IS NULL AND $alias.id IN (SELECT id FROM products WHERE is_active = 1 AND deleted_at IS NULL ORDER BY activated_at ASC, id ASC LIMIT ?)", [$limit]];
    }

    public static function storedLimitReached(PDO $db, array $shop, int $adding = 1): bool
    {
        return self::storedCount($db) + $adding > Shops::storedLimit($shop);
    }
}
