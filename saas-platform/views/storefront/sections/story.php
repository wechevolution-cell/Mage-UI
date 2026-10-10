<?php if ($sec['title'] !== '' || $sec['text'] !== ''): $m = $sec['image_id'] ? ($hpMedia[(int) $sec['image_id']] ?? null) : null; ?>
<section class="sec sec-story" aria-labelledby="h-story"><div class="wrap story">
  <div class="story-img"><?= sf_img($m ?: ($featured[0]['image'] ?? null), '(max-width: 900px) 100vw, 45vw') ?></div>
  <div class="story-copy"><?php if ($sec['eyebrow']): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?><h2 id="h-story"><?= e($sec['title']) ?></h2><p><?= nl2br(e($sec['text'])) ?></p><?php if ($sec['cta_text']): ?><a class="btn btn--ghost" href="<?= e($sec['cta_url'] ?: '/about/') ?>"><?= e($sec['cta_text']) ?></a><?php endif; ?></div>
</div></section>
<?php endif; ?>
