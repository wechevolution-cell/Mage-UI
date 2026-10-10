<div class="wrap page-pad">
  <?= partial('breadcrumbs', ['crumbs' => [['Αρχική', '/'], ['Blog', null]]]) ?>
  <h1 class="page-title">Blog</h1>
  <?php if (!$posts): ?><div class="empty-state"><p>Δεν υπάρχουν άρθρα ακόμη.</p></div><?php endif; ?>
  <div class="post-grid"><?php foreach ($posts as $p): ?>
    <article class="post-card"><a href="/blog/<?= e($p['slug']) ?>/" class="post-img"><?= sf_img($p['image'], '(max-width: 700px) 100vw, 33vw') ?></a><div class="post-body"><?php if ($p['category']): ?><span class="eyebrow"><?= e($p['category']) ?></span><?php endif; ?><h2><a href="/blog/<?= e($p['slug']) ?>/"><?= e($p['title']) ?></a></h2><p><?= e($p['excerpt'] ?: excerpt($p['content'], 150)) ?></p><time datetime="<?= e(date('c', strtotime($p['published_at']))) ?>"><?= fmt_date($p['published_at']) ?></time></div></article>
  <?php endforeach; ?></div>
  <?= partial('pagination', ['pg' => $pg]) ?>
</div>
