<?php
declare(strict_types=1);

/** System-wide audit trail (who, what, when, which shop, IP, outcome) — stored in the platform DB. */
final class Audit
{
    /** Current actor resolved by controllers: ['type'=>..., 'id'=>..., 'name'=>..., 'impersonator_id'=>...] */
    public static array $actor = ['type' => 'system', 'id' => null, 'name' => 'system'];

    public static function log(string $action, ?int $shopId = null, ?string $entityType = null, string|int|null $entityId = null, array $details = [], string $outcome = 'success'): void
    {
        try {
            db_insert(DB::platform(), 'audit_logs', [
                'actor_type' => self::$actor['type'], 'actor_id' => self::$actor['id'], 'actor_name' => mb_substr((string) self::$actor['name'], 0, 120),
                'impersonator_id' => self::$actor['impersonator_id'] ?? null, 'shop_id' => $shopId, 'action' => $action,
                'entity_type' => $entityType, 'entity_id' => $entityId === null ? null : (string) $entityId, 'outcome' => $outcome,
                'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(), 'details' => $details ? je($details) : null, 'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            error_log('audit failed: ' . $e->getMessage());
        }
    }
}

final class Notifications
{
    /** Creates a notification once per dedupe key; optionally emails the platform notification address. */
    public static function create(string $type, ?int $shopId, string $title, string $body = '', ?string $dedupe = null, bool $email = true): bool
    {
        $p = DB::platform();
        if ($dedupe && db_val($p, 'SELECT 1 FROM notifications WHERE dedupe_key = ?', [$dedupe])) return false;
        $id = db_insert($p, 'notifications', ['type' => $type, 'shop_id' => $shopId, 'title' => $title, 'body' => $body, 'dedupe_key' => $dedupe, 'created_at' => now()]);
        $to = setting_get($p, 'notify_email', '');
        if ($email && $to) {
            $sent = Mailer::send($to, '[wechevo e-shop] ' . $title, Mailer::layout($title, '<p>' . nl2br(e($body)) . '</p>'));
            if ($sent) db_exec($p, 'UPDATE notifications SET emailed_at = ? WHERE id = ?', [now(), $id]);
        }
        return true;
    }

    public static function unreadCount(): int { return (int) db_val(DB::platform(), 'SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'); }
}
