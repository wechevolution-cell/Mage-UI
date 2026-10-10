<div class="page-head"><div><h1>Παραγγελίες</h1></div><div class="actions"><a class="btn" href="/admin/orders/export.csv?<?= e(http_build_query($_GET)) ?>"><?= icon('download') ?>Εξαγωγή CSV</a></div></div>
<nav class="tabs"><a href="/admin/orders"<?= in_str('status') === '' ? ' aria-current="page"' : '' ?>>Όλες (<?= array_sum($counts) ?>)</a><?php foreach (Orders::STATUSES as $s): if (empty($counts[$s])) continue; ?><a href="/admin/orders?status=<?= $s ?>"<?= in_str('status') === $s ? ' aria-current="page"' : '' ?>><?= e(status_label($s)) ?> (<?= $counts[$s] ?>)</a><?php endforeach; ?></nav>
<div class="card">
  <form class="toolbar" method="get"><input type="hidden" name="status" value="<?= e(in_str('status')) ?>"><input type="search" name="q" value="<?= e(in_str('q')) ?>" placeholder="Αριθμός, email, επώνυμο, τηλέφωνο" aria-label="Αναζήτηση">
    <select name="payment" data-autosubmit aria-label="Πληρωμή"><option value="">Κάθε πληρωμή</option><?php foreach (Orders::PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>"<?= in_str('payment') === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select>
    <input type="date" name="from" value="<?= e(in_str('from')) ?>" style="width:auto" aria-label="Από"><input type="date" name="to" value="<?= e(in_str('to')) ?>" style="width:auto" aria-label="Έως"><button class="btn">Φίλτρο</button></form>
  <div class="table-wrap"><?php if (!$rows): ?><div class="empty"><h3>Δεν βρέθηκαν παραγγελίες</h3></div><?php else: ?>
  <table class="table"><thead><tr><th>Αριθμός</th><th>Ημερομηνία</th><th>Πελάτης</th><th>Τεμ.</th><th>Πληρωμή</th><th>Κατάσταση</th><th>Αποστολή</th><th class="r">Σύνολο</th></tr></thead><tbody>
  <?php foreach ($rows as $o): ?><tr><td><a class="title" href="/admin/orders/<?= $o['id'] ?>"><?= e($o['number']) ?></a></td><td class="num"><?= fmt_date($o['created_at'], true) ?></td><td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><div class="sub"><?= e($o['email']) ?></div></td><td class="num"><?= (int) $o['items'] ?></td>
  <td><?= e($o['payment_method'] === 'cod' ? 'Αντικαταβολή' : 'Κατάθεση') ?><div><?= status_badge($o['payment_status']) ?></div></td><td><?= status_badge($o['status']) ?></td><td><?= status_badge($o['shipping_status']) ?></td><td class="r num"><b><?= money((float) $o['total']) ?></b></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?></div>
  <?php include __DIR__ . '/_pager.php'; ?>
</div>
