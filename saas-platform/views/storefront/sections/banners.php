<?php $items = array_values(array_filter($sec['items'] ?? [], fn($b) => $b['title'] !== '' || $b['image_id'])); if ($items): ?>
<section class="sec sec-banners" aria-label="Προωθήσεις"><div class="wrap banners banners--<?= count($items) ?>">
<?php foreach ($items as $i => $b): $m = $b['image_id'] ? ($hpMedia[(int) $b['image_id']] ?? null) : null; ?>
  <a class="banner banner--<?= $i ?>" href="<?= e($b['url'] ?: '/products/') ?>"><span class="banner-img"><?= sf_img($m, '(max-width: 700px) 100vw, 33vw') ?></span><span class="banner-copy"><b><?= e($b['title']) ?></b><?php if ($b['text']): ?><span><?= e($b['text']) ?></span><?php endif; ?><?php if ($b['cta']): ?><span class="banner-cta"><?= e($b['cta']) ?> <?= icon('arrow-right') ?></span><?php endif; ?></span></a>
<?php endforeach; ?>
</div></section>
<?php endif; ?>
