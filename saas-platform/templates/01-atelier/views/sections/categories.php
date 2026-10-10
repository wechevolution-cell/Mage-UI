<?php /* Atelier override: editorial category index with cursor-follow preview. */ if ($homeCats): ?>
<section class="sec sec-cats" aria-labelledby="h-cats"><div class="wrap">
  <div class="sec-head"><h2 id="h-cats"><?= e($sec['title'] ?: 'Κατηγορίες') ?></h2><span class="eyebrow">Εξερευνήστε τη συλλογή</span></div>
  <ul class="cat-index">
  <?php foreach ($homeCats as $c): ?><li><a href="<?= e(Categories::url($c)) ?>"<?= $c['image'] ? ' data-peek="' . e(media_url($c['image'], 400)) . '"' : '' ?>><span class="nm"><?= e($c['name']) ?></span><span class="ct"><?= (int) $c['count'] ?> κομμάτια</span></a></li><?php endforeach; ?>
  </ul>
</div><div class="peek" aria-hidden="true"><img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" width="220" height="275"></div></section>
<?php endif; ?>
