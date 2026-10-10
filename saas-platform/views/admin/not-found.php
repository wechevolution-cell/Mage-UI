<div class="page-head"><div><h1>Παρακολούθηση 404</h1><p>Σελίδες που δεν βρέθηκαν (αγνοείται θόρυβος scanners όπως wp-login, .env). Δημιουργήστε ανακατεύθυνση με ένα κλικ.</p></div>
  <form method="post" action="/admin/seo/404/action"><?= csrf_field() ?><button class="btn" name="do" value="cleanup" data-confirm="Καθαρισμός εγγραφών με 1 hit και αγνοημένων;">Καθαρισμός</button></form></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Διαδρομή</th><th class="r">Hits</th><th>Referrer</th><th>Τελευταίο</th><th>Ενέργεια</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="5" class="empty">Δεν καταγράφηκαν 404.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td class="mono"><?= e($r['path']) ?></td><td class="r num"><?= (int) $r['hits'] ?></td><td class="sub" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($r['referrer'] ?? '—') ?></td><td class="num"><?= fmt_date($r['last_hit_at'], true) ?></td>
<td><form method="post" action="/admin/seo/404/action" class="row-flex"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input name="target" value="<?= e($r['suggest'] ?? '/') ?>" style="max-width:220px" aria-label="Προορισμός">
  <button class="btn btn-sm" name="do" value="301">301</button><button class="btn btn-sm" name="do" value="302">302</button><button class="btn btn-sm" name="do" value="410">410</button><button class="btn btn-sm btn-ghost" name="do" value="ignore">Αγνόηση</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
