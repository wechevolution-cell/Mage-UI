<?php
declare(strict_types=1);

/** Shop staff: roles + granular module permissions, checked server-side on every admin route. */
final class ShopUsers
{
    public const MODULES = [
        'dashboard' => 'Πίνακας ελέγχου', 'products' => 'Προϊόντα', 'categories' => 'Κατηγορίες', 'import' => 'Εισαγωγή / εξαγωγή',
        'orders' => 'Παραγγελίες', 'customers' => 'Πελάτες', 'pages' => 'Σελίδες & blog', 'media' => 'Πολυμέσα',
        'appearance' => 'Εμφάνιση, αρχική & μενού', 'popups' => 'Pop-ups & συνδρομητές', 'feeds' => 'Feeds', 'seo' => 'SEO',
        'settings' => 'Ρυθμίσεις', 'users' => 'Χρήστες',
    ];

    public const ROLES = [
        'owner' => 'Ιδιοκτήτης',
        'administrator' => 'Διαχειριστής',
        'editor' => 'Συντάκτης',
        'product_manager' => 'Υπεύθυνος προϊόντων',
    ];

    /** Role rank prevents privilege escalation (a user can only manage strictly lower ranks). */
    public const RANK = ['owner' => 4, 'administrator' => 3, 'product_manager' => 2, 'editor' => 2];

    public static function roleDefaults(string $role): array
    {
        $all = array_keys(self::MODULES);
        return match ($role) {
            'owner' => $all,
            'administrator' => $all,
            'editor' => ['dashboard', 'pages', 'media', 'appearance', 'popups', 'seo'],
            'product_manager' => ['dashboard', 'products', 'categories', 'import', 'media', 'feeds', 'orders'],
            default => ['dashboard'],
        };
    }

    public static function permissions(array $user): array
    {
        if ($user['role'] === 'owner') return array_keys(self::MODULES);
        $custom = jd($user['permissions'] ?? null, null);
        $perms = is_array($custom) ? $custom : self::roleDefaults($user['role']);
        $perms = array_values(array_intersect($perms, array_keys(self::MODULES)));
        if (!in_array('dashboard', $perms, true)) $perms[] = 'dashboard';
        return $perms;
    }

    public static function can(?array $user, string $module): bool
    {
        if (!$user) return false;
        if (!empty($user['_impersonated'])) return true;
        return in_array($module, self::permissions($user), true);
    }

    public static function canManage(array $actor, array $target): bool
    {
        if (!empty($actor['_impersonated'])) return $target['role'] !== 'owner' || true;
        return (self::RANK[$actor['role']] ?? 0) > (self::RANK[$target['role']] ?? 0);
    }

    /** Roles the actor may assign (never owner, never above own rank). */
    public static function assignableRoles(array $actor): array
    {
        $out = [];
        foreach (self::ROLES as $k => $l) {
            if ($k === 'owner') continue;
            if (!empty($actor['_impersonated']) || (self::RANK[$actor['role']] ?? 0) > (self::RANK[$k] ?? 0)) $out[$k] = $l;
        }
        return $out;
    }
}
