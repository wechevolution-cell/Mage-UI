<div>
  <h1>Εγκατάσταση πλατφόρμας</h1>
  <p class="muted">Οδηγός πρώτης εκτέλεσης. Δεν υπάρχουν προεπιλεγμένοι κωδικοί: δημιουργείτε εδώ τον πρώτο Super Admin.</p>
</div>
<div class="card"><div class="bd">
  <h3 style="margin-bottom:8px">Απαιτήσεις συστήματος</h3>
  <ul class="checks"><?php foreach ($reqs as [$label, $pass]): ?><li class="<?= $pass ? 'pass' : '' ?>"><?= e($label) ?></li><?php endforeach; ?></ul>
</div></div>
<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="field">
    <label for="platform_hosts">Host(s) πίνακα πλατφόρμας</label>
    <input id="platform_hosts" name="platform_hosts" required value="<?= e(old('platform_hosts', host_without_port(req_host()))) ?>">
    <span class="hint">Το domain όπου θα λειτουργεί ο πίνακας Super Admin (π.χ. panel.wechevo.com). Τα καταστήματα συνδέονται με τα δικά τους domains.</span>
  </div>
  <div class="fg">
    <div class="field"><label for="name">Όνομα</label><input id="name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
    <div class="field"><label for="password">Κωδικός</label><input id="password" type="password" name="password" required minlength="10" autocomplete="new-password"><span class="hint">Τουλάχιστον 10 χαρακτήρες, γράμματα και αριθμοί.</span></div>
    <div class="field"><label for="password2">Επανάληψη κωδικού</label><input id="password2" type="password" name="password2" required minlength="10" autocomplete="new-password"></div>
    <div class="field"><label for="notify_email">Email ειδοποιήσεων</label><input id="notify_email" type="email" name="notify_email" value="<?= e(old('notify_email')) ?>"><span class="hint">Λήξεις συνδρομών, κλειδώματα, εισαγωγές.</span></div>
    <div class="field"><label for="timezone">Ζώνη ώρας</label><input id="timezone" name="timezone" value="<?= e(old('timezone', 'Europe/Athens')) ?>"></div>
  </div>
  <button class="btn btn-primary btn-lg"<?= $ok ? '' : ' disabled' ?>>Ολοκλήρωση εγκατάστασης</button>
</form>
