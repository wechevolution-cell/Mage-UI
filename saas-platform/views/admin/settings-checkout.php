<div class="page-head"><div><h1>Ταμείο, ΦΠΑ & ειδοποιήσεις</h1></div></div>
<?php include __DIR__ . '/_settings_tabs.php'; ?>
<form method="post" action="/admin/settings/checkout" class="grid g2"><?= csrf_field() ?>
  <div class="card"><div class="hd"><h2>Ταμείο</h2></div><div class="bd form">
    <div class="field"><label>Πρόθεμα αριθμού παραγγελίας</label><input name="order_prefix" value="<?= e($c['order_prefix']) ?>" maxlength="8"></div>
    <div class="field"><label>Ελάχιστη αξία παραγγελίας (€)</label><input name="min_order" inputmode="decimal" value="<?= e(number_format((float) $c['min_order'], 2, ',', '')) ?>"></div>
    <label class="check"><input type="checkbox" name="invoice_enabled" value="1"<?= $c['invoice_enabled'] ? ' checked' : '' ?>> Δυνατότητα τιμολογίου (ΑΦΜ, ΔΟΥ)</label>
    <label class="check"><input type="checkbox" name="gift_wrap_enabled" value="1"<?= $c['gift_wrap_enabled'] ? ' checked' : '' ?>> Συσκευασία δώρου</label>
    <div class="field"><label>Κόστος συσκευασίας δώρου (€)</label><input name="gift_wrap_price" inputmode="decimal" value="<?= e(number_format((float) $c['gift_wrap_price'], 2, ',', '')) ?>"></div>
  </div></div>
  <div class="card"><div class="hd"><h2>ΦΠΑ & ειδοποιήσεις</h2></div><div class="bd form">
    <div class="field"><label>Συντελεστής ΦΠΑ (%) — οι τιμές περιλαμβάνουν ΦΠΑ</label><input name="vat_rate" inputmode="decimal" value="<?= e(number_format((float) $t['vat_rate'], 0)) ?>"></div>
    <div class="field"><label>Email για νέες παραγγελίες</label><input type="email" name="order_email" value="<?= e($n['order_email']) ?>"></div>
    <label class="check"><input type="checkbox" name="notify_admin_new_order" value="1"<?= $n['notify_admin_new_order'] ? ' checked' : '' ?>> Ειδοποίηση για κάθε νέα παραγγελία</label>
    <label class="check"><input type="checkbox" name="notify_customer" value="1"<?= $n['notify_customer'] ? ' checked' : '' ?>> Email επιβεβαίωσης στον πελάτη</label>
  </div><div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div></div>
</form>
