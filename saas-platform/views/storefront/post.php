<?php $p = $post; ?>
<article class="wrap page-pad post">
  <?= partial('breadcrumbs', ['crumbs' => [['Αρχική', '/'], ['Blog', '/blog/'], [$p['title'], null]]]) ?>
  <header class="post-head"><?php if ($p['category']): ?><span class="eyebrow"><?= e($p['category']) ?></span><?php endif; ?><h1><?= e($p['title']) ?></h1>
    <p class="post-meta"><?php if ($p['author']): ?>Από <b><?= e($p['author']) ?></b> · <?php endif; ?><time datetime="<?= e(date('c', strtotime($p['published_at']))) ?>"><?= fmt_date($p['published_at']) ?></time><?php if ($p['updated_at'] && $p['updated_at'] > $p['published_at']): ?> · Ενημέρωση <?= fmt_date($p['updated_at']) ?><?php endif; ?></p></header>
  <?php if ($p['image']): ?><figure class="post-cover"><?= media_img($p['image'], '(max-width: 900px) 100vw, 900px', '', true) ?></figure><?php endif; ?>
  <div class="post-layout">
    <div class="post-main">
      <?php if ($p['tldr']): ?><aside class="tldr"><b>Με μια ματιά (TL;DR)</b><p><?= nl2br(e($p['tldr'])) ?></p></aside><?php endif; ?>
      <div class="prose"><?= Sanitizer::html($html) ?></div>
      <?= partial('faq', ['faq' => $faq]) ?>
      <?php if ($p['sources']): ?><section class="sources"><h2>Πηγές</h2><ul><?php foreach (array_filter(array_map('trim', explode("\n", $p['sources']))) as $s): ?><li><?= Sanitizer::safeUrl($s) && preg_match('#^https?://#', $s) ? '<a href="' . e($s) . '" rel="nofollow noopener" target="_blank">' . e($s) . '</a>' : e($s) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
      <div class="post-cta"><b>Ανακαλύψτε τα προϊόντα μας</b><a class="btn btn--primary" href="/products/">Στο κατάστημα</a></div>
    </div>
    <?php if (count($toc) > 1): ?><nav class="toc" aria-label="Περιεχόμενα"><b>Περιεχόμενα</b><ol><?php foreach ($toc as $t): ?><li class="lv<?= (int) $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ol></nav><?php endif; ?>
  </div>
  <?php if ($related): ?><section class="sec"><div class="sec-head"><h2>Σχετικά άρθρα</h2></div><div class="post-grid"><?php foreach ($related as $r): ?><article class="post-card"><div class="post-body"><h3><a href="/blog/<?= e($r['slug']) ?>/"><?= e($r['title']) ?></a></h3><p><?= e($r['excerpt'] ?: excerpt($r['content'], 120)) ?></p></div></article><?php endforeach; ?></div></section><?php endif; ?>
</article>
