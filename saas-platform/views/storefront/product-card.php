<?php
/** @var array $p */
$card = $layout['card'] ?? 'minimal';
$rich = in_array($card, ['compact', 'spec', 'clean', 'bold'], true);
$attrs = in_array($card, ['compact', 'spec'], true) ? array_slice(Products::attributes($db, (int) $p['id']), 0, 3) : [];
$url = Products::url($p);
$sale = Products::onSale($p);
$isNew = strtotime((string) $p['created_at']) > strtotime('-21 days');
$out = !Products::inStock($p);
?>
<article class="pc<?= $out ? ' pc--out' : '' ?>" data-pid="<?= (int) $p['id'] ?>">
  <a class="pc-media" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
    <?= sf_img($p['image'] ?? null, '(max-width: 700px) 50vw, (max-width: 1100px) 33vw, 25vw', 'pc-img') ?>
    <?php if (!empty($p['image2'])): ?><?= media_img($p['image2'], '(max-width: 700px) 50vw, 25vw', 'pc-img2') ?><?php endif; ?>
  </a>
  <div class="pc-badges"><?php if ($sale): ?><span class="pc-badge pc-badge--sale">-<?= (int) round((1 - Products::effectivePrice($p) / (float) $p['price']) * 100) ?>%</span><?php endif; ?><?php if ($isNew): ?><span class="pc-badge pc-badge--new">Νέο</span><?php endif; ?><?php if ($out): ?><span class="pc-badge pc-badge--out">Εξαντλήθηκε</span><?php endif; ?></div>
  <button class="pc-wish" type="button" data-wish="<?= (int) $p['id'] ?>" aria-pressed="false" aria-label="Προσθήκη στα αγαπημένα: <?= e($p['name']) ?>"><?= icon('heart') ?></button>
  <div class="pc-body">
    <?php if ($rich && $p['brand']): ?><span class="pc-brand"><?= e($p['brand']) ?></span><?php endif; ?>
    <h3 class="pc-title"><a href="<?= e($url) ?>"><?= e($p['name']) ?></a></h3>
    <?php if ($attrs): ?><ul class="pc-specs"><?php foreach ($attrs as $a): ?><li><?= e($a['value']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($rich): ?><?= sf_stock_label($p) ?><?php endif; ?>
    <div class="pc-foot">
      <?= sf_price($p) ?>
      <?php if (!$out): ?>
        <?php if (!empty($p['has_variants'])): ?><a class="pc-add" href="<?= e($url) ?>" aria-label="Επιλογές για <?= e($p['name']) ?>"><?= icon('plus') ?><span>Επιλογές</span></a>
        <?php else: ?><form class="pc-addform" method="post" action="/cart/add/" data-ajax-cart><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="qty" value="1"><button class="pc-add" aria-label="Προσθήκη στο καλάθι: <?= e($p['name']) ?>"><?= icon('plus') ?><span>Στο καλάθι</span></button></form><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</article>
