<?php
declare(strict_types=1);

final class AdminSettings
{
    public static function routes(Router $r): void
    {
        $r->get('/admin/settings', fn() => self::store());
        $r->post('/admin/settings', fn() => self::storeSave());
        $r->get('/admin/settings/shipping', fn() => self::shipping());
        $r->post('/admin/settings/shipping', fn() => self::shippingSave());
        $r->get('/admin/settings/payments', fn() => self::payments());
        $r->post('/admin/settings/payments', fn() => self::paymentsSave());
        $r->get('/admin/settings/checkout', fn() => self::checkout());
        $r->post('/admin/settings/checkout', fn() => self::checkoutSave());
        $r->get('/admin/users', fn() => self::users());
        $r->get('/admin/users/new', fn() => self::userForm(null));
        $r->get('/admin/users/{id:\d+}', fn($id) => self::userForm((int) $id));
        $r->post('/admin/users/save', fn() => self::userSave());
        $r->post('/admin/users/{id:\d+}/delete', fn($id) => self::userDelete((int) $id));
        $r->get('/admin/account', fn() => self::account());
        $r->post('/admin/account', fn() => self::accountSave());
        $r->get('/admin/subscription', fn() => self::subscription());
        $r->get('/admin/activity', fn() => self::activity());
    }

    private static function db(): PDO { return ShopAdmin::$db; }

    private static function tabs(string $active): array
    {
        return ['active' => $active, 'items' => ['/admin/settings' => 'Στοιχεία καταστήματος', '/admin/settings/shipping' => 'Αποστολές', '/admin/settings/payments' => 'Πληρωμές', '/admin/settings/checkout' => 'Ταμείο, ΦΠΑ & ειδοποιήσεις']];
    }

    private static function store(): void
    {
        ShopAdmin::render('admin/settings-store', ['s' => ShopSettings::get(self::db(), 'store'), 'cookies' => ShopSettings::get(self::db(), 'cookies'), 'tabs' => self::tabs('/admin/settings')], 'Ρυθμίσεις', [['Ρυθμίσεις', null]]);
    }

    private static function storeSave(): void
    {
        $db = self::db();
        $s = ShopSettings::get($db, 'store');
        foreach (['name' => 120, 'company_name' => 150, 'vat_number' => 20, 'tax_office' => 80, 'phone' => 40, 'address' => 200, 'city' => 80, 'postcode' => 10, 'hours' => 200, 'about_short' => 500] as $k => $max) $s[$k] = in_str($k, '', $max);
        $s['email'] = filter_var(in_str('email'), FILTER_VALIDATE_EMAIL) ?: '';
        $s['map_url'] = Sanitizer::url(in_str('map_url'));
        foreach (array_keys($s['socials']) as $k) $s['socials'][$k] = Sanitizer::url(in_str('social_' . $k));
        ShopSettings::set($db, 'store', $s);
        ShopSettings::set($db, 'cookies', ['banner_text' => in_str('cookie_text', '', 600)]);
        ShopAdmin::audit('settings.store', 'settings', 'store');
        flash('ok', 'Αποθηκεύτηκε.');
        redirect('/admin/settings');
    }

    private static function shipping(): void
    {
        ShopAdmin::render('admin/settings-shipping', ['s' => ShopSettings::get(self::db(), 'shipping'), 'tabs' => self::tabs('/admin/settings/shipping')], 'Αποστολές', [['Ρυθμίσεις', '/admin/settings'], ['Αποστολές', null]]);
    }

    private static function shippingSave(): void
    {
        $db = self::db();
        $s = ShopSettings::get($db, 'shipping');
        foreach (array_keys($s['zones']) as $z) {
            $s['zones'][$z] = ['label' => in_str("zone_{$z}_label", $s['zones'][$z]['label'], 60) ?: $s['zones'][$z]['label'], 'cost' => max(0, (float) in_float("zone_{$z}_cost", 0)), 'enabled' => in_bool("zone_{$z}_enabled")];
        }
        if (!array_filter(array_column($s['zones'], 'enabled')) && !in_bool('pickup_enabled')) { flash('bad', 'Ενεργοποιήστε τουλάχιστον μία ζώνη ή την παραλαβή από το κατάστημα.'); redirect('/admin/settings/shipping'); }
        $s['free_threshold'] = max(0, (float) in_float('free_threshold', 0));
        $s['pickup'] = ['enabled' => in_bool('pickup_enabled'), 'label' => in_str('pickup_label', 'Παραλαβή από το κατάστημα', 60), 'address' => in_str('pickup_address', '', 200)];
        $s['delivery_text'] = in_str('delivery_text', '', 120);
        ShopSettings::set($db, 'shipping', $s);
        ShopAdmin::audit('settings.shipping', 'settings', 'shipping');
        flash('ok', 'Οι αποστολές αποθηκεύτηκαν.');
        redirect('/admin/settings/shipping');
    }

    private static function payments(): void
    {
        ShopAdmin::render('admin/settings-payments', ['s' => ShopSettings::get(self::db(), 'payments'), 'tabs' => self::tabs('/admin/settings/payments')], 'Πληρωμές', [['Ρυθμίσεις', '/admin/settings'], ['Πληρωμές', null]]);
    }

    private static function paymentsSave(): void
    {
        $db = self::db();
        $s = ShopSettings::get($db, 'payments');
        foreach (['cod', 'bank'] as $k) {
            $s[$k] = ['enabled' => in_bool("{$k}_enabled"), 'label' => in_str("{$k}_label", $s[$k]['label'], 60) ?: $s[$k]['label'], 'fee' => max(0, (float) in_float("{$k}_fee", 0)), 'instructions' => in_str("{$k}_instructions", '', 1000)];
        }
        if (!$s['cod']['enabled'] && !$s['bank']['enabled']) { flash('bad', 'Ενεργοποιήστε τουλάχιστον έναν τρόπο πληρωμής.'); redirect('/admin/settings/payments'); }
        ShopSettings::set($db, 'payments', $s);
        ShopAdmin::audit('settings.payments', 'settings', 'payments');
        flash('ok', 'Οι πληρωμές αποθηκεύτηκαν.');
        redirect('/admin/settings/payments');
    }

    private static function checkout(): void
    {
        $db = self::db();
        ShopAdmin::render('admin/settings-checkout', ['c' => ShopSettings::get($db, 'checkout'), 't' => ShopSettings::get($db, 'tax'), 'n' => ShopSettings::get($db, 'notifications'), 'tabs' => self::tabs('/admin/settings/checkout')],
            'Ταμείο', [['Ρυθμίσεις', '/admin/settings'], ['Ταμείο', null]]);
    }

    private static function checkoutSave(): void
    {
        $db = self::db();
        ShopSettings::set($db, 'checkout', ['order_prefix' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', in_str('order_prefix', 'ORD', 8))) ?: 'ORD', 'gift_wrap_enabled' => in_bool('gift_wrap_enabled'),
            'gift_wrap_price' => max(0, (float) in_float('gift_wrap_price', 0)), 'min_order' => max(0, (float) in_float('min_order', 0)), 'invoice_enabled' => in_bool('invoice_enabled'), 'guest_checkout' => true]);
        ShopSettings::set($db, 'tax', ['prices_include_vat' => true, 'vat_rate' => max(0, min(30, (float) in_float('vat_rate', 24)))]);
        ShopSettings::set($db, 'notifications', ['order_email' => filter_var(in_str('order_email'), FILTER_VALIDATE_EMAIL) ?: '', 'notify_admin_new_order' => in_bool('notify_admin_new_order'), 'notify_customer' => in_bool('notify_customer')]);
        ShopAdmin::audit('settings.checkout', 'settings', 'checkout');
        flash('ok', 'Αποθηκεύτηκε.');
        redirect('/admin/settings/checkout');
    }

    /* ---------- users ---------- */
    private static function users(): void
    {
        $db = self::db();
        $count = (int) db_val($db, 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        ShopAdmin::render('admin/users', ['rows' => db_all($db, 'SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id'), 'count' => $count, 'limit' => Shops::staffLimit(ShopAdmin::$shop),
            'staffFeature' => ShopAdmin::feature('staff')], 'Χρήστες', [['Χρήστες', null]]);
    }

    private static function userForm(?int $id): void
    {
        $db = self::db();
        $u = $id ? db_one($db, 'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id && !$u) { ShopAdmin::notFound(); return; }
        if ($u && (int) $u['id'] !== (int) ShopAdmin::$user['id'] && !ShopUsers::canManage(ShopAdmin::$user, $u)) ShopAdmin::forbidden('Δεν μπορείτε να διαχειριστείτε χρήστη ίσου ή ανώτερου ρόλου.');
        ShopAdmin::render('admin/user-form', ['u' => $u, 'roles' => ShopUsers::assignableRoles(ShopAdmin::$user), 'perms' => $u ? ShopUsers::permissions($u) : ShopUsers::roleDefaults('editor')],
            $u['name'] ?? 'Νέος χρήστης', [['Χρήστες', '/admin/users'], [$u['name'] ?? 'Νέος', null]]);
    }

    private static function userSave(): void
    {
        $db = self::db();
        $id = in_int('id') ?: null;
        $actor = ShopAdmin::$user;
        $role = in_str('role');
        $existing = $id ? db_one($db, 'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id && !$existing) { ShopAdmin::notFound(); return; }
        if ($existing && $existing['role'] === 'owner') $role = 'owner';
        elseif (!array_key_exists($role, ShopUsers::assignableRoles($actor))) ShopAdmin::forbidden('Δεν μπορείτε να αναθέσετε αυτόν τον ρόλο.');
        if ($existing && $existing['role'] !== 'owner' && !ShopUsers::canManage($actor, $existing)) ShopAdmin::forbidden('Δεν μπορείτε να διαχειριστείτε αυτόν τον χρήστη.');
        if (!$id) {
            $count = (int) db_val($db, 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
            if (!ShopAdmin::feature('staff') && $count >= 1) { flash('bad', 'Οι επιπλέον χρήστες δεν περιλαμβάνονται στο πακέτο σας.'); redirect('/admin/users'); }
            if ($count >= Shops::staffLimit(ShopAdmin::$shop)) { flash('bad', 'Έχετε φτάσει το όριο χρηστών του πακέτου (' . Shops::staffLimit(ShopAdmin::$shop) . ').'); redirect('/admin/users'); }
        }
        $email = in_str('email', '', 190);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || in_str('name') === '') { flash('bad', 'Συμπληρώστε όνομα και έγκυρο email.'); redirect($id ? '/admin/users/' . $id : '/admin/users/new'); }
        if (db_val($db, 'SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL' . ($id ? ' AND id <> ?' : ''), $id ? [$email, $id] : [$email])) { flash('bad', 'Το email χρησιμοποιείται ήδη.'); redirect($id ? '/admin/users/' . $id : '/admin/users/new'); }
        // Permissions: never above the actor's own permissions
        $actorPerms = ShopUsers::permissions($actor);
        $perms = in_bool('custom_perms') ? array_values(array_intersect(in_arr('perms'), $actorPerms, array_keys(ShopUsers::MODULES))) : null;
        if ($perms !== null && !in_array('users', $actorPerms, true)) $perms = array_values(array_diff($perms, ['users']));
        $row = ['name' => in_str('name', '', 120), 'email' => $email, 'role' => $role, 'permissions' => $role === 'owner' ? null : ($perms !== null ? je($perms) : null),
            'status' => in_str('status') === 'disabled' && $role !== 'owner' ? 'disabled' : 'active', 'updated_at' => now()];
        $pass = (string) ($_POST['password'] ?? '');
        if ($pass !== '' || !$id) {
            if ($e = Auth::validatePassword($pass)) { flash('bad', $e); redirect($id ? '/admin/users/' . $id : '/admin/users/new'); }
            $row['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }
        if ($id) db_update($db, 'users', $row, 'id = ?', [$id]);
        else { $row['created_at'] = now(); $id = db_insert($db, 'users', $row); }
        ShopAdmin::audit('user.saved', 'user', $id, ['role' => $role]);
        flash('ok', 'Ο χρήστης αποθηκεύτηκε.');
        redirect('/admin/users');
    }

    private static function userDelete(int $id): void
    {
        $db = self::db();
        $u = db_one($db, 'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$u || $u['role'] === 'owner' || $id === (int) ShopAdmin::$user['id'] || !ShopUsers::canManage(ShopAdmin::$user, $u)) ShopAdmin::forbidden('Δεν μπορείτε να διαγράψετε αυτόν τον χρήστη.');
        db_update($db, 'users', ['deleted_at' => now(), 'status' => 'disabled', 'email' => $u['email'] . '.deleted.' . $id], 'id = ?', [$id]);
        ShopAdmin::audit('user.deleted', 'user', $id);
        flash('ok', 'Ο χρήστης διαγράφηκε.');
        redirect('/admin/users');
    }

    private static function account(): void { ShopAdmin::render('admin/account', [], 'Ο λογαριασμός μου', [['Λογαριασμός', null]]); }

    private static function accountSave(): void
    {
        $u = ShopAdmin::$user;
        if (!empty($u['_impersonated'])) { flash('bad', 'Η αλλαγή κωδικού δεν επιτρέπεται σε λειτουργία υποστήριξης.'); redirect('/admin/account'); }
        if (!password_verify((string) ($_POST['current'] ?? ''), $u['password_hash'])) { flash('bad', 'Ο τρέχων κωδικός είναι λάθος.'); redirect('/admin/account'); }
        $p = (string) ($_POST['password'] ?? '');
        if ($e = Auth::validatePassword($p)) { flash('bad', $e); redirect('/admin/account'); }
        if ($p !== (string) ($_POST['password2'] ?? '')) { flash('bad', 'Οι κωδικοί δεν ταιριάζουν.'); redirect('/admin/account'); }
        db_update(self::db(), 'users', ['password_hash' => password_hash($p, PASSWORD_DEFAULT), 'updated_at' => now()], 'id = ?', [$u['id']]);
        Auth::login('admin', db_one(self::db(), 'SELECT * FROM users WHERE id = ?', [$u['id']]));
        ShopAdmin::audit('user.password_changed', 'user', $u['id']);
        flash('ok', 'Ο κωδικός άλλαξε.');
        redirect('/admin/account');
    }

    private static function subscription(): void
    {
        $shop = ShopAdmin::$shop;
        $sub = Subscriptions::current((int) $shop['id']);
        ShopAdmin::render('admin/subscription', ['plan' => Shops::plan($shop), 'sub' => $sub, 'days' => $sub ? days_until($sub['end_date']) : null, 'features' => Shops::features($shop),
            'staffLimit' => Shops::staffLimit($shop), 'history' => db_all(DB::platform(), 'SELECT * FROM subscription_history WHERE shop_id = ? ORDER BY id DESC LIMIT 20', [$shop['id']])], 'Συνδρομή & πακέτο', [['Συνδρομή', null]]);
    }

    private static function activity(): void
    {
        $total = (int) db_val(DB::platform(), 'SELECT COUNT(*) FROM audit_logs WHERE shop_id = ?', [App::shopId()]);
        $pg = paginate($total, 50, in_int('page', 1));
        $rows = db_all(DB::platform(), "SELECT * FROM audit_logs WHERE shop_id = ? ORDER BY id DESC LIMIT 50 OFFSET {$pg['offset']}", [App::shopId()]);
        ShopAdmin::render('admin/activity', ['rows' => $rows, 'pg' => $pg], 'Ιστορικό ενεργειών', [['Ιστορικό ενεργειών', null]]);
    }
}
