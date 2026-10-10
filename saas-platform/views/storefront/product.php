<?php
$imgs = $p['images'];
$main = $imgs[0] ?? null;
$hasVar = !empty($p['variants']);
$out = !Products::inStock($p);
$ship = $shipping;
$pay = ShopSettings::get($db, 'payments');
?>
<div class="pdp-wrap">
  <div class="wrap">
    <?= partial('breadcrumbs', ['crumbs' => $crumbs]) ?>
    <div class="pdp" data-product="<?= (int) $p['id'] ?>">
      <section class="pdp-gallery" aria-label="Εικόνες προϊόντος">
        <div class="gal-main" data-zoom><?= sf_img($main, '(max-width: 900px) 100vw, 55vw', 'gal-img', true, $p['name']) ?><?php if (Products::onSale($p)): ?><span class="pc-badge pc-badge--sale">-<?= (int) round((1 - Products::effectivePrice($p) / (float) $p['price']) * 100) ?>%</span><?php endif; ?></div>
        <?php if (count($imgs) > 1): ?><div class="gal-thumbs" role="list"><?php foreach ($imgs as $i => $m): ?><button type="button" role="listitem" class="gal-thumb" data-src="<?= e(media_url($m)) ?>" data-srcset="<?= e(implode(', ', array_filter([isset(jd($m['variants'])[800]) ? '/uploads/' . jd($m['variants'])[800] . ' 800w' : null, isset(jd($m['variants'])[1200]) ? '/uploads/' . jd($m['variants'])[1200] . ' 1200w' : null]))) ?>" aria-label="Εικόνα <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><?= media_img($m, '96px') ?></button><?php endforeach; ?></div><?php endif; ?>
        <?php if (count($imgs) > 1): ?><div class="gal-stack"><?php foreach (array_slice($imgs, 1, 4) as $m): ?><div class="gal-extra"><?= media_img($m, '(max-width: 900px) 100vw, 28vw') ?></div><?php endforeach; ?></div><?php endif; ?>
      </section>
      <section class="pdp-info">
        <?php if ($p['brand']): ?><span class="pdp-brand"><?= e($p['brand']) ?></span><?php endif; ?>
        <h1 class="pdp-title"><?= e($p['name']) ?></h1>
        <div class="pdp-meta"><?php if ($p['sku']): ?><span>Κωδικός: <b data-sku><?= e($p['sku']) ?></b></span><?php endif; ?><?= sf_stock_label($p) ?></div>
        <div class="pdp-price" data-price><?= sf_price($p) ?></div>
        <span class="pdp-vat">Η τιμή περιλαμβάνει ΦΠΑ</span>
        <?php if (trim(strip_tags((string) $p['short_description'])) !== ''): ?><div class="pdp-short prose"><?= Sanitizer::html($p['short_description']) ?></div><?php endif; ?>
        <form class="pdp-buy" method="post" action="/cart/add/" data-ajax-cart data-variants="<?= e(je($variantsJs)) ?>" data-base-price="<?= e((string) $p['price']) ?>" data-base-sale="<?= e((string) ($p['sale_price'] ?? '')) ?>">
          <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="variant_id" value="">
          <?php foreach ($options as $optName => $vals): ?>
            <fieldset class="opt"><legend><?= e($optName) ?>: <b data-opt-label></b></legend><div class="opt-vals"><?php foreach ($vals as $val): ?><label class="opt-val"><input type="radio" name="opt[<?= e($optName) ?>]" value="<?= e($val) ?>" data-opt="<?= e($optName) ?>" required><span><?= e($val) ?></span></label><?php endforeach; ?></div></fieldset>
          <?php endforeach; ?>
          <div class="buy-row">
            <div class="qty" data-qty><button type="button" data-step="-1" aria-label="Μείωση ποσότητας"><?= icon('minus') ?></button><label class="sr" for="qty">Ποσότητα</label><input id="qty" type="number" name="qty" value="1" min="1" max="99" inputmode="numeric"><button type="button" data-step="1" aria-label="Αύξηση ποσότητας"><?= icon('plus') ?></button></div>
            <button class="btn btn--primary btn--buy" data-buy<?= $out ? ' disabled' : '' ?>><?= icon('bag') ?><span><?= $out ? 'Εξαντλήθηκε' : 'Προσθήκη στο καλάθι' ?></span></button>
            <button class="icon-btn pdp-wish" type="button" data-wish="<?= (int) $p['id'] ?>" aria-pressed="false" aria-label="Προσθήκη στα αγαπημένα"><?= icon('heart') ?></button>
          </div>
          <p class="form-msg" role="status"></p>
        </form>
        <ul class="pdp-perks">
          <li><?= icon('truck') ?><span><b>Αποστολή <?= e($ship['delivery_text']) ?></b><?= (float) $ship['free_threshold'] > 0 ? 'Δωρεάν για αγορές άνω των ' . money((float) $ship['free_threshold']) : '' ?></span></li>
          <li><?= icon('card') ?><span><b>Πληρωμή</b><?= e(implode(' ή ', array_filter([$pay['cod']['enabled'] ? $pay['cod']['label'] : null, $pay['bank']['enabled'] ? $pay['bank']['label'] : null]))) ?></span></li>
          <li><?= icon('return') ?><span><b>Επιστροφές</b><a href="/returns/">Πολιτική επιστροφών</a></span></li>
        </ul>
      </section>
    </div>

    <div class="pdp-details">
      <?php if (trim(strip_tags((string) $p['description'])) !== ''): ?><details class="acc" open><summary>Περιγραφή<?= icon('plus', 'ic acc-ic') ?></summary><div class="acc-body prose"><?= Sanitizer::html($p['description']) ?></div></details><?php endif; ?>
      <?php if ($p['attributes'] || $p['weight'] || $p['barcode']): ?><details class="acc" open><summary>Χαρακτηριστικά<?= icon('plus', 'ic acc-ic') ?></summary><div class="acc-body"><table class="specs"><tbody>
        <?php if ($p['brand']): ?><tr><th scope="row">Μάρκα</th><td><?= e($p['brand']) ?></td></tr><?php endif; ?>
        <?php foreach ($p['attributes'] as $a): ?><tr><th scope="row"><?= e($a['name']) ?></th><td><?= e($a['value']) ?></td></tr><?php endforeach; ?>
        <?php if ($p['weight']): ?><tr><th scope="row">Βάρος</th><td><?= e(rtrim(rtrim(number_format((float) $p['weight'], 3, ',', ''), '0'), ',')) ?> kg</td></tr><?php endif; ?>
        <?php if ($p['length'] && $p['width'] && $p['height']): ?><tr><th scope="row">Διαστάσεις</th><td><?= (float) $p['length'] ?> × <?= (float) $p['width'] ?> × <?= (float) $p['height'] ?> cm</td></tr><?php endif; ?>
        <?php if ($p['barcode']): ?><tr><th scope="row">EAN</th><td><?= e($p['barcode']) ?></td></tr><?php endif; ?>
      </tbody></table></div></details><?php endif; ?>
      <details class="acc"><summary>Αποστολή & επιστροφές<?= icon('plus', 'ic acc-ic') ?></summary><div class="acc-body prose"><ul><?php foreach ($ship['zones'] as $z): if (!$z['enabled']) continue; ?><li><?= e($z['label']) ?>: <?= money((float) $z['cost']) ?></li><?php endforeach; ?><?php if ((float) $ship['free_threshold'] > 0): ?><li>Δωρεάν αποστολή άνω των <?= money((float) $ship['free_threshold']) ?></li><?php endif; ?><?php if ($ship['pickup']['enabled']): ?><li><?= e($ship['pickup']['label']) ?> (δωρεάν)</li><?php endif; ?></ul><p><a href="/shipping/">Αποστολές</a> · <a href="/returns/">Επιστροφές</a></p></div></details>
      <?= partial('faq', ['faq' => $faq]) ?>
    </div>

    <?php if ($related): ?><section class="sec sec-related" aria-labelledby="h-rel"><div class="sec-head"><h2 id="h-rel">Μπορεί να σας αρέσουν</h2></div><div class="grid-products"><?php foreach ($related as $rp): ?><?= partial('product-card', ['p' => $rp]) ?><?php endforeach; ?></div></section><?php endif; ?>
  </div>
  <div class="sticky-buy" data-sticky-buy aria-hidden="true"><div class="wrap"><span class="sb-title"><?= e($p['name']) ?></span><span class="sb-price"><?= money(Products::effectivePrice($p)) ?></span><button class="btn btn--primary" type="button" data-sticky-add<?= $out ? ' disabled' : '' ?>><?= $hasVar ? 'Επιλογή' : 'Στο καλάθι' ?></button></div></div>
</div>
