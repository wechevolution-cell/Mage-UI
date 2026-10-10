<?php if (!empty($sec['items'])): ?>
<section class="sec sec-brands" aria-labelledby="h-brands"><div class="wrap">
  <div class="sec-head"><h2 id="h-brands"><?= e($sec['title'] ?: 'Μάρκες') ?></h2></div>
  <div class="brands"><?php foreach ($sec['items'] as $b): $m = $b['image_id'] ? ($hpMedia[(int) $b['image_id']] ?? db_one($db, 'SELECT * FROM media WHERE id = ?', [(int) $b['image_id']])) : null; ?>
    <?= $b['url'] ? '<a class="brand" href="' . e($b['url']) . '">' : '<span class="brand">' ?><?= $m ? media_img($m, '160px', '', false, $b['name']) : '<b>' . e($b['name']) . '</b>' ?><?= $b['url'] ? '</a>' : '</span>' ?>
  <?php endforeach; ?></div>
</div></section>
<?php endif; ?>
