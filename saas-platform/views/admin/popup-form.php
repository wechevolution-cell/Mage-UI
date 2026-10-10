<form method="post" action="/admin/popups/save" class="grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
<div class="page-head"><div><h1><?= e($p['name'] ?? 'Νέο pop-up') ?></h1></div><div class="actions"><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="split"><div class="card"><div class="bd form">
  <div class="field"><label>Εσωτερικό όνομα</label><input name="name" value="<?= e($p['name'] ?? 'Newsletter') ?>"></div>
  <div class="field"><label>Τίτλος</label><input name="title" value="<?= e($p['title'] ?? '') ?>"></div>
  <div class="field"><label>Κείμενο</label><textarea name="body" rows="3"><?= e($p['body'] ?? '') ?></textarea></div>
  <div class="fg"><div class="field"><label>Κείμενο κουμπιού</label><input name="button_text" value="<?= e($p['button_text'] ?? 'Εγγραφή') ?>"></div><div class="field"><label>Σύνδεσμος CTA (αν δεν συλλέγει email)</label><input name="cta_url" value="<?= e($p['cta_url'] ?? '') ?>"></div></div>
  <label class="check"><input type="checkbox" name="collect_email" value="1"<?= ($p['collect_email'] ?? 1) ? ' checked' : '' ?>> Πεδίο email (συλλογή συνδρομητών)</label>
  <label class="check"><input type="checkbox" name="require_consent" value="1"<?= ($p['require_consent'] ?? 1) ? ' checked' : '' ?>> Υποχρεωτικό checkbox συγκατάθεσης (GDPR)</label>
  <div class="field"><label>Κείμενο συγκατάθεσης</label><input name="consent_text" value="<?= e($p['consent_text'] ?? 'Συμφωνώ με την Πολιτική απορρήτου και την αποστολή ενημερώσεων.') ?>"></div>
  <?php $name = 'image_id'; $media = $img; $label = 'Εικόνα (προαιρετικό)'; include __DIR__ . '/_img_pick.php'; ?>
</div></div>
<div class="card"><div class="hd"><h2>Εμφάνιση</h2></div><div class="bd form">
  <label class="check"><input type="checkbox" name="active" value="1"<?= ($p['status'] ?? '') === 'active' ? ' checked' : '' ?>> <b>Ενεργό</b></label>
  <div class="field"><label>Σελίδες</label><select name="pages"><option value="home">Μόνο αρχική</option><option value="all"<?= ($p['pages'] ?? '') === 'all' ? ' selected' : '' ?>>Όλες οι σελίδες</option></select></div>
  <div class="field"><label>Ενεργοποίηση</label><select name="trigger_type"><option value="delay">Μετά από καθυστέρηση</option><option value="exit"<?= ($p['trigger_type'] ?? '') === 'exit' ? ' selected' : '' ?>>Πρόθεση εξόδου (desktop)</option><option value="scroll"<?= ($p['trigger_type'] ?? '') === 'scroll' ? ' selected' : '' ?>>Μετά από scroll 50%</option></select></div>
  <div class="field"><label>Καθυστέρηση (δευτερόλεπτα)</label><input type="number" min="0" max="120" name="delay_seconds" value="<?= (int) ($p['delay_seconds'] ?? 8) ?>"></div>
  <div class="field"><label>Επανεμφάνιση μετά από (ημέρες)</label><input type="number" min="0" max="365" name="frequency_days" value="<?= (int) ($p['frequency_days'] ?? 7) ?>"></div>
  <div class="field"><label>Από</label><input type="datetime-local" name="starts_at" value="<?= e(!empty($p['starts_at']) ? date('Y-m-d\TH:i', strtotime($p['starts_at'])) : '') ?>"></div>
  <div class="field"><label>Έως</label><input type="datetime-local" name="ends_at" value="<?= e(!empty($p['ends_at']) ? date('Y-m-d\TH:i', strtotime($p['ends_at'])) : '') ?>"></div>
</div></div></div></form>
<?php if ($p): ?><form method="post" action="/admin/popups/<?= $p['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-danger" data-confirm="Διαγραφή pop-up;"><?= icon('trash') ?>Διαγραφή</button></form><?php endif; ?>
