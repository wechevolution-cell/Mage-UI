<form method="post" action="/admin/feeds/save" class="grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($f['id'] ?? 0) ?>">
<div class="page-head"><div><h1><?= e($f['name'] ?? 'Νέο custom feed') ?></h1></div><div class="actions"><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="split"><div class="grid"><div class="card"><div class="bd form"><div class="fg">
  <div class="field"><label>Όνομα</label><input name="name" required value="<?= e($f['name'] ?? '') ?>"></div>
  <div class="field"><label>Slug (URL /feed/slug.xml)</label><input name="slug" value="<?= e($f['slug'] ?? '') ?>"></div>
  <div class="field"><label>Τύπος</label><select name="type"><?php foreach (Feeds::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= ($f['type'] ?? 'custom') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Συχνότητα ανανέωσης</label><select name="frequency"><?php foreach (Feeds::FREQ as $k => $l): ?><option value="<?= $k ?>"<?= ($f['frequency'] ?? 'hourly') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
</div><label class="check"><input type="checkbox" name="active" value="1"<?= ($f['status'] ?? 'active') === 'active' ? ' checked' : '' ?>> Ενεργό</label></div></div>
<div class="card"><div class="hd"><div><h2>Πεδία (custom feed)</h2><p>Επιλέξτε ποια στοιχεία θα περιέχει κάθε &lt;product&gt;.</p></div></div><div class="bd"><div class="fg fg3"><?php foreach (Feeds::CUSTOM_FIELDS as $fl): ?><label class="check"><input type="checkbox" name="fields[]" value="<?= $fl ?>"<?= in_array($fl, $fields, true) ? ' checked' : '' ?>> <code><?= $fl ?></code></label><?php endforeach; ?></div></div></div></div>
<div class="card"><div class="hd"><h2>Φίλτρα προϊόντων</h2></div><div class="bd form">
  <fieldset class="box" style="max-height:260px;overflow:auto"><legend>Κατηγορίες (κενό = όλες)</legend><?php foreach ($cats as $c): ?><label class="check" style="padding-left:<?= (int) $c['depth'] * 14 ?>px"><input type="checkbox" name="category_ids[]" value="<?= $c['id'] ?>"<?= in_array((int) $c['id'], array_map('intval', $filters['category_ids'] ?? []), true) ? ' checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?></fieldset>
  <label class="check"><input type="checkbox" name="in_stock" value="1"<?= !empty($filters['in_stock']) ? ' checked' : '' ?>> Μόνο διαθέσιμα</label>
  <div class="field"><label>Ελάχιστη τιμή (€)</label><input name="min_price" value="<?= e($filters['min_price'] ?? '') ?>"></div>
  <div class="field"><label>Μάρκα</label><input name="brand" value="<?= e($filters['brand'] ?? '') ?>"></div>
  <p class="muted" style="font-size:12px">Πάντα περιλαμβάνονται μόνο ενεργά προϊόντα εντός ορίου πακέτου.</p>
</div></div></div></form>
<?php if ($f): ?><form method="post" action="/admin/feeds/<?= $f['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-danger" data-confirm="Διαγραφή feed; Οι πλατφόρμες που το χρησιμοποιούν θα πάψουν να λαμβάνουν δεδομένα."><?= icon('trash') ?>Διαγραφή</button></form><?php endif; ?>
