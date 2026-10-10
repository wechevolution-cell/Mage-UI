<form method="post" action="/admin/users/save" class="grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($u['id'] ?? 0) ?>">
<div class="page-head"><div><h1><?= e($u['name'] ?? 'Νέος χρήστης') ?></h1><p>Δεν μπορείτε να δώσετε δικαιώματα μεγαλύτερα από τα δικά σας.</p></div><div class="actions"><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="grid g2"><div class="card"><div class="bd form">
  <div class="field"><label>Όνομα</label><input name="name" required value="<?= e($u['name'] ?? '') ?>"></div>
  <div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($u['email'] ?? '') ?>"></div>
  <div class="field"><label>Ρόλος</label><select name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>"<?= ($u['role'] ?? 'editor') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label><?= $u ? 'Νέος κωδικός (κενό = χωρίς αλλαγή)' : 'Κωδικός' ?></label><input type="text" name="password" minlength="10" autocomplete="off"<?= $u ? '' : ' required' ?>></div>
  <div class="field"><label>Κατάσταση</label><select name="status"><option value="active">Ενεργός</option><option value="disabled"<?= ($u['status'] ?? '') === 'disabled' ? ' selected' : '' ?>>Απενεργοποιημένος</option></select></div>
</div></div>
<div class="card"><div class="hd"><div><h2>Δικαιώματα</h2><p>Προεπιλογή ανά ρόλο, ή προσαρμοσμένα.</p></div></div><div class="bd form">
  <label class="check"><input type="checkbox" name="custom_perms" value="1"<?= $u && $u['permissions'] ? ' checked' : '' ?>> Προσαρμοσμένα δικαιώματα</label>
  <div class="fg"><?php foreach (ShopUsers::MODULES as $k => $l): $mine = in_array($k, ShopUsers::permissions($user), true); ?><label class="check"><input type="checkbox" name="perms[]" value="<?= $k ?>"<?= in_array($k, $perms, true) ? ' checked' : '' ?><?= !$mine ? ' disabled' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div>
  <p class="muted" style="font-size:12px">Προεπιλογές: Συντάκτης → σελίδες, πολυμέσα, εμφάνιση, pop-ups, SEO. Υπεύθυνος προϊόντων → προϊόντα, κατηγορίες, εισαγωγή, πολυμέσα, feeds, παραγγελίες.</p>
</div></div></div></form>
