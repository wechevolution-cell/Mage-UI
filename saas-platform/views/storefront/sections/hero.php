<?php
/** Hero: one data model, ten art directions (layout.hero). */
$v = $layout['hero'] ?? 'editorial';
$img = !empty($sec['image_id']) ? ($hpMedia[(int) $sec['image_id']] ?? null) : null;
$pool = array_values(array_filter(array_merge($featured, $newest), fn($p) => !empty($p['image'])));
$fallback = fn(int $i) => $pool[$i % max(1, count($pool))]['image'] ?? null;
$main = $img ?: $fallback(0);
$title = trim((string) $sec['title']);
$words = preg_split('/\s+/u', $title);
$lastWord = count($words) > 2 ? array_pop($words) : null;
$titleHtml = e(implode(' ', $words)) . ($lastWord ? ' <em>' . e($lastWord) . '</em>' : '');
$ctas = function (string $cls = '') use ($sec) { ?>
  <div class="hero-ctas <?= $cls ?>">
    <?php if ($sec['cta_text']): ?><a class="btn btn--primary" href="<?= e($sec['cta_url'] ?: '/products/') ?>"><?= e($sec['cta_text']) ?></a><?php endif; ?>
    <?php if ($sec['cta2_text']): ?><a class="btn btn--ghost" href="<?= e($sec['cta2_url'] ?: '/products/') ?>"><?= e($sec['cta2_text']) ?></a><?php endif; ?>
  </div>
<?php };
?>
<section class="hero hero--<?= e($v) ?>" aria-label="Κεντρική προβολή">
<?php switch ($v):
case 'bento': $b = array_slice($pool, 1, 2); ?>
  <div class="wrap hero-bento">
    <div class="tile tile--main">
      <div class="tile-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div>
      <div class="tile-art"><?= sf_img($main, '(max-width: 900px) 60vw, 40vw', '', true) ?></div>
    </div>
    <?php foreach ($b as $i => $p): ?><a class="tile tile--side tile--<?= $i ? 'b' : 'a' ?>" href="<?= e(Products::url($p)) ?>"><span class="eyebrow"><?= $i ? 'Νέο' : 'Προτεινόμενο' ?></span><b><?= e($p['name']) ?></b><?= sf_price($p) ?><span class="tile-img"><?= sf_img($p['image'], '200px') ?></span></a><?php endforeach; ?>
  </div>
<?php break; case 'stack': ?>
  <div class="wrap"><div class="hero-panel">
    <div class="hero-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= $lastWord ? e(implode(' ', $words)) . ' <mark>' . e($lastWord) . '</mark>' : e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div>
    <div class="hero-stack" aria-hidden="true"><?php for ($i = 0; $i < 3; $i++): ?><span class="card card--<?= $i + 1 ?>"><?= sf_img($i === 0 && $img ? $img : $fallback($i), '260px', '', $i === 0) ?></span><?php endfor; ?></div>
    <span class="hero-sticker" aria-hidden="true"><span><?= e($sec['eyebrow'] ?: 'Νέα') ?></span></span>
  </div></div>
<?php break; case 'arch': ?>
  <div class="wrap hero-arch">
    <div class="hero-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= $titleHtml ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div>
    <div class="arch-art"><span class="arch"><?= sf_img($main, '(max-width: 900px) 80vw, 40vw', '', true) ?></span><span class="arch-dot"><?= sf_img($fallback(1), '180px') ?></span></div>
  </div>
<?php break; case 'room': $spots = array_slice($pool, 0, 3); $pos = [[28, 62], [58, 40], [78, 70]]; ?>
  <div class="hero-room">
    <div class="room-img"><?= sf_img($main, '100vw', '', true) ?></div>
    <?php foreach ($spots as $i => $p): ?><div class="spot" style="left:<?= $pos[$i][0] ?>%;top:<?= $pos[$i][1] ?>%"><button type="button" class="spot-dot" aria-label="<?= e($p['name']) ?>" aria-expanded="false"></button><a class="spot-card" href="<?= e(Products::url($p)) ?>"><?= sf_img($p['image'], '80px') ?><span><b><?= e($p['name']) ?></b><?= sf_price($p) ?></span></a></div><?php endforeach; ?>
    <div class="wrap room-copy"><div class="room-panel"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div></div>
  </div>
<?php break; case 'spotlight': ?>
  <div class="wrap hero-spot">
    <?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?>
    <h1><?= $titleHtml ?></h1>
    <div class="spot-art"><span class="glow" aria-hidden="true"></span><?= sf_img($main, '(max-width: 900px) 80vw, 520px', '', true) ?></div>
    <?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas('center'); ?>
  </div>
<?php break; case 'dynamic': ?>
  <div class="hero-dyn">
    <div class="dyn-copy"><div class="dyn-inner"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div></div>
    <div class="dyn-img"><?= sf_img($main, '(max-width: 900px) 100vw, 55vw', '', true) ?></div>
  </div>
  <?php if ($catTree): ?><div class="dyn-marquee" aria-hidden="true"><div><?php for ($r = 0; $r < 2; $r++) foreach ($catTree as $c): ?><span><?= e($c['name']) ?></span><?php endforeach; ?></div></div><?php endif; ?>
<?php break; case 'illustrated': ?>
  <div class="hero-ill">
    <svg class="blob blob--1" viewBox="0 0 200 200" aria-hidden="true"><path d="M43 -58C55 -45 63 -28 66 -10C68 8 64 27 53 41C42 56 25 66 6 70C-14 75 -36 74 -50 62C-64 50 -70 27 -71 5C-72 -17 -68 -39 -55 -52C-42 -65 -21 -70 -2 -68C17 -66 31 -71 43 -58Z" transform="translate(100 100)"/></svg>
    <svg class="blob blob--2" viewBox="0 0 200 200" aria-hidden="true"><path d="M37 -47C48 -37 56 -24 61 -8C65 8 66 27 57 39C48 52 29 58 10 63C-9 68 -28 72 -44 64C-60 56 -73 36 -74 16C-76 -4 -66 -24 -52 -36C-39 -49 -20 -54 -3 -50C13 -47 26 -57 37 -47Z" transform="translate(100 100)"/></svg>
    <div class="wrap ill-grid">
      <div class="hero-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div>
      <div class="ill-art"><span class="ill-circle"><?= sf_img($main, '(max-width: 900px) 70vw, 420px', '', true) ?></span><span class="ill-mini"><?= sf_img($fallback(1), '140px') ?></span></div>
    </div>
    <svg class="wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40c120 30 240 30 360 0s240-30 360 0 240 30 360 0 240-30 360 0v40H0z"/></svg>
  </div>
<?php break; case 'search': ?>
  <div class="wrap hero-search">
    <div class="hero-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?>
      <form class="big-search" action="/search/" role="search" data-live-search><label class="sr" for="hsq">Αναζήτηση κωδικού ή προϊόντος</label><input id="hsq" type="search" name="q" placeholder="Αναζήτηση με κωδικό, SKU, EAN ή όνομα" autocomplete="off"><button class="btn btn--primary"><?= icon('search') ?>Αναζήτηση</button><div class="suggest" hidden></div></form>
      <div class="chips"><?php foreach (array_slice($catTree, 0, 6) as $c): ?><a class="chip" href="<?= e(Categories::url($c)) ?>"><?= e($c['name']) ?></a><?php endforeach; ?></div>
    </div>
    <div class="search-art"><?= sf_img($main, '(max-width: 900px) 90vw, 40vw', '', true) ?></div>
  </div>
<?php break; case 'clinical': ?>
  <div class="wrap"><div class="hero-clin">
    <div class="hero-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= e($title) ?></h1><?php if ($sec['subtitle']): ?><p><?= e($sec['subtitle']) ?></p><?php endif; ?>
      <form class="big-search" action="/search/" role="search" data-live-search><label class="sr" for="hcq">Αναζήτηση</label><?= icon('search') ?><input id="hcq" type="search" name="q" placeholder="Αναζητήστε προϊόν ή ανάγκη…" autocomplete="off"><button class="btn btn--primary">Αναζήτηση</button><div class="suggest" hidden></div></form>
      <?php $ctas(); ?>
    </div>
    <div class="clin-art"><?= sf_img($main, '(max-width: 900px) 90vw, 40vw', '', true) ?></div>
    <?php if ($catTree): ?><div class="needs"><?php foreach (array_slice($catTree, 0, 4) as $c): ?><a href="<?= e(Categories::url($c)) ?>"><?= icon('leaf') ?><span><?= e($c['name']) ?></span><?= icon('chevron-right') ?></a><?php endforeach; ?></div><?php endif; ?>
  </div></div>
<?php break; default: /* editorial */ ?>
  <div class="wrap hero-ed">
    <div class="hero-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h1><?= $titleHtml ?></h1><?php if ($sec['subtitle']): ?><p class="lede"><?= e($sec['subtitle']) ?></p><?php endif; ?><?php $ctas(); ?></div>
    <figure class="hero-media"><?= sf_img($main, '(max-width: 900px) 100vw, 50vw', '', true) ?><?php if (!$img && !empty($pool[0])): ?><figcaption><b><?= e($pool[0]['name']) ?></b><?= sf_price($pool[0]) ?></figcaption><?php endif; ?></figure>
  </div>
<?php endswitch; ?>
</section>
