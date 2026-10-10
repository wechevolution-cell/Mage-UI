<div><h1>Σύνδεση διαχείρισης</h1><p class="muted">Διαχείριση του ηλεκτρονικού σας καταστήματος.</p></div>
<form method="post" action="/admin/login" class="form"><?= csrf_field() ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="username" autofocus></div>
  <div class="field"><label for="password">Κωδικός</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary btn-lg">Σύνδεση</button>
  <a href="/admin/forgot">Ξεχάσατε τον κωδικό;</a>
</form>
