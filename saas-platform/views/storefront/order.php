<?php $addr = jd($order['shipping_address']); ?>
<div class="wrap page-pad order-page">
  <?php if ($fresh): ?><div class="order-hero"><span class="ok-ic"><?= icon('check') ?></span><h1>Ευχαριστούμε για την παραγγελία σας!</h1><p>Η παραγγελία <b><?= e($order['number']) ?></b> καταχωρήθηκε. Θα λάβετε email επιβεβαίωσης στο <?= e($order['email']) ?>.</p></div>
  <?php else: ?><h1 class="page-title">Παραγγελία <?= e($order['number']) ?></h1><?php endif; ?>
  <?php if ($order['payment_method'] === 'bank' && $order['payment_status'] !== 'paid'): ?><div class="notice notice--info"><b>Οδηγίες τραπεζικής κατάθεσης</b><br><?= nl2br(e($payments['bank']['instructions'] ?? '')) ?><br>Ποσό: <b><?= money((float) $order['total']) ?></b> · Αιτιολογία: <b><?= e($order['number']) ?></b></div><?php endif; ?>
  <div class="order-grid">
    <section class="summary"><h2>Προϊόντα</h2>
      <ul class="sum-lines"><?php foreach ($items as $it): ?><li><span class="sl-name"><?= e($it['name']) ?><?php if ($it['options']): ?><small><?= e($it['options']) ?></small><?php endif; ?></span><span>× <?= (int) $it['qty'] ?></span><span><?= money((float) $it['total']) ?></span></li><?php endforeach; ?></ul>
      <div class="row"><span>Υποσύνολο</span><b><?= money((float) $order['subtotal']) ?></b></div>
      <div class="row"><span>Αποστολή</span><span><?= (float) $order['shipping_cost'] > 0 ? money((float) $order['shipping_cost']) : 'Δωρεάν' ?></span></div>
      <?php if ((float) $order['payment_fee'] > 0): ?><div class="row"><span>Χρέωση πληρωμής</span><span><?= money((float) $order['payment_fee']) ?></span></div><?php endif; ?>
      <div class="row total"><span>Σύνολο</span><b><?= money((float) $order['total']) ?></b></div>
    </section>
    <section class="summary"><h2>Στοιχεία</h2><dl class="dl">
      <dt>Κατάσταση</dt><dd><?= e(status_label($order['status'])) ?></dd><dt>Πληρωμή</dt><dd><?= e($order['payment_method'] === 'cod' ? ($payments['cod']['label'] ?? 'Αντικαταβολή') : ($payments['bank']['label'] ?? 'Τραπεζική κατάθεση')) ?> · <?= e(status_label($order['payment_status'])) ?></dd>
      <dt>Αποστολή</dt><dd><?= e($order['shipping_method']) ?><?= $order['tracking_number'] ? '<br>Αρ. αποστολής: ' . e($order['tracking_number']) : '' ?></dd>
      <?php if (!empty($addr['address'])): ?><dt>Διεύθυνση</dt><dd><?= e($order['first_name'] . ' ' . $order['last_name']) ?><br><?= e($addr['address']) ?>, <?= e($addr['postcode']) ?> <?= e($addr['city']) ?></dd><?php endif; ?>
    </dl>
    <?php if (!empty($history)): ?><h3>Ιστορικό</h3><ul class="timeline"><?php foreach ($history as $h): ?><li><time><?= fmt_date($h['created_at'], true) ?></time> <?= e($h['note']) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
  </div>
  <p><a class="btn btn--primary" href="/products/">Συνέχεια αγορών</a><?php if ($customer): ?> <a class="btn btn--ghost" href="/account/">Οι παραγγελίες μου</a><?php endif; ?></p>
</div>
