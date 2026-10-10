<?php
declare(strict_types=1);

/**
 * Subscription timer: ACTIVE → EXPIRING (≤10 days) → EXPIRED → LOCKED (after optional grace days).
 * Renewals/plan changes touch only platform tables — never products, orders, templates, users or settings.
 * Billing goes through payments/payment_providers so Stripe/Viva can be plugged in later.
 */
final class Subscriptions
{
    public const WARN_DAYS = [10, 3, 1];

    public static function current(int $shopId): ?array
    {
        return db_one(DB::platform(), "SELECT * FROM subscriptions WHERE shop_id = ? ORDER BY CASE status WHEN 'active' THEN 0 ELSE 1 END, end_date DESC, id DESC LIMIT 1", [$shopId]);
    }

    public static function history(int $shopId, ?int $subId, string $action, ?array $plan, ?int $months, ?string $start, ?string $end, ?float $price, string $note = ''): void
    {
        db_insert(DB::platform(), 'subscription_history', [
            'shop_id' => $shopId, 'subscription_id' => $subId, 'action' => $action, 'plan_id' => $plan['id'] ?? null, 'plan_name' => $plan['name'] ?? null,
            'duration_months' => $months, 'start_date' => $start, 'end_date' => $end, 'price' => $price,
            'actor_type' => Audit::$actor['type'], 'actor_id' => Audit::$actor['id'], 'note' => $note ?: null, 'created_at' => now(),
        ]);
    }

    public static function graceDays(): int { return max(0, (int) setting_get(DB::platform(), 'grace_days', 0)); }

    /** Lazy status refresh (runs on every tenant request and in cron). */
    public static function refreshShopStatus(int $shopId): void
    {
        $shop = Shops::find($shopId);
        if (!$shop || in_array($shop['status'], ['suspended', 'deleted', 'pending'], true)) return;
        $sub = self::current($shopId);
        if (!$sub) return;
        $days = days_until($sub['end_date']);
        if ($days === null) return;

        if ($days < 0) {
            if ($sub['status'] === 'active') {
                db_update(DB::platform(), 'subscriptions', ['status' => 'expired', 'updated_at' => now()], 'id = ?', [$sub['id']]);
            }
            $grace = self::graceDays();
            if (-$days > $grace) {
                if ($shop['status'] !== 'locked') {
                    if ($shop['status'] !== 'expired') {
                        self::history($shopId, (int) $sub['id'], 'expired', Shops::plan($shop), (int) $sub['duration_months'], $sub['start_date'], $sub['end_date'], null, 'Λήξη συνδρομής');
                        Notifications::create('expired', $shopId, 'Έληξε η συνδρομή: ' . $shop['name'], 'Η συνδρομή έληξε στις ' . fmt_date($sub['end_date']) . '.', 'expired:' . $sub['id']);
                    }
                    $prev = Audit::$actor;
                    Audit::$actor = ['type' => 'system', 'id' => null, 'name' => 'system'];
                    Shops::setStatus($shopId, 'locked', 'Αυτόματο κλείδωμα λόγω λήξης συνδρομής');
                    Audit::$actor = $prev;
                }
            } elseif ($shop['status'] !== 'expired') {
                db_update(DB::platform(), 'shops', ['status' => 'expired', 'updated_at' => now()], 'id = ?', [$shopId]);
                self::history($shopId, (int) $sub['id'], 'expired', Shops::plan($shop), (int) $sub['duration_months'], $sub['start_date'], $sub['end_date'], null, 'Λήξη συνδρομής (περίοδος χάριτος)');
                Notifications::create('expired', $shopId, 'Έληξε η συνδρομή: ' . $shop['name'], 'Περίοδος χάριτος ' . $grace . ' ημερών.', 'expired:' . $sub['id']);
            }
            return;
        }

        $target = $days <= self::WARN_DAYS[0] ? 'expiring' : 'active';
        if (in_array($shop['status'], ['active', 'expiring'], true) && $shop['status'] !== $target) {
            db_update(DB::platform(), 'shops', ['status' => $target, 'updated_at' => now()], 'id = ?', [$shopId]);
        }
        // Warning notifications at 10 / 3 / 1 days (once each, missed days still caught)
        $threshold = null;
        foreach (self::WARN_DAYS as $t) { if ($days <= $t) $threshold = $t; }
        if ($threshold !== null) {
            $isNew = Notifications::create('expiring', $shopId, "Λήγει σε $days " . ($days === 1 ? 'ημέρα' : 'ημέρες') . ': ' . $shop['name'],
                'Η συνδρομή του καταστήματος λήγει στις ' . fmt_date($sub['end_date']) . '. Ανανεώστε από τη σελίδα του καταστήματος.', "warn:{$sub['id']}:$threshold");
            if ($isNew) self::warnOwner($shop, $sub, $days);
        }
    }

    /** Email the shop owner once per warning threshold (10 / 3 / 1 days). Data and storefront are unaffected. */
    private static function warnOwner(array $shop, array $sub, int $days): void
    {
        $to = $shop['email'] ?? '';
        if (!$to && is_file(DB::shopPath((int) $shop['id']))) {
            $to = (string) db_val(DB::shop((int) $shop['id']), "SELECT email FROM users WHERE role = 'owner' AND deleted_at IS NULL ORDER BY id LIMIT 1");
        }
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $when = $days <= 0 ? 'σήμερα' : "σε $days " . ($days === 1 ? 'ημέρα' : 'ημέρες');
        Mailer::send($to, 'Η συνδρομή του e-shop σας λήγει ' . $when, Mailer::layout('Υπενθύμιση ανανέωσης',
            '<p>Η συνδρομή του καταστήματος <b>' . e($shop['name']) . '</b> λήγει ' . $when . ' (' . e(fmt_date($sub['end_date'])) . ').</p>'
            . '<p>Για να παραμείνει το κατάστημα ανοιχτό, επικοινωνήστε μαζί μας για ανανέωση. Τα προϊόντα, οι παραγγελίες και οι ρυθμίσεις σας δεν επηρεάζονται.</p>'),
            ['shop_id' => (int) $shop['id']]);
    }

    /**
     * Renew or reset the timer. mode: 'extend' (continue from current end, if not yet expired) or 'reset' (start today).
     */
    public static function renew(int $shopId, int $months, string $mode = 'extend', bool $paid = false, string $note = ''): array
    {
        $shop = Shops::find($shopId);
        if (!$shop) return ['ok' => false, 'error' => 'Δεν βρέθηκε κατάστημα.'];
        $plan = Shops::plan($shop);
        if (!$plan) return ['ok' => false, 'error' => 'Το κατάστημα δεν έχει πακέτο.'];
        if ($months < 1 || $months > 60) return ['ok' => false, 'error' => 'Μη έγκυρη διάρκεια.'];
        $p = DB::platform();
        $cur = self::current($shopId);
        $start = today();
        if ($mode === 'extend' && $cur && days_until($cur['end_date']) >= 0) {
            $start = (new DateTimeImmutable($cur['end_date']))->format('Y-m-d');
        }
        $end = add_months($start, $months);
        $price = round((float) $plan['price'] * $months, 2);
        $subId = db_tx($p, function () use ($p, $shopId, $plan, $start, $end, $months, $price, $paid, $cur, $mode, $note) {
            if ($cur) db_update($p, 'subscriptions', ['status' => 'completed', 'updated_at' => now()], 'id = ? AND status IN (\'active\',\'expired\')', [$cur['id']]);
            $subId = db_insert($p, 'subscriptions', ['shop_id' => $shopId, 'plan_id' => $plan['id'], 'status' => 'active', 'start_date' => $start, 'end_date' => $end,
                'duration_months' => $months, 'price' => $price, 'currency' => 'EUR', 'provider_code' => 'manual', 'created_at' => now()]);
            db_insert($p, 'payments', ['shop_id' => $shopId, 'subscription_id' => $subId, 'provider_code' => 'manual', 'kind' => 'subscription', 'amount' => $price,
                'status' => $paid ? 'paid' : 'pending', 'paid_at' => $paid ? now() : null, 'created_at' => now()]);
            self::history($shopId, $subId, $mode === 'reset' ? 'timer_reset' : 'renewed', $plan, $months, $start, $end, $price, $note);
            // Status back to ACTIVE (or EXPIRING if the new period is very short). Shop data untouched.
            $days = days_until($end);
            db_update($p, 'shops', ['status' => $days <= self::WARN_DAYS[0] ? 'expiring' : 'active', 'locked_at' => null, 'updated_at' => now()], 'id = ?', [$shopId]);
            return $subId;
        });
        Audit::log('subscription.' . ($mode === 'reset' ? 'reset' : 'renewed'), $shopId, 'subscription', $subId, ['months' => $months, 'start' => $start, 'end' => $end, 'paid' => $paid]);
        Notifications::create('renewed', $shopId, 'Ανανέωση: ' . $shop['name'], "Νέα λήξη: " . fmt_date($end), 'renew:' . $subId, false);
        return ['ok' => true, 'subscription_id' => $subId, 'end' => $end];
    }

    /**
     * Change package. On downgrade where active products exceed the new limit:
     *  - 'deactivate_excess': most recently activated products become inactive now (never deleted)
     *  - 'shop_choose': storefront shows only the oldest-activated N, admin must choose which stay active.
     */
    public static function changePlan(int $shopId, int $planId, string $downgradeMode = 'shop_choose', string $note = ''): array
    {
        $shop = Shops::find($shopId);
        $new = Plans::find($planId);
        if (!$shop || !$new) return ['ok' => false, 'error' => 'Μη έγκυρο πακέτο.'];
        $old = Shops::plan($shop);
        if ((int) $shop['plan_id'] === $planId) return ['ok' => true, 'deactivated' => 0];
        $p = DB::platform();
        db_update($p, 'shops', ['plan_id' => $planId, 'updated_at' => now()], 'id = ?', [$shopId]);
        $cur = self::current($shopId);
        if ($cur) db_update($p, 'subscriptions', ['plan_id' => $planId, 'updated_at' => now()], 'id = ?', [$cur['id']]);
        $action = ((float) $new['price'] >= (float) ($old['price'] ?? 0)) ? 'upgraded' : 'downgraded';
        self::history($shopId, $cur['id'] ?? null, $action, $new, $cur['duration_months'] ?? null, $cur['start_date'] ?? null, $cur['end_date'] ?? null, (float) $new['price'], trim(($old['name'] ?? '?') . ' → ' . $new['name'] . '. ' . $note));

        $shop = Shops::find($shopId);
        $limit = Shops::activeLimit($shop);
        $db = DB::shop($shopId);
        $active = Quota::activeCount($db);
        $deactivated = 0;
        if ($active > $limit) {
            if ($downgradeMode === 'deactivate_excess') {
                $deactivated = Quota::deactivateExcess($db, $limit);
                db_update($p, 'shops', ['quota_mode' => 'normal'], 'id = ?', [$shopId]);
            } else {
                db_update($p, 'shops', ['quota_mode' => 'over_limit'], 'id = ?', [$shopId]);
            }
        } else {
            db_update($p, 'shops', ['quota_mode' => 'normal'], 'id = ?', [$shopId]);
        }
        Audit::log('subscription.plan_changed', $shopId, 'shop', $shopId, ['from' => $old['name'] ?? null, 'to' => $new['name'], 'active' => $active, 'limit' => $limit, 'deactivated' => $deactivated, 'mode' => $downgradeMode]);
        return ['ok' => true, 'deactivated' => $deactivated, 'over' => max(0, $active - $limit - $deactivated)];
    }

    /** Cron: refresh every live shop + send warnings. */
    public static function runChecks(): array
    {
        $ids = db_col(DB::platform(), "SELECT id FROM shops WHERE deleted_at IS NULL AND status NOT IN ('deleted','suspended','pending')");
        foreach ($ids as $id) { self::refreshShopStatus((int) $id); }
        return ['checked' => count($ids)];
    }

    public static function expiringSoon(int $days = 10): array
    {
        return db_all(DB::platform(), "SELECT s.*, sub.end_date FROM shops s JOIN subscriptions sub ON sub.id = (SELECT id FROM subscriptions WHERE shop_id = s.id ORDER BY CASE status WHEN 'active' THEN 0 ELSE 1 END, end_date DESC LIMIT 1)
            WHERE s.deleted_at IS NULL AND s.status IN ('active','expiring') AND sub.end_date <= date('now', 'localtime', '+$days days') ORDER BY sub.end_date");
    }
}
