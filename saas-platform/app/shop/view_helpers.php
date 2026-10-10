<?php
declare(strict_types=1);

/** Small storefront view helpers shared by all templates. */
function sf_price(array $p, ?array $variant = null): string
{
    $reg = (float) $p['price'];
    $eff = Products::effectivePrice($p);
    if ($variant && $variant['price'] !== null) {
        $reg = (float) $variant['price'];
        $eff = ($variant['sale_price'] !== null && (float) $variant['sale_price'] > 0 && (float) $variant['sale_price'] < $reg) ? (float) $variant['sale_price'] : $reg;
    }
    $h = '<span class="price' . ($eff < $reg ? ' price--sale' : '') . '"><span class="price-now">' . money($eff) . '</span>';
    if ($eff < $reg) $h .= ' <s class="price-was">' . money($reg) . '</s> <span class="price-off">-' . (int) round((1 - $eff / $reg) * 100) . '%</span>';
    return $h . '</span>';
}

function sf_img(?array $m, string $sizes = '(max-width: 700px) 50vw, 25vw', string $class = '', bool $eager = false, string $alt = ''): string
{
    if (!$m) return '<span class="ph' . ($class ? ' ' . e($class) : '') . '" aria-hidden="true"></span>';
    return media_img($m, $sizes, $class, $eager, $alt);
}

function sf_link(array $item): string
{
    return '<a href="' . e($item['href']) . '"' . (!empty($item['new_tab']) ? ' target="_blank" rel="noopener"' : '') . (req_path() === $item['href'] ? ' aria-current="page"' : '') . '>' . e($item['label']) . '</a>';
}

function sf_stock_label(array $p): string
{
    if (!Products::inStock($p)) return '<span class="stock stock--out">Εξαντλημένο</span>';
    if ($p['availability'] === 'preorder') return '<span class="stock stock--pre">Προπαραγγελία</span>';
    if ($p['availability'] === 'backorder') return '<span class="stock stock--pre">Κατόπιν παραγγελίας</span>';
    if ((int) $p['manage_stock'] && (int) $p['stock'] > 0 && (int) $p['stock'] <= 3) return '<span class="stock stock--low">Τελευταία τεμάχια</span>';
    return '<span class="stock stock--in">Άμεσα διαθέσιμο</span>';
}

function sf_is(string $layoutKey, array $values): bool
{
    return in_array(Theme::opt('layout.' . $layoutKey), $values, true);
}

function sf_qs(array $changes): string { return query_with($changes + ['page' => null]); }

/** Hidden inputs that preserve the current query string (nested arrays supported). */
function sf_hidden_inputs(array $exclude = []): string
{
    $out = '';
    $walk = function ($val, string $name) use (&$walk, &$out) {
        if (is_array($val)) { foreach ($val as $k => $v) $walk($v, $name . (is_int($k) ? '[]' : '[' . $k . ']')); return; }
        $out .= '<input type="hidden" name="' . e($name) . '" value="' . e((string) $val) . '">';
    };
    foreach ($_GET as $k => $v) { if (!in_array($k, $exclude, true) && is_string($k)) $walk($v, $k); }
    return $out;
}
