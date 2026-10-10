<div><h1>Σύνδεση Super Admin</h1><p class="muted">Πίνακας διαχείρισης πλατφόρμας.</p></div>
<form method="post" action="/superadmin/login" class="form">
  <?= csrf_field() ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="username" autofocus></div>
  <div class="field"><label for="password">Κωδικός</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary btn-lg">Σύνδεση</button>
  <p class="muted" style="font-size:12px">Μετά από 5 αποτυχημένες προσπάθειες ο λογαριασμός κλειδώνει για 15 λεπτά.</p>
</form>
