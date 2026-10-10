<?php
declare(strict_types=1);

/** Order lifecycle. Orders snapshot names/SKUs/prices so deactivating products never alters history. */
final class Orders
{
    public const STATUSES = ['pending', 'awaiting_payment', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];
    public const SHIPPING_STATUSES = ['not_shipped', 'shipped', 'delivered', 'returned'];

    /** @return array{ok:bool,error?:string,errors?:array,order?:array} */
    public static function place(PDO $db, array $shop, array $in, ?array $customer): array
    {
        $lines = Cart::lines($db, $shop);
        if (!$lines) return ['ok' => false, 'error' => 'Το καλάθι σας είναι άδειο.'];
        $errors = [];
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Συμπληρώστε έγκυρο email.';
        foreach (['first_name' => 'Όνομα', 'last_name' => 'Επώνυμο', 'phone' => 'Τηλέφωνο', 'address' => 'Διεύθυνση', 'city' => 'Πόλη', 'postcode' => 'Τ.Κ.'] as $f => $label) {
            if (trim((string) ($in[$f] ?? '')) === '' && !($f !== 'phone' && ($in['shipping_method'] ?? '') === 'pickup' && in_array($f, ['address', 'city', 'postcode'], true))) $errors[$f] = "Το πεδίο «{$label}» είναι υποχρεωτικό.";
        }
        if (!empty($in['postcode']) && !preg_match('/^\d{3}\s?\d{2}$|^\d{4}$/', trim((string) $in['postcode']))) $errors['postcode'] = 'Μη έγκυρος Τ.Κ.';
        if (empty($in['accept_terms'])) $errors['accept_terms'] = 'Πρέπει να αποδεχτείτε τους όρους χρήσης.';
        $pay = ShopSettings::get($db, 'payments');
        $method = (string) ($in['payment_method'] ?? '');
        if (empty($pay[$method]['enabled'])) $errors['payment_method'] = 'Επιλέξτε τρόπο πληρωμής.';
        $ship = ShopSettings::get($db, 'shipping');
        $zone = (string) ($in['shipping_zone'] ?? 'mainland');
        $smethod = ($in['shipping_method'] ?? 'courier') === 'pickup' && !empty($ship['pickup']['enabled']) ? 'pickup' : 'courier';
        if ($smethod === 'courier' && empty($ship['zones'][$zone]['enabled'])) $errors['shipping_zone'] = 'Επιλέξτε περιοχή αποστολής.';
        $chk = ShopSettings::get($db, 'checkout');
        $totals = Cart::totals($db, $lines, $zone, $smethod, $method, !empty($in['gift_wrap']));
        if ((float) $chk['min_order'] > 0 && $totals['subtotal'] < (float) $chk['min_order']) $errors['cart'] = 'Ελάχιστη αξία παραγγελίας ' . money((float) $chk['min_order']) . '.';
        if ($errors) return ['ok' => false, 'errors' => $errors, 'error' => reset($errors)];

        try {
            $order = db_tx($db, function () use ($db, $shop, $in, $lines, $email, $method, $zone, $smethod, $totals, $customer, $chk, $ship, $pay) {
                // Re-validate stock inside the write lock and decrement
                foreach ($lines as $l) {
                    $p = Products::find($db, (int) $l['product']['id']);
                    if (!$p || !Catalog::isVisible($db, $shop, (int) $p['id'])) throw new DomainException('Το προϊόν «' . $l['name'] . '» δεν είναι πλέον διαθέσιμο.');
                    $v = $l['variant'] ? db_one($db, 'SELECT * FROM product_variants WHERE id = ?', [$l['variant']['id']]) : null;
                    $avail = Cart::available($p, $v);
                    if ($avail !== null && $l['qty'] > $avail) throw new DomainException('Δεν υπάρχει αρκετό απόθεμα για «' . $l['name'] . '».');
                    if ($avail !== null) {
                        if ($v) db_exec($db, 'UPDATE product_variants SET stock = stock - ? WHERE id = ?', [$l['qty'], $v['id']]);
                        else db_exec($db, 'UPDATE products SET stock = stock - ? WHERE id = ?', [$l['qty'], $p['id']]);
                    }
                }
                $custId = $customer['id'] ?? null;
                if (!$custId) {
                    $existing = db_one($db, 'SELECT id FROM customers WHERE email = ?', [$email]);
                    $custId = $existing ? (int) $existing['id'] : db_insert($db, 'customers', ['email' => $email, 'first_name' => trim((string) $in['first_name']), 'last_name' => trim((string) $in['last_name']),
                        'phone' => trim((string) $in['phone']), 'marketing_consent' => !empty($in['newsletter']) ? 1 : 0, 'status' => 'active', 'created_at' => now()]);
                }
                $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($chk['order_prefix'] ?: 'ORD'))) ?: 'ORD';
                $seq = (int) db_val($db, 'SELECT COUNT(*) FROM orders') + 1;
                do {
                    $number = $prefix . '-' . date('ymd') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                    $seq++;
                } while (db_val($db, 'SELECT 1 FROM orders WHERE number = ?', [$number]));
                $address = ['address' => trim((string) ($in['address'] ?? '')), 'city' => trim((string) ($in['city'] ?? '')), 'postcode' => trim((string) ($in['postcode'] ?? '')),
                    'region' => trim((string) ($in['region'] ?? '')), 'country' => $zone === 'cyprus' ? 'CY' : 'GR', 'zone_label' => $ship['zones'][$zone]['label'] ?? ''];
                $billing = !empty($in['invoice']) ? ['invoice' => true, 'company' => trim((string) ($in['company'] ?? '')), 'vat_number' => trim((string) ($in['vat_number'] ?? '')),
                    'tax_office' => trim((string) ($in['tax_office'] ?? '')), 'activity' => trim((string) ($in['activity'] ?? ''))] : ['invoice' => false];
                $orderId = db_insert($db, 'orders', [
                    'number' => $number, 'access_token' => random_token(18), 'customer_id' => $custId, 'email' => $email, 'phone' => trim((string) $in['phone']),
                    'first_name' => trim((string) $in['first_name']), 'last_name' => trim((string) $in['last_name']), 'shipping_address' => je($address), 'billing' => je($billing),
                    'shipping_zone' => $smethod === 'pickup' ? 'pickup' : $zone, 'shipping_method' => $smethod === 'pickup' ? ($ship['pickup']['label'] ?? 'Παραλαβή') : 'Courier',
                    'shipping_cost' => $totals['shipping'], 'payment_method' => $method, 'payment_fee' => $totals['payment_fee'], 'payment_status' => 'pending',
                    'status' => $method === 'bank' ? 'awaiting_payment' : 'pending', 'shipping_status' => 'not_shipped', 'subtotal' => $totals['subtotal'], 'discount' => 0,
                    'tax_total' => $totals['vat'], 'total' => $totals['total'], 'currency' => 'EUR', 'customer_note' => Sanitizer::text($in['notes'] ?? '', 1000) ?: null,
                    'gift_wrap' => $totals['gift_wrap'] > 0 ? 1 : 0, 'ip' => client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($lines as $l) {
                    db_insert($db, 'order_items', ['order_id' => $orderId, 'product_id' => $l['product']['id'], 'variant_id' => $l['variant']['id'] ?? null, 'name' => $l['name'],
                        'sku' => $l['sku'], 'options' => $l['options'] ?: null, 'unit_price' => $l['unit'], 'qty' => $l['qty'], 'total' => $l['total']]);
                }
                db_insert($db, 'order_history', ['order_id' => $orderId, 'type' => 'status', 'status' => $method === 'bank' ? 'awaiting_payment' : 'pending', 'note' => 'Η παραγγελία δημιουργήθηκε', 'actor' => 'customer', 'created_at' => now()]);
                if ($customer && !empty($in['save_address'])) {
                    if (!db_val($db, 'SELECT 1 FROM customer_addresses WHERE customer_id = ? AND address = ? AND postcode = ?', [$custId, $address['address'], $address['postcode']])) {
                        db_insert($db, 'customer_addresses', ['customer_id' => $custId, 'first_name' => $in['first_name'], 'last_name' => $in['last_name'], 'phone' => $in['phone'], 'address' => $address['address'],
                            'city' => $address['city'], 'postcode' => $address['postcode'], 'region' => $address['region'], 'country' => $address['country'],
                            'is_default' => db_val($db, 'SELECT 1 FROM customer_addresses WHERE customer_id = ?', [$custId]) ? 0 : 1, 'created_at' => now()]);
                    }
                }
                return db_one($db, 'SELECT * FROM orders WHERE id = ?', [$orderId]);
            });
        } catch (DomainException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        if (!empty($in['newsletter'])) Popups::subscribe($db, $email, 'checkout', null, true, 'Εγγραφή στο ταμείο');
        Cart::clear();
        self::sendEmails($db, $shop, $order);
        return ['ok' => true, 'order' => $order];
    }

    public static function items(PDO $db, int $orderId): array { return db_all($db, 'SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]); }

    public static function sendEmails(PDO $db, array $shop, array $order): void
    {
        $store = ShopSettings::get($db, 'store');
        $notif = ShopSettings::get($db, 'notifications');
        $pay = ShopSettings::get($db, 'payments');
        $name = ShopSettings::storeName($db, $shop);
        $items = self::items($db, (int) $order['id']);
        $rows = '';
        foreach ($items as $it) $rows .= '<tr><td style="padding:6px 0">' . e($it['name']) . ($it['options'] ? ' <small>(' . e($it['options']) . ')</small>' : '') . ' × ' . (int) $it['qty'] . '</td><td align="right">' . money((float) $it['total']) . '</td></tr>';
        $tbl = '<table width="100%" style="border-collapse:collapse">' . $rows
            . '<tr><td>Αποστολή</td><td align="right">' . money((float) $order['shipping_cost']) . '</td></tr>'
            . ((float) $order['payment_fee'] > 0 ? '<tr><td>Χρέωση πληρωμής</td><td align="right">' . money((float) $order['payment_fee']) . '</td></tr>' : '')
            . '<tr><td><b>Σύνολο</b></td><td align="right"><b>' . money((float) $order['total']) . '</b></td></tr></table>';
        $instr = $order['payment_method'] === 'bank' ? '<p><b>Οδηγίες πληρωμής:</b><br>' . nl2br(e($pay['bank']['instructions'] ?? '')) . '</p>' : '';
        $url = abs_url('/order/' . $order['number'] . '/?t=' . $order['access_token']);
        if (!empty($notif['notify_customer'])) {
            Mailer::send($order['email'], "Παραγγελία {$order['number']} — $name", Mailer::layout('Ευχαριστούμε για την παραγγελία σας!',
                '<p>Λάβαμε την παραγγελία <b>' . e($order['number']) . '</b>.</p>' . $tbl . $instr . '<p><a href="' . e($url) . '">Δείτε την παραγγελία σας</a></p>', $name),
                ['shop_id' => $shop['id'], 'from_name' => $name, 'reply_to' => $store['email'] ?? null]);
        }
        $adminTo = ($notif['order_email'] ?? '') ?: ($store['email'] ?? '');
        if (!empty($notif['notify_admin_new_order']) && $adminTo) {
            Mailer::send($adminTo, "Νέα παραγγελία {$order['number']} (" . money((float) $order['total']) . ')', Mailer::layout('Νέα παραγγελία',
                '<p>' . e($order['first_name'] . ' ' . $order['last_name']) . ' — ' . e($order['email']) . ' — ' . e($order['phone']) . '</p>' . $tbl, $name), ['shop_id' => $shop['id']]);
        }
    }

    public static function changeStatus(PDO $db, int $orderId, array $changes, string $note, string $actor, bool $notify): void
    {
        $o = db_one($db, 'SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o) return;
        $upd = ['updated_at' => now()];
        foreach (['status' => self::STATUSES, 'payment_status' => self::PAYMENT_STATUSES, 'shipping_status' => self::SHIPPING_STATUSES] as $f => $allowed) {
            if (isset($changes[$f]) && in_array($changes[$f], $allowed, true) && $changes[$f] !== $o[$f]) $upd[$f] = $changes[$f];
        }
        if (isset($changes['tracking_number'])) $upd['tracking_number'] = mb_substr(trim((string) $changes['tracking_number']), 0, 100) ?: null;
        db_update($db, 'orders', $upd, 'id = ?', [$orderId]);
        // Restock on cancellation (once)
        if (isset($upd['status']) && in_array($upd['status'], ['cancelled', 'refunded'], true) && !in_array($o['status'], ['cancelled', 'refunded'], true)) {
            foreach (self::items($db, $orderId) as $it) {
                if ($it['variant_id']) db_exec($db, 'UPDATE product_variants SET stock = stock + ? WHERE id = ?', [$it['qty'], $it['variant_id']]);
                elseif ($it['product_id']) db_exec($db, 'UPDATE products SET stock = stock + ? WHERE id = ? AND manage_stock = 1', [$it['qty'], $it['product_id']]);
            }
        }
        $desc = [];
        foreach ($upd as $k => $v) { if ($k !== 'updated_at') $desc[] = $k . ': ' . ($k === 'tracking_number' ? $v : status_label((string) $v)); }
        if ($desc || trim($note) !== '') {
            db_insert($db, 'order_history', ['order_id' => $orderId, 'type' => $desc ? 'status' : 'note', 'status' => $upd['status'] ?? null,
                'note' => trim(implode(', ', $desc) . ($note !== '' ? ' — ' . $note : '')), 'actor' => $actor, 'notify_customer' => $notify ? 1 : 0, 'created_at' => now()]);
        }
        if ($notify && ($desc || $note)) {
            $name = ShopSettings::storeName($db, App::$shop);
            Mailer::send($o['email'], "Ενημέρωση παραγγελίας {$o['number']}", Mailer::layout('Ενημέρωση παραγγελίας',
                '<p>Η παραγγελία <b>' . e($o['number']) . '</b> ενημερώθηκε:</p><p>' . e(implode(', ', $desc)) . '</p>' . ($note ? '<p>' . nl2br(e($note)) . '</p>' : '')
                . (!empty($upd['tracking_number']) ? '<p>Αριθμός αποστολής: <b>' . e($upd['tracking_number']) . '</b></p>' : ''), $name), ['shop_id' => App::shopId(), 'from_name' => $name]);
        }
    }
}
