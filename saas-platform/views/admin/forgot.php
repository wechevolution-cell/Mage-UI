<div><h1>Επαναφορά κωδικού</h1><p class="muted">Θα σας στείλουμε σύνδεσμο για να ορίσετε νέο κωδικό.</p></div>
<form method="post" action="/admin/forgot" class="form"><?= csrf_field() ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="username"></div>
  <button class="btn btn-primary btn-lg">Αποστολή συνδέσμου</button><a href="/admin/login">Επιστροφή στη σύνδεση</a>
</form>
