<?php if ($items): $shelf = in_array($layout['hero'] ?? '', ['stack', 'illustrated'], true); ?>
<section class="sec sec-products sec-<?= e($id) ?>" aria-labelledby="h-<?= e($id) ?>"><div class="wrap">
  <div class="sec-head"><h2 id="h-<?= e($id) ?>"><?= e($title) ?></h2><div class="sec-tools"><?php if ($shelf): ?><button class="icon-btn" type="button" data-shelf="-1" aria-label="Προηγούμενα"><?= icon('chevron-left') ?></button><button class="icon-btn" type="button" data-shelf="1" aria-label="Επόμενα"><?= icon('chevron-right') ?></button><?php endif; ?><a class="sec-link" href="<?= e($more) ?>">Όλα <?= icon('arrow-right') ?></a></div></div>
  <div class="<?= $shelf ? 'shelf' : 'grid-products' ?>"><?php foreach ($items as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach; ?></div>
</div></section>
<?php endif; ?>
