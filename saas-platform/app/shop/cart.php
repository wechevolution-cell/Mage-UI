<?php
declare(strict_types=1);

/** Session cart. Prices, stock and visibility are always re-resolved server-side. */
final class Cart
{
    public const MAX_QTY = 99;

    public static function raw(): array { return $_SESSION['cart'] ?? []; }

    public static function key(int $pid, ?int $vid): string { return $pid . ':' . ($vid ?: 0); }

    /** @return array{ok:bool,error?:string} */
    public static function add(PDO $db, array $shop, int $productId, ?int $variantId, int $qty): array
    {
        if (!Catalog::isVisible($db, $shop, $productId)) return ['ok' => false, 'error' => 'Το προϊόν δεν είναι διαθέσιμο.'];
        $p = Products::find($db, $productId);
        $variants = Products::variants($db, $productId, true);
        if ($variants && !$variantId) return ['ok' => false, 'error' => 'Επιλέξτε παραλλαγή (π.χ. μέγεθος ή χρώμα).'];
        $v = null;
        if ($variantId) {
            $v = db_one($db, "SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND status = 'active'", [$variantId, $productId]);
            if (!$v) return ['ok' => false, 'error' => 'Η παραλλαγή δεν βρέθηκε.'];
        }
        $k = self::key($productId, $variantId);
        $cart = self::raw();
        $newQty = min(self::MAX_QTY, max(1, ($cart[$k]['qty'] ?? 0) + max(1, $qty)));
        $avail = self::available($p, $v);
        if ($avail !== null && $newQty > $avail) {
            if ($avail <= 0) return ['ok' => false, 'error' => 'Το προϊόν έχει εξαντληθεί.'];
            $newQty = $avail;
        }
        $cart[$k] = ['product_id' => $productId, 'variant_id' => $variantId ?: null, 'qty' => $newQty];
        $_SESSION['cart'] = $cart;
        return ['ok' => true];
    }

    public static function update(string $key, int $qty): void
    {
        $cart = self::raw();
        if (!isset($cart[$key])) return;
        if ($qty <= 0) unset($cart[$key]); else $cart[$key]['qty'] = min(self::MAX_QTY, $qty);
        $_SESSION['cart'] = $cart;
    }

    public static function clear(): void { unset($_SESSION['cart']); }

    /** null = unlimited */
    public static function available(array $p, ?array $v): ?int
    {
        if (!(int) $p['manage_stock'] || in_array($p['availability'], ['preorder', 'backorder'], true)) return null;
        if ($p['availability'] === 'out_of_stock') return 0;
        return $v ? (int) $v['stock'] : (int) $p['stock'];
    }

    /** Resolved lines with live prices; drops lines that are no longer purchasable. */
    public static function lines(PDO $db, array $shop): array
    {
        $out = [];
        $changed = false;
        $cart = self::raw();
        foreach ($cart as $k => $l) {
            $p = Products::find($db, (int) $l['product_id']);
            if (!$p || !Catalog::isVisible($db, $shop, (int) $p['id'])) { unset($cart[$k]); $changed = true; continue; }
            $v = $l['variant_id'] ? db_one($db, "SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND status = 'active'", [$l['variant_id'], $p['id']]) : null;
            if ($l['variant_id'] && !$v) { unset($cart[$k]); $changed = true; continue; }
            $unit = Products::effectivePrice($p);
            $regular = (float) $p['price'];
            if ($v && $v['price'] !== null) {
                $regular = (float) $v['price'];
                $unit = ($v['sale_price'] !== null && (float) $v['sale_price'] > 0 && (float) $v['sale_price'] < (float) $v['price']) ? (float) $v['sale_price'] : (float) $v['price'];
            }
            $avail = self::available($p, $v);
            $qty = (int) $l['qty'];
            if ($avail !== null && $qty > $avail) { $qty = max(0, $avail); $changed = true; }
            if ($qty <= 0) { unset($cart[$k]); $changed = true; continue; }
            $cart[$k]['qty'] = $qty;
            $p = Products::withImages($db, [$p])[0];
            $out[] = ['key' => $k, 'product' => $p, 'variant' => $v, 'qty' => $qty, 'unit' => $unit, 'regular' => $regular, 'total' => round($unit * $qty, 2),
                'name' => $p['name'], 'options' => $v ? $v['name'] : '', 'sku' => $v['sku'] ?? $p['sku'], 'max' => $avail];
        }
        if ($changed) $_SESSION['cart'] = $cart;
        return $out;
    }

    public static function count(): int { return array_sum(array_map(fn($l) => (int) $l['qty'], self::raw())); }

    /** Totals incl. shipping by zone/method and payment fee. */
    public static function totals(PDO $db, array $lines, string $zone = 'mainland', string $method = 'courier', string $payment = 'cod', bool $giftWrap = false): array
    {
        $ship = ShopSettings::get($db, 'shipping');
        $pay = ShopSettings::get($db, 'payments');
        $chk = ShopSettings::get($db, 'checkout');
        $tax = ShopSettings::get($db, 'tax');
        $subtotal = round(array_sum(array_column($lines, 'total')), 2);
        $shipping = 0.0;
        $free = (float) $ship['free_threshold'];
        if ($method === 'pickup' && !empty($ship['pickup']['enabled'])) {
            $shipping = 0.0;
        } else {
            $z = $ship['zones'][$zone] ?? null;
            if (!$z || empty($z['enabled'])) {
                $z = null;
                foreach ($ship['zones'] as $zz) { if (!empty($zz['enabled'])) { $z = $zz; break; } }
            }
            $shipping = (float) ($z['cost'] ?? 0);
            if ($free > 0 && $subtotal >= $free) $shipping = 0.0;
        }
        if (!$lines) $shipping = 0.0;
        $fee = (float) ($pay[$payment]['fee'] ?? 0);
        $gift = ($giftWrap && !empty($chk['gift_wrap_enabled'])) ? (float) $chk['gift_wrap_price'] : 0.0;
        $total = round($subtotal + $shipping + $fee + $gift, 2);
        $rate = (float) ($tax['vat_rate'] ?? 24);
        $vat = round($total - $total / (1 + $rate / 100), 2);
        return ['subtotal' => $subtotal, 'shipping' => round($shipping, 2), 'payment_fee' => round($fee, 2), 'gift_wrap' => round($gift, 2), 'total' => $total,
            'vat' => $vat, 'vat_rate' => $rate, 'free_threshold' => $free, 'to_free' => $free > 0 ? max(0, round($free - $subtotal, 2)) : 0,
            'count' => array_sum(array_column($lines, 'qty'))];
    }
}
