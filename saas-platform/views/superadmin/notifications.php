<div class="page-head"><div><h1>Ειδοποιήσεις</h1><p>Λήξεις (10/3/1 ημέρες), λήξεις συνδρομών, κλειδώματα, ανανεώσεις, εισαγωγές.</p></div>
  <form method="post" action="/superadmin/notifications/read"><?= csrf_field() ?><button class="btn">Σήμανση όλων ως αναγνωσμένων</button></form></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Πότε</th><th>Τύπος</th><th>Ειδοποίηση</th><th>Κατάστημα</th><th>Email</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="5" class="empty">Δεν υπάρχουν ειδοποιήσεις.</td></tr><?php endif; ?>
<?php foreach ($rows as $n): ?><tr<?= !$n['read_at'] ? ' style="background:#F5F8FF"' : '' ?>><td class="num"><?= fmt_date($n['created_at'], true) ?></td><td><span class="badge plain"><?= e($n['type']) ?></span></td><td><b><?= e($n['title']) ?></b><div class="sub"><?= e($n['body']) ?></div></td><td><?= $n['shop_id'] ? '<a href="/superadmin/shops/' . (int) $n['shop_id'] . '">' . e($n['shop_name']) . '</a>' : '—' ?></td><td><?= $n['emailed_at'] ? '<span class="badge b-ok">Στάλθηκε</span>' : '<span class="badge b-muted">—</span>' ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
