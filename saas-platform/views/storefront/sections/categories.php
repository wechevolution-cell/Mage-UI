<?php if ($homeCats): ?>
<section class="sec sec-cats" aria-labelledby="h-cats"><div class="wrap">
  <div class="sec-head"><h2 id="h-cats"><?= e($sec['title'] ?: 'Κατηγορίες') ?></h2><a class="sec-link" href="/products/">Όλα τα προϊόντα <?= icon('arrow-right') ?></a></div>
  <div class="cat-grid">
  <?php foreach ($homeCats as $i => $c): ?>
    <a class="cat-tile cat-tile--<?= $i % 6 ?>" href="<?= e(Categories::url($c)) ?>">
      <span class="cat-img"><?= sf_img($c['image'], '(max-width: 700px) 50vw, 20vw') ?></span>
      <span class="cat-name"><?= e($c['name']) ?></span><span class="cat-count"><?= (int) $c['count'] ?> προϊόντα</span><span class="cat-go"><?= icon('arrow-right') ?></span>
    </a>
  <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>
