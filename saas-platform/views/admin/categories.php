<div class="page-head"><div><h1>Κατηγορίες</h1><p>Απεριόριστες κατηγορίες και υποκατηγορίες, ανεξάρτητα από το πακέτο.</p></div><div class="actions"><a class="btn btn-primary" href="/admin/categories/new"><?= icon('plus') ?>Νέα κατηγορία</a></div></div>
<div class="card"><div class="table-wrap">
<?php if (!$rows): ?><div class="empty"><h3>Δεν υπάρχουν κατηγορίες</h3><p>Δημιουργήστε την πρώτη κατηγορία ή εισάγετε προϊόντα με πεδίο κατηγορίας «Γονική > Υπο».</p><a class="btn btn-primary" href="/admin/categories/new">Νέα κατηγορία</a></div><?php else: ?>
<table class="table"><thead><tr><th>Κατηγορία</th><th>URL</th><th class="r">Προϊόντα (ενεργά)</th><th>Κατάσταση</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $c): $n = $counts[(int) $c['id']] ?? null; ?><tr>
  <td style="padding-left:<?= 14 + (int) $c['depth'] * 22 ?>px"><?= $c['depth'] ? '<span class="muted">└ </span>' : '' ?><a class="title" href="/admin/categories/<?= $c['id'] ?>"><?= e($c['name']) ?></a></td>
  <td class="sub mono">/category/<?= e($c['path']) ?>/</td><td class="r num"><?= (int) ($n['n'] ?? 0) ?> (<?= (int) ($n['a'] ?? 0) ?>)</td><td><?= status_badge($c['status']) ?></td>
  <td class="actions"><a class="btn btn-sm" href="/admin/categories/<?= $c['id'] ?>"><?= icon('edit') ?></a>
    <form method="post" action="/admin/categories/<?= $c['id'] ?>/delete" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" data-confirm="Διαγραφή της κατηγορίας «<?= e($c['name']) ?>»; Τα προϊόντα δεν διαγράφονται.">✕</button></form></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?></div></div>
