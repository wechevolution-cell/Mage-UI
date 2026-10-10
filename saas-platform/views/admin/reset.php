<div><h1>Νέος κωδικός</h1></div>
<form method="post" action="/admin/reset" class="form"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="email" value="<?= e($email) ?>">
  <div class="field"><label for="password">Νέος κωδικός</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="hint">Τουλάχιστον 10 χαρακτήρες, γράμματα και αριθμοί.</span></div>
  <button class="btn btn-primary btn-lg">Αποθήκευση κωδικού</button>
</form>
