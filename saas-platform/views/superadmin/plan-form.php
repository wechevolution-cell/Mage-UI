<?php $f = $plan ? Plans::features($plan) : array_fill_keys(array_keys(Plans::FEATURES), true); ?>
<div class="page-head"><div><h1><?= $plan ? 'Πακέτο: ' . e($plan['name']) : 'Νέο πακέτο' ?></h1><p>Οι αλλαγές ισχύουν άμεσα για όλα τα καταστήματα του πακέτου.</p></div></div>
<form method="post" action="/superadmin/plans/save" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($plan['id'] ?? 0) ?>">
  <div class="bd form">
    <div class="fg fg3">
      <div class="field"><label for="pn">Όνομα</label><input id="pn" name="name" required value="<?= e($plan['name'] ?? '') ?>"></div>
      <div class="field"><label for="ps">Slug</label><input id="ps" name="slug" data-slug-from="#pn" value="<?= e($plan['slug'] ?? '') ?>"></div>
      <div class="field"><label for="pst">Κατάσταση</label><select id="pst" name="status"><option value="active">Ενεργό</option><option value="inactive"<?= ($plan['status'] ?? '') === 'inactive' ? ' selected' : '' ?>>Ανενεργό (δεν προσφέρεται σε νέα καταστήματα)</option></select></div>
      <div class="field"><label for="pp">Τιμή ανά μήνα (€)</label><input id="pp" name="price" inputmode="decimal" required value="<?= e(isset($plan) ? number_format((float) $plan['price'], 2, ',', '') : '') ?>"></div>
      <div class="field"><label for="psf">Setup fee (€)</label><input id="psf" name="setup_fee" inputmode="decimal" value="<?= e(isset($plan) ? number_format((float) $plan['setup_fee'], 2, ',', '') : '150,00') ?>"></div>
      <div class="field"><label for="pso">Σειρά εμφάνισης</label><input id="pso" type="number" name="sort" value="<?= (int) ($plan['sort'] ?? 0) ?>"></div>
      <div class="field"><label for="pal">Μέγιστα ενεργά προϊόντα</label><input id="pal" type="number" min="0" name="active_product_limit" required value="<?= (int) ($plan['active_product_limit'] ?? 20) ?>"><span class="hint">Το όριο αφορά ΕΝΕΡΓΑ προϊόντα, όχι αποθηκευμένα.</span></div>
      <div class="field"><label for="psl">Μέγιστα αποθηκευμένα προϊόντα</label><input id="psl" type="number" min="1" name="stored_product_limit" value="<?= (int) ($plan['stored_product_limit'] ?? 10000) ?>"></div>
      <div class="field"><label for="pstf">Μέγιστοι χρήστες προσωπικού</label><input id="pstf" type="number" min="1" name="staff_limit" value="<?= (int) ($plan['staff_limit'] ?? 3) ?>"></div>
      <div class="field"><label for="psto">Αποθηκευτικός χώρος (MB)</label><input id="psto" type="number" min="50" name="storage_limit_mb" value="<?= (int) ($plan['storage_limit_mb'] ?? 2048) ?>"></div>
      <div class="field"><label for="pdur">Διαθέσιμες διάρκειες (μήνες)</label><input id="pdur" name="duration_options" value="<?= e(implode(', ', Plans::durations($plan))) ?>"><span class="hint">π.χ. 1, 3, 6, 12</span></div>
      <div class="field full"><label for="pdesc">Περιγραφή</label><input id="pdesc" name="description" value="<?= e($plan['description'] ?? '') ?>"></div>
    </div>
    <fieldset class="box"><legend>Λειτουργίες πακέτου (feature flags)</legend>
      <div class="fg"><?php foreach (Plans::FEATURES as $k => $label): ?><label class="check"><input type="checkbox" name="feat_<?= $k ?>" value="1"<?= $f[$k] ? ' checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?></div>
    </fieldset>
  </div>
  <div class="ft"><a class="btn" href="/superadmin/plans">Ακύρωση</a><button class="btn btn-primary">Αποθήκευση πακέτου</button></div>
</form>
