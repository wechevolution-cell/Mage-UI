<div class="page-head"><div><h1>Πολυμέσα</h1><p>Χρήση χώρου: <?= number_format($bytes / 1048576, 1, ',', '.') ?> MB από <?= number_format($limitMb, 0, ',', '.') ?> MB. Αυτόματη βελτιστοποίηση σε WebP και responsive μεγέθη.</p></div></div>
<form method="post" action="/admin/media/upload" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
  <div class="bd"><div class="dropzone"><p><b>Ανέβασμα εικόνων</b> (JPG, PNG, WebP, GIF έως 10 MB)</p><input type="file" name="file[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" required aria-label="Εικόνες"></div></div>
  <div class="ft"><input name="alt" placeholder="Εναλλακτικό κείμενο (alt) για όλες" style="max-width:320px" aria-label="Alt"><button class="btn btn-primary"><?= icon('upload') ?>Ανέβασμα</button></div>
</form>
<div class="card"><form class="toolbar" method="get"><input type="search" name="q" value="<?= e(in_str('q')) ?>" placeholder="Αναζήτηση…" aria-label="Αναζήτηση"><button class="btn">Αναζήτηση</button></form>
<div class="bd">
<?php if (!$rows): ?><div class="empty">Δεν υπάρχουν εικόνες.</div><?php endif; ?>
<div class="media-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
<?php foreach ($rows as $m): ?>
  <details class="card" style="overflow:hidden"<?= in_int('confirm') === (int) $m['id'] ? ' open' : '' ?>><summary style="list-style:none;cursor:pointer"><img src="<?= e(media_url($m, 400)) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;display:block"><div style="padding:6px 8px;font-size:12px" class="muted"><?= (int) $m['width'] ?>×<?= (int) $m['height'] ?> · <?= number_format($m['size'] / 1024, 0) ?> KB<?= !$m['alt'] ? ' · <span style="color:var(--warn)">χωρίς alt</span>' : '' ?></div></summary>
    <form method="post" action="/admin/media/<?= $m['id'] ?>/update" enctype="multipart/form-data" class="form" style="padding:8px"><?= csrf_field() ?>
      <input name="alt" value="<?= e($m['alt']) ?>" placeholder="Alt text" aria-label="Alt"><input name="title" value="<?= e($m['title']) ?>" placeholder="Τίτλος" aria-label="Τίτλος">
      <label class="muted" style="font-size:12px">Αντικατάσταση αρχείου<input type="file" name="file" accept="image/*"></label>
      <div class="row-flex"><button class="btn btn-sm btn-primary">Αποθήκευση</button><button class="btn btn-sm" type="button" data-copy="<?= e(abs_url(media_url($m))) ?>">URL</button></div>
    </form>
    <form method="post" action="/admin/media/<?= $m['id'] ?>/delete" style="padding:0 8px 8px"><?= csrf_field() ?><?= in_int('confirm') === (int) $m['id'] ? '<input type="hidden" name="force" value="1">' : '' ?><button class="btn btn-sm btn-danger" data-confirm="Διαγραφή εικόνας;">Διαγραφή</button></form>
  </details>
<?php endforeach; ?>
</div></div>
<?php include __DIR__ . '/_pager.php'; ?></div>
