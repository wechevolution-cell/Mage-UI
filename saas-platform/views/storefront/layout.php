<?php
/** Storefront document shell shared by every template. */
$L = $layout;
$t = $tokens;
$bodyClass = 't-' . $tpl . ' hdr-' . ($L['header'] ?? 'classic') . ' card-' . ($L['card'] ?? 'minimal') . ' pdp-' . ($L['product'] ?? 'split') . (!empty($L['mobile_tabbar']) ? ' has-tabbar' : '') . ' v-' . e(str_replace('/', '-', $view));
$fontMap = ['serif' => 'Georgia, "Times New Roman", serif', 'sans' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans", sans-serif', 'rounded' => 'ui-rounded, "Arial Rounded MT Bold", system-ui, sans-serif'];
$ga = $seoCfg['ga4_id'] ?? '';
?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?= Seo::head() ?>
<?php if (!empty($seoCfg['gsc_verification'])): ?><meta name="google-site-verification" content="<?= e($seoCfg['gsc_verification']) ?>">
<?php endif; ?>
<meta name="theme-color" content="<?= e($t['background'] ?? '#ffffff') ?>">
<?php if ($favicon): ?><link rel="icon" href="<?= e(media_url($favicon, 400)) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= Theme::asset('fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/store.css') ?>">
<link rel="stylesheet" href="<?= Theme::asset('theme.css') ?>">
<style>:root{<?php foreach (['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'button_bg', 'button_text', 'link'] as $k): if (isset($t[$k])): ?>--c-<?= str_replace('_', '-', $k) ?>:<?= e($t[$k]) ?>;<?php endif; endforeach; ?><?php if ($fontChoice !== 'default' && isset($fontMap[$fontChoice])): ?>--f-display:<?= $fontMap[$fontChoice] ?>;--f-body:<?= $fontMap[$fontChoice] ?>;<?php endif; ?>}</style>
<link rel="alternate" type="application/rss+xml" title="<?= e($storeName) ?> blog" href="/feed/blog.xml">
</head>
<body class="<?= $bodyClass ?>" data-cart-mode="<?= e($L['cart'] ?? (in_array($L['card'] ?? '', ['compact', 'spec', 'bold'], true) ? 'toast' : 'drawer')) ?>"<?= $ga ? ' data-ga="' . e($ga) . '"' : '' ?>>
<a class="skip-link" href="#main">Μετάβαση στο περιεχόμενο</a>
<?php if ($preview): ?><div class="preview-bar" role="status">Προεπισκόπηση template <b><?= e(Theme::opt('name')) ?></b> — δεν έχει εφαρμοστεί στο κατάστημα. <a href="/?__exit_preview=1">Έξοδος</a></div><?php endif; ?>
<?= partial('header') ?>
<main id="main" class="main">
<?php $fl = flashes(); if ($fl): ?><div class="wrap flashes"><?php foreach ($fl as $f): ?><div class="notice notice--<?= e($f['type']) ?>" role="<?= $f['type'] === 'bad' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?></div><?php endif; ?>
<?= $content ?>
</main>
<?= partial('footer') ?>
<?= partial('cart-drawer') ?>
<?php if (!empty($L['mobile_tabbar'])): ?><?= partial('tabbar') ?><?php endif; ?>
<?php if ($popup): ?><?= partial('popup', ['popup' => $popup]) ?><?php endif; ?>
<?= partial('cookie') ?>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.SHOP={csrf:<?= ej(csrf_token()) ?>,loggedIn:<?= $customer ? 'true' : 'false' ?>,money:"€"};</script>
<script src="<?= asset('js/store.js') ?>" defer></script>
<?php if (is_file(ROOT . '/templates/' . $tpl . '/assets/theme.js')): ?><script src="<?= Theme::asset('theme.js') ?>" defer></script><?php endif; ?>
</body>
</html>
