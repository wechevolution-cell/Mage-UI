<?php $pages = db_all($db, "SELECT title, slug FROM pages WHERE status = 'published' AND deleted_at IS NULL AND show_in_footer = 1 ORDER BY sort"); ?>
<footer class="ftr">
  <div class="wrap ftr-grid">
    <div class="ftr-brand">
      <a class="logo" href="/"><?= $logo ? media_img($logo, '200px', 'logo-img', false, $storeName) : '<span class="logo-text">' . e($logoText) . '</span>' ?></a>
      <?php if (!empty($store['about_short'])): ?><p><?= e($store['about_short']) ?></p><?php endif; ?>
      <ul class="ftr-contact">
        <?php if (!empty($store['address'])): ?><li><?= icon('pin') ?><span><?= e(trim($store['address'] . ', ' . ($store['postcode'] ?? '') . ' ' . ($store['city'] ?? ''), ', ')) ?></span></li><?php endif; ?>
        <?php if (!empty($store['phone'])): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/\s+/', '', $store['phone'])) ?>"><?= e($store['phone']) ?></a></li><?php endif; ?>
        <?php if (!empty($store['email'])): ?><li><?= icon('mail') ?><a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a></li><?php endif; ?>
        <?php if (!empty($store['hours'])): ?><li><?= icon('clock') ?><span><?= e($store['hours']) ?></span></li><?php endif; ?>
      </ul>
      <?php $soc = array_filter($store['socials'] ?? []); if ($soc): ?><div class="ftr-social"><?php foreach ($soc as $k => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><?= icon($k) ?></a><?php endforeach; ?></div><?php endif; ?>
    </div>
    <nav class="ftr-col" aria-label="Κατηγορίες"><h2>Κατάστημα</h2><ul><li><a href="/products/">Όλα τα προϊόντα</a></li><?php foreach (array_slice($catTree, 0, 6) as $c): ?><li><a href="<?= e(Categories::url($c)) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?></ul></nav>
    <nav class="ftr-col" aria-label="Εξυπηρέτηση"><h2>Εξυπηρέτηση</h2><ul><?php foreach ($footerMenu2 ?: [] as $it): ?><li><?= sf_link($it) ?></li><?php endforeach; ?><?php if ($hasBlog): ?><li><a href="/blog/">Blog</a></li><?php endif; ?></ul></nav>
    <nav class="ftr-col" aria-label="Πληροφορίες"><h2>Πληροφορίες</h2><ul><?php foreach ($footerMenu ?: [] as $it): ?><li><?= sf_link($it) ?></li><?php endforeach; ?><li><button class="link-btn" type="button" data-cookie-settings>Ρυθμίσεις cookies</button></li></ul></nav>
  </div>
  <div class="ftr-bottom"><div class="wrap"><span>© <?= date('Y') ?> <?= e($store['company_name'] ?: $storeName) ?><?= !empty($store['vat_number']) ? ' · ΑΦΜ ' . e($store['vat_number']) : '' ?></span><span class="ftr-pay"><?= icon('card') ?>Αντικαταβολή · Τραπεζική κατάθεση</span><span class="ftr-powered">Powered by wechevo e-shop</span></div></div>
</footer>
