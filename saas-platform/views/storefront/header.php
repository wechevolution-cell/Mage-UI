<?php
$h = $layout['header'] ?? 'classic';
$family = match ($h) { 'centered', 'split', 'dark' => 'centered', 'market', 'utility', 'clean' => 'market', 'pill', 'playful' => 'pill', default => 'classic' };
$logoHtml = $logo ? media_img($logo, '220px', 'logo-img', true, $storeName) : '<span class="logo-text">' . e($logoText) . '</span>';
$actions = function () use ($cartCount, $customer, $family) { ?>
  <div class="hdr-actions">
    <?php if ($family !== 'market'): ?><button class="icon-btn" type="button" data-search-open aria-label="Αναζήτηση"><?= icon('search') ?></button><?php endif; ?>
    <a class="icon-btn hide-sm" href="<?= $customer ? '/account/' : '/account/login/' ?>" aria-label="Λογαριασμός"><?= icon('user') ?><span class="lbl">Λογαριασμός</span></a>
    <a class="icon-btn hide-sm" href="/wishlist/" aria-label="Αγαπημένα"><?= icon('heart') ?><span class="lbl">Αγαπημένα</span><span class="badge-count" data-wish-count hidden>0</span></a>
    <button class="icon-btn cart-btn" type="button" data-cart-open aria-label="Καλάθι"><?= icon('bag') ?><span class="lbl">Καλάθι</span><span class="badge-count" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= (int) $cartCount ?></span></button>
  </div>
<?php };
$navList = function (array $items, string $class = 'nav') { ?>
  <ul class="<?= $class ?>">
  <?php foreach ($items as $it): ?>
    <li class="<?= $it['children'] ? 'has-sub' : '' ?>"><?= sf_link($it) ?>
      <?php if ($it['children']): ?><div class="sub"><ul><?php foreach ($it['children'] as $c): ?><li><?= sf_link($c) ?><?php if ($c['children']): ?><ul><?php foreach ($c['children'] as $cc): ?><li><?= sf_link($cc) ?></li><?php endforeach; ?></ul><?php endif; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
<?php };
?>
<header class="hdr hdr--<?= e($family) ?>" data-header>
<?php if ($family === 'market'): ?>
  <div class="hdr-util"><div class="wrap"><span><?= icon('truck') ?><?= !empty($shipping['free_threshold']) && (float) $shipping['free_threshold'] > 0 ? 'Δωρεάν αποστολή άνω των ' . money((float) $shipping['free_threshold']) : e($shipping['delivery_text'] ?? '') ?></span><span class="hide-sm"><?php if (!empty($store['phone'])): ?><?= icon('phone') ?><?= e($store['phone']) ?><?php endif; ?><?php if (!empty($store['hours'])): ?> · <?= e($store['hours']) ?><?php endif; ?></span></div></div>
  <div class="hdr-main"><div class="wrap">
    <button class="icon-btn menu-btn" type="button" data-menu-open aria-label="Μενού"><?= icon('menu') ?></button>
    <a class="logo" href="/" aria-label="<?= e($storeName) ?> — αρχική"><?= $logoHtml ?></a>
    <form class="hdr-search" action="/search/" role="search" data-live-search>
      <label class="sr" for="hq">Αναζήτηση</label><input id="hq" type="search" name="q" placeholder="<?= $h === 'utility' ? 'Κωδικός, SKU ή όνομα προϊόντος…' : 'Αναζήτηση προϊόντων…' ?>" autocomplete="off" value="<?= e($searchQuery ?? '') ?>">
      <button aria-label="Αναζήτηση"><?= icon('search') ?></button><div class="suggest" hidden></div>
    </form>
    <?php $actions(); ?>
  </div></div>
  <nav class="hdr-cats" aria-label="Κατηγορίες"><div class="wrap">
    <button class="allcats" type="button" data-mega-toggle aria-expanded="false"><?= icon('menu') ?>Όλες οι κατηγορίες</button>
    <?php $navList($menu, 'nav nav--bar'); ?>
  </div>
  <div class="mega" data-mega hidden><div class="wrap mega-grid">
    <?php foreach (array_slice($catTree, 0, 8) as $c): ?><div class="mega-col"><a class="mega-h" href="<?= e(Categories::url($c)) ?>"><?= e($c['name']) ?></a><?php if ($c['children']): ?><ul><?php foreach (array_slice($c['children'], 0, 8) as $cc): ?><li><a href="<?= e(Categories::url($cc)) ?>"><?= e($cc['name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div><?php endforeach; ?>
  </div></div></nav>
<?php elseif ($family === 'centered'): ?>
  <div class="hdr-main"><div class="wrap">
    <div class="hdr-left"><button class="icon-btn menu-btn menu-btn--always" type="button" data-menu-open aria-label="Μενού"><?= icon('menu') ?><span class="lbl">Μενού</span></button>
      <?php if ($h === 'split'): ?><nav class="hide-md" aria-label="Κύριο μενού"><?php $navList(array_slice($menu, 0, (int) ceil(count($menu) / 2))); ?></nav><?php endif; ?></div>
    <a class="logo" href="/" aria-label="<?= e($storeName) ?> — αρχική"><?= $logoHtml ?></a>
    <div class="hdr-right"><?php if ($h === 'split'): ?><nav class="hide-md" aria-label="Μενού"><?php $navList(array_slice($menu, (int) ceil(count($menu) / 2))); ?></nav><?php endif; ?><?php $actions(); ?></div>
  </div></div>
  <?php if ($h === 'dark'): ?><nav class="hdr-row hide-md" aria-label="Κύριο μενού"><div class="wrap"><?php $navList($menu, 'nav nav--center'); ?></div></nav><?php endif; ?>
<?php elseif ($family === 'pill'): ?>
  <div class="wrap"><div class="pill">
    <button class="icon-btn menu-btn" type="button" data-menu-open aria-label="Μενού"><?= icon('menu') ?></button>
    <a class="logo" href="/" aria-label="<?= e($storeName) ?> — αρχική"><?= $logoHtml ?></a>
    <nav class="hide-md" aria-label="Κύριο μενού"><?php $navList($menu); ?></nav>
    <?php $actions(); ?>
  </div></div>
<?php else: ?>
  <div class="hdr-main"><div class="wrap">
    <button class="icon-btn menu-btn" type="button" data-menu-open aria-label="Μενού"><?= icon('menu') ?></button>
    <a class="logo" href="/" aria-label="<?= e($storeName) ?> — αρχική"><?= $logoHtml ?></a>
    <nav class="hide-md hdr-nav" aria-label="Κύριο μενού"><?php $navList($menu); ?></nav>
    <?php $actions(); ?>
  </div></div>
<?php endif; ?>
</header>

<div class="offcanvas" data-menu hidden>
  <div class="offcanvas-panel" role="dialog" aria-modal="true" aria-label="Μενού">
    <div class="offcanvas-head"><a class="logo" href="/"><?= $logoHtml ?></a><button class="icon-btn" type="button" data-menu-close aria-label="Κλείσιμο"><?= icon('close') ?></button></div>
    <form class="offcanvas-search" action="/search/" role="search"><label class="sr" for="mq">Αναζήτηση</label><input id="mq" type="search" name="q" placeholder="Αναζήτηση…"><button aria-label="Αναζήτηση"><?= icon('search') ?></button></form>
    <nav aria-label="Μενού κινητού"><ul class="mnav">
      <?php foreach ($menu as $it): ?><li><?php if ($it['children']): ?><details><summary><?= e($it['label']) ?><?= icon('chevron-down') ?></summary><ul><li><?= sf_link(['href' => $it['href'], 'label' => 'Όλα: ' . $it['label']]) ?></li><?php foreach ($it['children'] as $c): ?><li><?= sf_link($c) ?></li><?php endforeach; ?></ul></details><?php else: ?><?= sf_link($it) ?><?php endif; ?></li><?php endforeach; ?>
    </ul></nav>
    <div class="offcanvas-foot"><a href="<?= $customer ? '/account/' : '/account/login/' ?>"><?= icon('user') ?><?= $customer ? 'Ο λογαριασμός μου' : 'Σύνδεση / εγγραφή' ?></a><a href="/wishlist/"><?= icon('heart') ?>Αγαπημένα</a><?php if (!empty($store['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $store['phone'])) ?>"><?= icon('phone') ?><?= e($store['phone']) ?></a><?php endif; ?></div>
  </div>
</div>

<div class="search-overlay" data-search hidden>
  <div class="search-panel" role="dialog" aria-modal="true" aria-label="Αναζήτηση">
    <form action="/search/" role="search" data-live-search class="search-form"><label class="sr" for="sq">Αναζήτηση προϊόντων</label><?= icon('search') ?><input id="sq" type="search" name="q" placeholder="Τι ψάχνετε;" autocomplete="off"><button type="button" class="icon-btn" data-search-close aria-label="Κλείσιμο"><?= icon('close') ?></button><div class="suggest" hidden></div></form>
  </div>
</div>
