<div class="page-head"><div><h1>Ο λογαριασμός μου</h1><p><?= e($me['email']) ?></p></div></div>
<form method="post" action="/superadmin/account" class="card" style="max-width:520px"><?= csrf_field() ?><div class="hd"><h2>Αλλαγή κωδικού</h2></div>
  <div class="bd form"><div class="field"><label>Τρέχων κωδικός</label><input type="password" name="current" required autocomplete="current-password"></div><div class="field"><label>Νέος κωδικός</label><input type="password" name="password" minlength="10" required autocomplete="new-password"></div><div class="field"><label>Επανάληψη</label><input type="password" name="password2" minlength="10" required autocomplete="new-password"></div></div>
  <div class="ft"><button class="btn btn-primary">Αλλαγή κωδικού</button></div></form>
