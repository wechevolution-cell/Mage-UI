<div class="page-head"><div><h1>Πληρωμές</h1><p>Αντικαταβολή και τραπεζική κατάθεση. Η αρχιτεκτονική υποστηρίζει μελλοντική ενσωμάτωση Stripe / Viva Wallet.</p></div></div>
<?php include __DIR__ . '/_settings_tabs.php'; ?>
<form method="post" action="/admin/settings/payments" class="grid g2"><?= csrf_field() ?>
<?php foreach (['cod' => 'Αντικαταβολή', 'bank' => 'Τραπεζική κατάθεση'] as $k => $title): $m = $s[$k]; ?>
  <div class="card"><div class="hd"><h2><?= $title ?></h2><label class="check"><input type="checkbox" name="<?= $k ?>_enabled" value="1"<?= $m['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div><div class="bd form">
    <div class="fg"><div class="field"><label>Ετικέτα</label><input name="<?= $k ?>_label" value="<?= e($m['label']) ?>"></div><div class="field"><label>Επιπλέον χρέωση (€)</label><input name="<?= $k ?>_fee" inputmode="decimal" value="<?= e(number_format((float) $m['fee'], 2, ',', '')) ?>"></div></div>
    <div class="field"><label><?= $k === 'bank' ? 'Στοιχεία λογαριασμού / οδηγίες (εμφανίζονται μετά την παραγγελία και στο email)' : 'Οδηγίες' ?></label><textarea name="<?= $k ?>_instructions" rows="5"><?= e($m['instructions']) ?></textarea></div>
  </div></div>
<?php endforeach; ?>
  <div style="grid-column:1 / -1"><button class="btn btn-primary">Αποθήκευση</button></div>
</form>
