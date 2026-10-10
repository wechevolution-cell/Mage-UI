<div class="page-head"><div><h1>Super Admins</h1><p>Λογαριασμοί με πλήρη πρόσβαση στην πλατφόρμα.</p></div></div>
<div class="split">
  <div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Όνομα</th><th>Email</th><th>Τελευταία σύνδεση</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="title"><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td class="num"><?= fmt_date($r['last_login_at'], true) ?></td>
  <td class="actions"><?php if ((int) $r['id'] !== (int) $me['id']): ?><form method="post" action="/superadmin/admins/<?= $r['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-sm btn-danger" data-confirm="Διαγραφή του Super Admin <?= e($r['email']) ?>;" data-confirm-type="ΔΙΑΓΡΑΦΗ">Διαγραφή</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
  <form method="post" action="/superadmin/admins" class="card"><?= csrf_field() ?><div class="hd"><h2>Νέος Super Admin</h2></div>
    <div class="bd form"><div class="field"><label>Όνομα</label><input name="name" required></div><div class="field"><label>Email</label><input type="email" name="email" required></div><div class="field"><label>Κωδικός</label><input type="password" name="password" minlength="10" required autocomplete="new-password"></div></div>
    <div class="ft"><button class="btn btn-primary">Δημιουργία</button></div></form>
</div>
