<?php
$v = fn($k, $d = '') => old($k, $p[$k] ?? $d);
$num = fn($x) => $x === null || $x === '' ? '' : number_format((float) $x, 2, ',', '');
$optNames = $variants ? implode(' / ', array_keys(jd($variants[0]['options']))) : 'Μέγεθος';
?>
<form method="post" action="/admin/products/save" data-seo-analyze="/admin/seo/analyze" data-seo-type="product" class="grid">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
<div class="page-head">
  <div><h1><?= $p ? e($p['name']) : 'Νέο προϊόν' ?></h1><p><?= $p ? 'Δημιουργήθηκε ' . fmt_date($p['created_at']) . ' · Πωλήσεις: ' . $sold . ' τεμ.' : 'Συμπληρώστε τα στοιχεία του προϊόντος.' ?></p></div>
  <div class="actions"><?php if ($p && $p['is_active']): ?><a class="btn" href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener"><?= icon('eye') ?>Προβολή</a><?php endif; ?>
  <button class="btn" name="_after" value="new">Αποθήκευση & νέο</button><button class="btn btn-primary">Αποθήκευση</button></div>
</div>
<div class="split">
  <div class="grid">
    <div class="card"><div class="bd form">
      <div class="field"><label for="p-name">Όνομα προϊόντος *</label><input id="p-name" name="name" required maxlength="250" value="<?= e($v('name')) ?>"></div>
      <div class="field"><label for="p-slug">Slug (URL)</label><div class="input-group"><span class="addon">/product/</span><input id="p-slug" name="slug" data-slug-from="#p-name" value="<?= e($v('slug')) ?>"></div><span class="hint">Αν αλλάξει, δημιουργείται αυτόματα ανακατεύθυνση 301.</span></div>
      <div class="field"><label for="p-short">Σύντομη περιγραφή</label><textarea id="p-short" name="short_description" rows="3" data-rte><?= e($v('short_description')) ?></textarea><span class="hint">Σύντομη, πραγματική απάντηση «τι είναι» — χρησιμοποιείται και από μηχανές απαντήσεων (AEO).</span></div>
      <div class="field"><label for="p-desc">Περιγραφή</label><textarea id="p-desc" name="description" rows="10" data-rte><?= e($v('description')) ?></textarea></div>
    </div></div>
    <div class="card"><div class="hd"><div><h2>Εικόνες</h2><p>Η πρώτη εικόνα είναι η κύρια. Σύρετε για αλλαγή σειράς.</p></div></div><div class="bd">
      <div class="gallery-edit" data-gallery="gallery">
        <?php foreach ($images as $m): ?><div class="media-item" draggable="true"><img src="<?= e(media_url($m, 400)) ?>" alt=""><input type="hidden" name="gallery[]" value="<?= (int) $m['id'] ?>"><button type="button" class="x" aria-label="Αφαίρεση">✕</button></div><?php endforeach; ?>
        <button type="button" class="add" data-gallery-add><?= icon('plus') ?></button>
      </div></div></div>
    <div class="card"><div class="hd"><h2>Τιμή & απόθεμα</h2></div><div class="bd form"><div class="fg fg3">
      <div class="field"><label for="p-price">Τιμή (€, με ΦΠΑ) *</label><input id="p-price" name="price" inputmode="decimal" required value="<?= e(old('price', $num($p['price'] ?? null))) ?>"></div>
      <div class="field"><label for="p-sale">Τιμή προσφοράς (€)</label><input id="p-sale" name="sale_price" inputmode="decimal" value="<?= e(old('sale_price', $num($p['sale_price'] ?? null))) ?>"></div>
      <div class="field"><label for="p-cost">Κόστος (€, εσωτερικό)</label><input id="p-cost" name="cost_price" inputmode="decimal" value="<?= e(old('cost_price', $num($p['cost_price'] ?? null))) ?>"></div>
      <div class="field"><label for="p-sku">SKU</label><input id="p-sku" name="sku" value="<?= e($v('sku')) ?>"></div>
      <div class="field"><label for="p-bar">Barcode / EAN</label><input id="p-bar" name="barcode" value="<?= e($v('barcode')) ?>"></div>
      <div class="field"><label for="p-mpn">MPN</label><input id="p-mpn" name="mpn" value="<?= e($v('mpn')) ?>"></div>
      <div class="field"><label for="p-stock">Απόθεμα</label><input id="p-stock" type="number" name="stock" value="<?= (int) $v('stock', 0) ?>"></div>
      <div class="field"><label for="p-av">Διαθεσιμότητα</label><select id="p-av" name="availability"><?php foreach (['in_stock', 'out_of_stock', 'preorder', 'backorder'] as $a): ?><option value="<?= $a ?>"<?= $v('availability', 'in_stock') === $a ? ' selected' : '' ?>><?= e(status_label($a)) ?></option><?php endforeach; ?></select></div>
      <div class="field"><span class="lbl">Διαχείριση αποθέματος</span><label class="check"><input type="checkbox" name="manage_stock" value="1"<?= (int) $v('manage_stock', 1) ? ' checked' : '' ?>> Μείωση αποθέματος με τις παραγγελίες</label></div>
    </div></div></div>
    <div class="card" data-repeater><div class="hd"><div><h2>Παραλλαγές</h2><p>π.χ. μέγεθος ή χρώμα — κάθε παραλλαγή με δικό της SKU, τιμή και απόθεμα.</p></div><button type="button" class="btn btn-sm" data-add><?= icon('plus') ?>Παραλλαγή</button></div><div class="bd form">
      <div class="field"><label for="p-optn">Ονόματα επιλογών (χωρισμένα με /)</label><input id="p-optn" name="variant_option_names" value="<?= e($optNames) ?>" placeholder="Χρώμα / Μέγεθος"><span class="hint">Το όνομα κάθε παραλλαγής ακολουθεί την ίδια σειρά, π.χ. «Μαύρο / M».</span></div>
      <div class="rep">
        <?php foreach ($variants as $vr): ?><div class="rep-row" style="--cols:5"><input type="hidden" data-name="variants[__i__][id]" value="<?= (int) $vr['id'] ?>"><input data-name="variants[__i__][name]" value="<?= e($vr['name']) ?>" placeholder="Όνομα (π.χ. Μαύρο / M)" aria-label="Όνομα"><input data-name="variants[__i__][sku]" value="<?= e($vr['sku']) ?>" placeholder="SKU" aria-label="SKU"><input data-name="variants[__i__][price]" value="<?= e($num($vr['price'])) ?>" placeholder="Τιμή (κενό = βασική)" aria-label="Τιμή"><input data-name="variants[__i__][sale_price]" value="<?= e($num($vr['sale_price'])) ?>" placeholder="Προσφορά" aria-label="Προσφορά"><input type="number" data-name="variants[__i__][stock]" value="<?= (int) $vr['stock'] ?>" placeholder="Απόθεμα" aria-label="Απόθεμα"><button type="button" class="btn rm" aria-label="Αφαίρεση">✕</button></div><?php endforeach; ?>
      </div></div>
      <template><div class="rep-row" style="--cols:5"><input data-name="variants[__i__][name]" placeholder="Όνομα (π.χ. Μαύρο / M)" aria-label="Όνομα"><input data-name="variants[__i__][sku]" placeholder="SKU" aria-label="SKU"><input data-name="variants[__i__][price]" placeholder="Τιμή (κενό = βασική)" aria-label="Τιμή"><input data-name="variants[__i__][sale_price]" placeholder="Προσφορά" aria-label="Προσφορά"><input type="number" data-name="variants[__i__][stock]" value="0" placeholder="Απόθεμα" aria-label="Απόθεμα"><button type="button" class="btn rm" aria-label="Αφαίρεση">✕</button></div></template>
    </div>
    <div class="card" data-repeater><div class="hd"><div><h2>Χαρακτηριστικά</h2><p>Εμφανίζονται ως πίνακας προδιαγραφών και ως φίλτρα κατηγορίας.</p></div><button type="button" class="btn btn-sm" data-add><?= icon('plus') ?>Χαρακτηριστικό</button></div><div class="bd">
      <div class="rep"><?php foreach ($attributes as $a): ?><div class="rep-row" style="--cols:2"><input data-name="attributes[__i__][name]" value="<?= e($a['name']) ?>" placeholder="Όνομα (π.χ. Υλικό)" aria-label="Όνομα"><input data-name="attributes[__i__][value]" value="<?= e($a['value']) ?>" placeholder="Τιμή (π.χ. Βαμβάκι)" aria-label="Τιμή"><button type="button" class="btn rm" aria-label="Αφαίρεση">✕</button></div><?php endforeach; ?></div></div>
      <template><div class="rep-row" style="--cols:2"><input data-name="attributes[__i__][name]" placeholder="Όνομα (π.χ. Υλικό)" aria-label="Όνομα"><input data-name="attributes[__i__][value]" placeholder="Τιμή (π.χ. Βαμβάκι)" aria-label="Τιμή"><button type="button" class="btn rm" aria-label="Αφαίρεση">✕</button></div></template>
    </div>
    <div class="card"><div class="hd"><h2>Αποστολή</h2></div><div class="bd"><div class="fg fg4">
      <div class="field"><label>Βάρος (kg)</label><input name="weight" inputmode="decimal" value="<?= e($v('weight')) ?>"></div><div class="field"><label>Μήκος (cm)</label><input name="length" inputmode="decimal" value="<?= e($v('length')) ?>"></div>
      <div class="field"><label>Πλάτος (cm)</label><input name="width" inputmode="decimal" value="<?= e($v('width')) ?>"></div><div class="field"><label>Ύψος (cm)</label><input name="height" inputmode="decimal" value="<?= e($v('height')) ?>"></div>
    </div></div></div>
    <?php include __DIR__ . '/_faq.php'; ?>
  </div>
  <div class="grid">
    <div class="card"><div class="hd"><h2>Κατάσταση</h2></div><div class="bd form">
      <label class="check"><input type="checkbox" name="is_active" value="1"<?= (int) old('is_active', $p['is_active'] ?? 1) ? ' checked' : '' ?>> <span><b>Ενεργό στο κατάστημα</b><br><small class="muted">Ενεργά: <?= $quota['active'] ?> / <?= $quota['limit'] ?>. Αν το όριο έχει συμπληρωθεί, το προϊόν αποθηκεύεται ως ανενεργό.</small></span></label>
      <label class="check"><input type="checkbox" name="featured" value="1"<?= (int) $v('featured', 0) ? ' checked' : '' ?>> Προτεινόμενο (αρχική σελίδα)</label>
    </div></div>
    <div class="card"><div class="hd"><h2>Οργάνωση</h2></div><div class="bd form">
      <div class="field"><label for="p-pcat">Κύρια κατηγορία</label><select id="p-pcat" name="primary_category_id"><option value="">—</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"<?= (int) $v('primary_category_id') === (int) $c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select></div>
      <fieldset class="box" style="max-height:220px;overflow:auto"><legend>Επιπλέον κατηγορίες</legend><?php foreach ($cats as $c): ?><label class="check" style="padding-left:<?= (int) $c['depth'] * 14 ?>px"><input type="checkbox" name="category_ids[]" value="<?= $c['id'] ?>"<?= in_array((int) $c['id'], $catIds, true) ? ' checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?><?php if (!$cats): ?><span class="muted">Δεν υπάρχουν κατηγορίες. <a href="/admin/categories/new">Δημιουργία</a></span><?php endif; ?></fieldset>
      <div class="field"><label for="p-brand">Μάρκα</label><input id="p-brand" name="brand" list="brands" value="<?= e($v('brand')) ?>"><datalist id="brands"><?php foreach ($brands as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist></div>
      <div class="field"><label for="p-tags">Ετικέτες</label><input id="p-tags" name="tags" value="<?= e($v('tags')) ?>" placeholder="χωρισμένες με κόμμα"></div>
    </div></div>
    <?php $e = $p ?? []; include __DIR__ . '/_seo_fields.php'; ?>
    <details class="card"><summary class="hd" style="cursor:pointer"><h2>Προχωρημένα: custom JSON-LD</h2></summary><div class="bd"><textarea name="schema_json" class="code" rows="6" placeholder='{"@type":"Product","material":"..."}'><?= e($v('schema_json')) ?></textarea><span class="hint">Προστίθεται στο αυτόματο schema του προϊόντος. Μη συμπεριλαμβάνετε ψεύτικες αξιολογήσεις.</span></div></details>
  </div>
</div>
</form>
<?php if ($p): ?>
<div class="row-flex">
  <form method="post" action="/admin/products/<?= $p['id'] ?>/duplicate"><?= csrf_field() ?><button class="btn"><?= icon('file') ?>Δημιουργία αντιγράφου</button></form>
  <form method="post" action="/admin/products/bulk"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= $p['id'] ?>"><button class="btn btn-danger" name="action" value="delete" data-confirm="Διαγραφή του προϊόντος; Οι παραγγελίες που το περιέχουν δεν επηρεάζονται." data-confirm-type="ΔΙΑΓΡΑΦΗ"><?= icon('trash') ?>Διαγραφή</button></form>
</div>
<?php endif; ?>
