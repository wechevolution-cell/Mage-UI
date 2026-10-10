<?php $addr = jd($o['shipping_address']); $bill = jd($o['billing']); ?>
<div class="page-head"><div><h1>Παραγγελία <?= e($o['number']) ?> <?= status_badge($o['status']) ?></h1><p><?= fmt_date($o['created_at'], true) ?> · <?= e($o['first_name'] . ' ' . $o['last_name']) ?></p></div><div class="actions"><button class="btn" onclick="window.print()"><?= icon('file') ?>Εκτύπωση</button></div></div>
<div class="split"><div class="grid">
  <div class="card"><div class="hd"><h2>Προϊόντα</h2></div><div class="bd flush table-wrap"><table class="table"><thead><tr><th>Προϊόν</th><th>SKU</th><th class="r">Τιμή</th><th class="r">Ποσ.</th><th class="r">Σύνολο</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?><tr><td><?= $it['product_id'] ? '<a href="/admin/products/' . (int) $it['product_id'] . '">' . e($it['name']) . '</a>' : e($it['name']) ?><?= $it['options'] ? '<div class="sub">' . e($it['options']) . '</div>' : '' ?></td><td class="mono sub"><?= e($it['sku']) ?></td><td class="r num"><?= money((float) $it['unit_price']) ?></td><td class="r num"><?= (int) $it['qty'] ?></td><td class="r num"><?= money((float) $it['total']) ?></td></tr><?php endforeach; ?>
    <tr><td colspan="4" class="r">Υποσύνολο</td><td class="r num"><?= money((float) $o['subtotal']) ?></td></tr>
    <tr><td colspan="4" class="r">Αποστολή (<?= e($o['shipping_method']) ?>)</td><td class="r num"><?= money((float) $o['shipping_cost']) ?></td></tr>
    <?php if ((float) $o['payment_fee'] > 0): ?><tr><td colspan="4" class="r">Χρέωση πληρωμής</td><td class="r num"><?= money((float) $o['payment_fee']) ?></td></tr><?php endif; ?>
    <?php if ($o['gift_wrap']): ?><tr><td colspan="4" class="r">Συσκευασία δώρου</td><td class="r num">✓</td></tr><?php endif; ?>
    <tr><td colspan="4" class="r"><b>Σύνολο</b> <span class="sub">(ΦΠΑ <?= money((float) $o['tax_total']) ?>)</span></td><td class="r num"><b><?= money((float) $o['total']) ?></b></td></tr>
  </tbody></table></div></div>
  <div class="card"><div class="hd"><h2>Ιστορικό</h2></div><div class="bd"><ul class="timeline"><?php foreach ($history as $h): ?><li><time><?= fmt_date($h['created_at'], true) ?></time><div><?= e($h['note']) ?><div class="sub"><?= e($h['actor']) ?><?= $h['notify_customer'] ? ' · ειδοποιήθηκε ο πελάτης' : '' ?></div></div></li><?php endforeach; ?></ul></div></div>
</div><div class="grid">
  <form method="post" action="/admin/orders/<?= $o['id'] ?>/update" class="card"><?= csrf_field() ?><div class="hd"><h2>Ενημέρωση</h2></div><div class="bd form">
    <div class="field"><label>Κατάσταση</label><select name="status"><?php foreach (Orders::STATUSES as $s): ?><option value="<?= $s ?>"<?= $o['status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Πληρωμή</label><select name="payment_status"><?php foreach (Orders::PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>"<?= $o['payment_status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Αποστολή</label><select name="shipping_status"><?php foreach (Orders::SHIPPING_STATUSES as $s): ?><option value="<?= $s ?>"<?= $o['shipping_status'] === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Αριθμός αποστολής (tracking)</label><input name="tracking_number" value="<?= e($o['tracking_number']) ?>"></div>
    <div class="field"><label>Σημείωση</label><textarea name="note" rows="3"></textarea></div>
    <label class="check"><input type="checkbox" name="notify" value="1"> Ειδοποίηση πελάτη με email</label>
    <p class="hint muted" style="font-size:12px">Σε ακύρωση/επιστροφή το απόθεμα επιστρέφει αυτόματα.</p>
  </div><div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div></form>
  <div class="card"><div class="hd"><h2>Πελάτης</h2></div><div class="bd"><dl class="kv">
    <dt>Όνομα</dt><dd><?= $customer ? '<a href="/admin/customers/' . (int) $customer['id'] . '">' . e($o['first_name'] . ' ' . $o['last_name']) . '</a>' : e($o['first_name'] . ' ' . $o['last_name']) ?></dd>
    <dt>Email</dt><dd><?= e($o['email']) ?></dd><dt>Τηλέφωνο</dt><dd><?= e($o['phone']) ?></dd>
    <dt>Διεύθυνση</dt><dd><?= e(trim(($addr['address'] ?? '') . ', ' . ($addr['postcode'] ?? '') . ' ' . ($addr['city'] ?? ''), ', ')) ?><div class="sub"><?= e($addr['zone_label'] ?? '') ?></div></dd>
    <dt>Πληρωμή</dt><dd><?= e($o['payment_method'] === 'cod' ? 'Αντικαταβολή' : 'Τραπεζική κατάθεση') ?></dd>
    <?php if (!empty($bill['invoice'])): ?><dt>Τιμολόγιο</dt><dd><?= e($bill['company']) ?><br>ΑΦΜ <?= e($bill['vat_number']) ?> · ΔΟΥ <?= e($bill['tax_office']) ?><br><?= e($bill['activity'] ?? '') ?></dd><?php endif; ?>
    <?php if ($o['customer_note']): ?><dt>Σχόλιο</dt><dd><?= nl2br(e($o['customer_note'])) ?></dd><?php endif; ?>
  </dl></div></div>
</div></div>
