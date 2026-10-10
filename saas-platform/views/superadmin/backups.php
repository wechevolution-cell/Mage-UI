<div class="page-head"><div><h1>Αντίγραφα ασφαλείας</h1><p>Αυτόματα αντίγραφα δημιουργούνται πριν από κάθε οριστική διαγραφή. Κατεβάστε και αποθηκεύστε τα εκτός server.</p></div>
  <form method="post" action="/superadmin/backups"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('archive') ?>Αντίγραφο πλατφόρμας</button></form></div>
<div class="card"><div class="hd"><h2>Πλατφόρμα (βάση ελέγχου + ρυθμίσεις)</h2></div><div class="bd flush table-wrap"><table class="table"><tbody>
<?php if (!$platform): ?><tr><td class="empty">Δεν υπάρχουν αντίγραφα πλατφόρμας.</td></tr><?php endif; ?>
<?php foreach ($platform as $b): ?><tr><td><code><?= e($b['name']) ?></code></td><td class="num"><?= number_format($b['size'] / 1048576, 2, ',', '.') ?> MB</td><td class="num"><?= date('d/m/Y H:i', $b['time']) ?></td><td class="actions"><a class="btn btn-sm" href="/superadmin/backups/<?= e($b['name']) ?>">Λήψη</a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php foreach ($shopBackups as $g): ?>
<div class="card"><div class="hd"><h2><?= e($g['shop']['name']) ?></h2></div><div class="bd flush table-wrap"><table class="table"><tbody>
<?php foreach ($g['items'] as $b): ?><tr><td><code><?= e($b['name']) ?></code></td><td class="num"><?= number_format($b['size'] / 1048576, 2, ',', '.') ?> MB</td><td class="num"><?= date('d/m/Y H:i', $b['time']) ?></td><td class="actions"><a class="btn btn-sm" href="/superadmin/shops/<?= (int) $g['shop']['id'] ?>/backups/<?= e($b['name']) ?>">Λήψη</a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endforeach; ?>
