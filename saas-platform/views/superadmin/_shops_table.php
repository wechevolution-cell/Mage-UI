<?php if (!$rows): ?>
  <div class="empty"><h3>Δεν υπάρχουν καταστήματα</h3><p>Δημιουργήστε το πρώτο e-shop με τον οδηγό.</p><a class="btn btn-primary" href="/superadmin/shops/new">Νέο κατάστημα</a></div>
<?php else: ?>
<table class="table">
  <thead><tr><th>Κατάστημα</th><th>Πακέτο</th><th>Template</th><th>Ενεργά / όριο</th><th>Αποθηκευμένα</th><th>Έναρξη</th><th>Λήξη</th><th>Κατάσταση</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $s): $pct = $s['limit'] ? min(100, round($s['active_products'] / $s['limit'] * 100)) : 100; ?>
    <tr>
      <td><a class="title" href="/superadmin/shops/<?= $s['id'] ?>"><?= e($s['name']) ?></a><div class="sub"><?= e($s['domain'] ?? '—') ?></div></td>
      <td><?= e($s['plan_name'] ?? '—') ?></td>
      <td><span class="sub"><?= e($s['template_key']) ?></span></td>
      <td style="min-width:120px"><div class="num"><?= $s['active_products'] ?> / <?= $s['limit'] ?></div><div class="bar <?= $s['active_products'] >= $s['limit'] ? 'full' : ($pct >= 80 ? 'warn' : '') ?>" style="height:6px"><i style="width:<?= $pct ?>%"></i></div></td>
      <td class="num"><?= number_format($s['stored_products'], 0, ',', '.') ?></td>
      <td class="num"><?= fmt_date($s['start_date']) ?></td>
      <td class="num"><?= fmt_date($s['end_date']) ?><?php if ($s['days_left'] !== null && $s['days_left'] <= 10 && $s['days_left'] >= 0): ?><div class="sub"><?= $s['days_left'] ?> ημ.</div><?php endif; ?></td>
      <td><?= status_badge($s["status"]) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
