<div class="page-head"><div><h1>Πακέτα</h1><p>Τιμές, όρια και λειτουργίες αποθηκεύονται στη βάση — καμία τιμή δεν είναι «καρφωμένη» στον κώδικα.</p></div>
  <div class="actions"><a class="btn btn-primary" href="/superadmin/plans/new"><?= icon('plus') ?>Νέο πακέτο</a></div></div>
<div class="card"><div class="bd flush table-wrap"><table class="table">
  <thead><tr><th>Πακέτο</th><th class="r">Τιμή / μήνα</th><th class="r">Setup</th><th class="r">Ενεργά προϊόντα</th><th class="r">Αποθηκευμένα</th><th class="r">Χρήστες</th><th>Διάρκειες</th><th>Καταστήματα</th><th>Κατάσταση</th><th></th></tr></thead>
  <tbody><?php foreach ($plans as $p): ?>
    <tr><td><a class="title" href="/superadmin/plans/<?= $p['id'] ?>"><?= e($p['name']) ?></a><div class="sub"><?= e($p['slug']) ?></div></td>
    <td class="r num"><?= money((float) $p['price']) ?></td><td class="r num"><?= money((float) $p['setup_fee']) ?></td><td class="r num"><b><?= (int) $p['active_product_limit'] ?></b></td>
    <td class="r num"><?= number_format((int) $p['stored_product_limit'], 0, ',', '.') ?></td><td class="r num"><?= (int) $p['staff_limit'] ?></td>
    <td><?= e(implode(', ', Plans::durations($p))) ?> μήνες</td><td class="num"><?= $p['shops'] ?></td><td><?= status_badge($p['status']) ?></td>
    <td class="actions"><a class="btn btn-sm" href="/superadmin/plans/<?= $p['id'] ?>"><?= icon('edit') ?>Επεξεργασία</a></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
