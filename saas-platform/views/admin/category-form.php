<form method="post" action="/admin/categories/save" class="grid" data-seo-analyze="/admin/seo/analyze" data-seo-type="category"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
<div class="page-head"><div><h1><?= $c ? e($c['name']) : 'Νέα κατηγορία' ?></h1></div><div class="actions"><?php if ($c): ?><a class="btn" href="/category/<?= e($c['path']) ?>/" target="_blank" rel="noopener"><?= icon('eye') ?>Προβολή</a><?php endif; ?><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="split"><div class="grid">
  <div class="card"><div class="bd form">
    <div class="fg"><div class="field"><label for="c-name">Όνομα *</label><input id="c-name" name="name" required value="<?= e($c['name'] ?? '') ?>"></div>
    <div class="field"><label for="c-slug">Slug</label><input id="c-slug" name="slug" data-slug-from="#c-name" value="<?= e($c['slug'] ?? '') ?>"></div>
    <div class="field"><label for="c-parent">Γονική κατηγορία</label><select id="c-parent" name="parent_id"><option value="">— Κορυφαίο επίπεδο —</option><?php foreach ($cats as $x): ?><option value="<?= $x['id'] ?>"<?= (int) ($c['parent_id'] ?? 0) === (int) $x['id'] ? ' selected' : '' ?>><?= str_repeat('— ', (int) $x['depth']) . e($x['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="c-sort">Σειρά</label><input id="c-sort" type="number" name="sort" value="<?= (int) ($c['sort'] ?? 0) ?>"></div></div>
    <div class="field"><label for="c-desc">Σύντομη περιγραφή (εμφανίζεται κάτω από τον τίτλο)</label><textarea id="c-desc" name="description" rows="2"><?= e($c['description'] ?? '') ?></textarea></div>
    <div class="field"><label for="c-content">Κείμενο SEO / οδηγός αγοράς (κάτω από τα προϊόντα)</label><textarea id="c-content" name="content" rows="8" data-rte><?= e($c['content'] ?? '') ?></textarea></div>
  </div></div>
  <?php include __DIR__ . '/_faq.php'; ?>
</div><div class="grid">
  <div class="card"><div class="hd"><h2>Ρυθμίσεις</h2></div><div class="bd form">
    <div class="field"><label>Κατάσταση</label><select name="status"><option value="active">Ενεργή</option><option value="inactive"<?= ($c['status'] ?? '') === 'inactive' ? ' selected' : '' ?>>Ανενεργή</option></select></div>
    <?php $name = 'image_id'; $media = $img; $label = 'Εικόνα κατηγορίας'; include __DIR__ . '/_img_pick.php'; ?>
  </div></div>
  <?php $e = $c ?? []; include __DIR__ . '/_seo_fields.php'; ?>
</div></div></form>
