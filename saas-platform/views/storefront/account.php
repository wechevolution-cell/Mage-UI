<div class="wrap page-pad account">
<?php if ($mode === 'dashboard'): ?>
  <div class="acct-head"><h1 class="page-title">Γεια σας, <?= e($customer['first_name'] ?: $customer['email']) ?></h1><form method="post" action="/account/logout/"><?= csrf_field() ?><button class="btn btn--ghost"><?= icon('logout') ?>Αποσύνδεση</button></form></div>
  <div class="acct-grid">
    <section class="summary"><h2>Οι παραγγελίες μου</h2>
      <?php if (!$orders): ?><p class="muted">Δεν έχετε παραγγελίες ακόμη.</p><?php else: ?>
      <table class="tbl"><thead><tr><th>Αριθμός</th><th>Ημερομηνία</th><th>Κατάσταση</th><th class="r">Σύνολο</th></tr></thead><tbody><?php foreach ($orders as $o): ?><tr><td><a href="/account/orders/<?= e($o['number']) ?>/"><?= e($o['number']) ?></a></td><td data-label="Ημερομηνία"><?= fmt_date($o['created_at']) ?></td><td data-label="Κατάσταση"><?= e(status_label($o['status'])) ?></td><td class="r" data-label="Σύνολο"><?= money((float) $o['total']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    </section>
    <section class="summary" id="addresses"><h2>Διευθύνσεις</h2>
      <?php foreach ($addresses as $a): ?><div class="addr"><p><?= e($a['first_name'] . ' ' . $a['last_name']) ?><br><?= e($a['address']) ?>, <?= e($a['postcode']) ?> <?= e($a['city']) ?><?= $a['is_default'] ? ' <span class="tag">Προεπιλογή</span>' : '' ?></p><form method="post" action="/account/addresses/"><?= csrf_field() ?><input type="hidden" name="delete" value="<?= (int) $a['id'] ?>"><button class="link-btn">Διαγραφή</button></form></div><?php endforeach; ?>
      <details><summary class="btn btn--ghost">Νέα διεύθυνση</summary><form method="post" action="/account/addresses/" class="fgrid"><?= csrf_field() ?>
        <div class="field"><label for="a-fn">Όνομα</label><input id="a-fn" name="first_name" value="<?= e($customer['first_name']) ?>"></div><div class="field"><label for="a-ln">Επώνυμο</label><input id="a-ln" name="last_name" value="<?= e($customer['last_name']) ?>"></div>
        <div class="field full"><label for="a-ad">Διεύθυνση</label><input id="a-ad" name="address" required></div><div class="field"><label for="a-ci">Πόλη</label><input id="a-ci" name="city" required></div><div class="field"><label for="a-pc">Τ.Κ.</label><input id="a-pc" name="postcode"></div>
        <button class="btn btn--primary">Αποθήκευση</button></form></details>
    </section>
    <section class="summary"><h2>Στοιχεία</h2><dl class="dl"><dt>Email</dt><dd><?= e($customer['email']) ?></dd><dt>Τηλέφωνο</dt><dd><?= e($customer['phone'] ?: '—') ?></dd></dl><a class="btn btn--ghost" href="/wishlist/"><?= icon('heart') ?>Αγαπημένα</a></section>
  </div>
<?php else: ?>
  <div class="auth-box">
  <?php if ($mode === 'login'): ?>
    <h1 class="page-title">Σύνδεση</h1>
    <form method="post" action="/account/login/" class="fgrid one"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e(in_str('next')) ?>">
      <div class="field"><label for="l-email">Email</label><input id="l-email" type="email" name="email" required autocomplete="username"></div>
      <div class="field"><label for="l-pass">Κωδικός</label><input id="l-pass" type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn--primary btn--block">Σύνδεση</button>
      <p><a href="/account/forgot/">Ξεχάσατε τον κωδικό;</a> · <a href="/account/register/">Δημιουργία λογαριασμού</a></p>
    </form>
  <?php elseif ($mode === 'register'): ?>
    <h1 class="page-title">Δημιουργία λογαριασμού</h1>
    <form method="post" action="/account/register/" class="fgrid"><?= csrf_field() ?><input type="hidden" name="_ts" value="<?= (int) ($_SESSION['form_ts'] ?? time()) ?>"><div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="field"><label for="r-fn">Όνομα</label><input id="r-fn" name="first_name" autocomplete="given-name" value="<?= e(old('first_name')) ?>"></div><div class="field"><label for="r-ln">Επώνυμο</label><input id="r-ln" name="last_name" autocomplete="family-name" value="<?= e(old('last_name')) ?>"></div>
      <div class="field full"><label for="r-email">Email *</label><input id="r-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
      <div class="field full"><label for="r-phone">Τηλέφωνο</label><input id="r-phone" type="tel" name="phone" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
      <div class="field full"><label for="r-pass">Κωδικός * (≥ 8 χαρακτήρες)</label><input id="r-pass" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
      <label class="check full"><input type="checkbox" name="newsletter" value="1"> Θέλω να λαμβάνω ενημερώσεις</label>
      <label class="check full"><input type="checkbox" name="accept" value="1" required> <span>Αποδέχομαι τους <a href="/terms/">Όρους</a> και την <a href="/privacy/">Πολιτική απορρήτου</a> *</span></label>
      <button class="btn btn--primary btn--block full">Δημιουργία λογαριασμού</button>
    </form>
  <?php elseif ($mode === 'forgot'): ?>
    <h1 class="page-title">Επαναφορά κωδικού</h1>
    <form method="post" action="/account/forgot/" class="fgrid one"><?= csrf_field() ?><div class="field"><label for="f-email">Email</label><input id="f-email" type="email" name="email" required></div><button class="btn btn--primary btn--block">Αποστολή συνδέσμου</button></form>
  <?php else: ?>
    <h1 class="page-title">Νέος κωδικός</h1>
    <form method="post" action="/account/reset/" class="fgrid one"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="email" value="<?= e($email) ?>"><div class="field"><label for="n-pass">Νέος κωδικός</label><input id="n-pass" type="password" name="password" required minlength="8" autocomplete="new-password"></div><button class="btn btn--primary btn--block">Αποθήκευση</button></form>
  <?php endif; ?>
  </div>
<?php endif; ?>
</div>
