<div class="page-head">
  <div><h1>Πίνακας ελέγχου</h1><p>Επισκόπηση όλων των e-shops της πλατφόρμας.</p></div>
  <div class="actions"><a class="btn btn-primary" href="/superadmin/shops/new"><?= icon('plus') ?>Νέο κατάστημα</a></div>
</div>
<div class="grid g4">
  <div class="card stat"><span class="lbl"><?= icon('store') ?>Σύνολο e-shops</span><span class="val"><?= $kpi['total'] ?></span><span class="sub"><?= $kpi['pending'] ?> σε αναμονή</span></div>
  <div class="card stat ok"><span class="lbl">Ενεργά</span><span class="val"><?= $kpi['active'] ?></span><span class="sub">MRR ≈ <?= money($mrr) ?></span></div>
  <div class="card stat <?= $kpi['locked'] ? 'bad' : '' ?>"><span class="lbl"><?= icon('lock') ?>Κλειδωμένα / ληγμένα</span><span class="val"><?= $kpi['locked'] ?></span><span class="sub"><?= $kpi['suspended'] ?> σε αναστολή</span></div>
  <div class="card stat <?= $kpi['expiring'] ? 'warn' : '' ?>"><span class="lbl"><?= icon('clock') ?>Λήγουν σε ≤ 10 ημέρες</span><span class="val"><?= $kpi['expiring'] ?></span><a class="sub" href="/superadmin/shops?status=expiring_soon">Προβολή</a></div>
  <div class="card stat"><span class="lbl"><?= icon('box') ?>Ενεργά προϊόντα (δίκτυο)</span><span class="val num"><?= number_format($kpi['active_products'], 0, ',', '.') ?></span></div>
  <div class="card stat"><span class="lbl"><?= icon('archive') ?>Αποθηκευμένα προϊόντα</span><span class="val num"><?= number_format($kpi['stored_products'], 0, ',', '.') ?></span></div>
  <div class="card stat"><span class="lbl"><?= icon('receipt') ?>Εκκρεμείς πληρωμές</span><span class="val num"><?= money($pendingPayments) ?></span><a class="sub" href="/superadmin/payments?status=pending">Προβολή</a></div>
  <div class="card stat"><span class="lbl"><?= icon('refresh') ?>Τελευταίο cron</span><span class="val" style="font-size:16px"><?= $cronLast ? e(fmt_date($cronLast, true)) : 'Ποτέ' ?></span><a class="sub" href="/superadmin/settings">Ρυθμίσεις cron</a></div>
</div>

<?php if ($expiring): ?>
<div class="card">
  <div class="hd"><div><h2>Λήξεις συνδρομών</h2><p>Ειδοποιήσεις στάλθηκαν στις 10, 3 και 1 ημέρα πριν τη λήξη.</p></div></div>
  <div class="bd flush table-wrap"><table class="table">
    <thead><tr><th>Κατάστημα</th><th>Πακέτο</th><th>Λήξη</th><th>Υπόλοιπο</th><th>Κατάσταση</th><th></th></tr></thead>
    <tbody><?php foreach ($expiring as $s): ?>
      <tr><td><a class="title" href="/superadmin/shops/<?= $s['id'] ?>"><?= e($s['name']) ?></a><div class="sub"><?= e($s['domain']) ?></div></td><td><?= e($s['plan_name']) ?></td>
      <td class="num"><?= fmt_date($s['end_date']) ?></td><td><span class="badge <?= $s['days_left'] < 0 ? 'b-bad' : ($s['days_left'] <= 3 ? 'b-bad' : 'b-warn') ?>"><?= $s['days_left'] < 0 ? 'Έληξε' : $s['days_left'] . ' ημ.' ?></span></td>
      <td><?= status_badge($s["status"]) ?></td><td class="actions"><a class="btn btn-sm" href="/superadmin/shops/<?= $s['id'] ?>#subscription">Ανανέωση</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<div class="split">
  <div class="card">
    <div class="hd"><h2>Καταστήματα</h2><a href="/superadmin/shops">Όλα →</a></div>
    <div class="bd flush table-wrap"><?php $rows = $shops; include __DIR__ . '/_shops_table.php'; ?></div>
  </div>
  <div class="card">
    <div class="hd"><h2>Πρόσφατες ειδοποιήσεις</h2><a href="/superadmin/notifications">Όλες →</a></div>
    <div class="bd">
      <?php if (!$notifs): ?><p class="muted">Δεν υπάρχουν ειδοποιήσεις.</p><?php endif; ?>
      <ul class="timeline"><?php foreach ($notifs as $n): ?><li><time><?= fmt_date($n['created_at'], true) ?></time><div><b><?= e($n['title']) ?></b><?php if (!$n['read_at']): ?> <span class="badge b-info plain">νέα</span><?php endif; ?><div class="muted"><?= e(excerpt($n['body'], 120)) ?></div></div></li><?php endforeach; ?></ul>
    </div>
  </div>
</div>
