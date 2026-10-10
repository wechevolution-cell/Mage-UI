<div class="page-head"><div><h1>Ανακατευθύνσεις</h1><p>301, 302, 307, 308, 410, 451 — ακριβής αντιστοίχιση ή regex με $1. Προστασία από βρόχους και αλυσίδες.</p></div></div>
<form method="post" action="/admin/seo/redirects/save" class="card"><?= csrf_field() ?><div class="hd"><h2>Νέα ανακατεύθυνση</h2></div><div class="bd"><div class="fg" style="grid-template-columns:2fr 2fr 1fr 1fr">
  <div class="field"><label>Από (διαδρομή ή regex)</label><input name="source" required value="<?= e($prefill['source']) ?>" placeholder="/old-url/"></div>
  <div class="field"><label>Προς</label><input name="target" value="<?= e($prefill['target']) ?>" placeholder="/new-url/ ή https://…"></div>
  <div class="field"><label>Κωδικός</label><select name="code"><?php foreach (Redirects::CODES as $c => $l): ?><option value="<?= $c ?>"<?= $prefill['code'] === $c ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Αντιστοίχιση</label><select name="match_type"><option value="exact">Ακριβής</option><option value="regex">Regex</option></select></div>
</div></div><div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Από</th><th>Προς</th><th>Κωδικός</th><th>Τύπος</th><th class="r">Hits</th><th>Τελευταίο hit</th><th>Ενεργή</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty">Δεν υπάρχουν ανακατευθύνσεις.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td class="mono"><?= e($r['source']) ?><?= $r['note'] ? '<div class="sub">' . e($r['note']) . '</div>' : '' ?></td><td class="mono"><?= e($r['target'] ?? '—') ?></td><td><span class="badge plain"><?= (int) $r['code'] ?></span></td><td><?= e($r['match_type']) ?></td><td class="r num"><?= (int) $r['hits'] ?></td><td class="num"><?= fmt_date($r['last_hit_at'], true) ?></td><td><?= $r['active'] ? '✓' : '—' ?></td>
<td class="actions"><form method="post" action="/admin/seo/redirects/<?= $r['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" data-confirm="Διαγραφή ανακατεύθυνσης;">✕</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
