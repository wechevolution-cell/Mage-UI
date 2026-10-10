<?php
$id = (int) $shop['id'];
$a = '/superadmin/shops/' . $id . '/';
$days = $sub ? days_until($sub['end_date']) : null;
$qpct = $quota['pct'];
$primary = $domains[0]['domain'] ?? '';
?>
<div class="page-head">
  <div>
    <h1><?= e($shop['name']) ?> <?= status_badge($shop['status']) ?></h1>
    <p><a href="<?= e($shopUrl) ?>" target="_blank" rel="noopener"><?= e($primary) ?></a> · <?= e($plan['name'] ?? 'Χωρίς πακέτο') ?> · Template <b><?= e(Templates::manifest($shop['template_key'])['name'] ?? $shop['template_key']) ?></b></p>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e($shopUrl) ?>" target="_blank" rel="noopener"><?= icon('eye') ?>Προβολή καταστήματος</a>
    <form method="post" action="<?= $a ?>impersonate"><?= csrf_field() ?><button class="btn btn-primary" data-confirm="Θα συνδεθείτε στον πίνακα του καταστήματος ως διαχειριστής (λειτουργία υποστήριξης). Η ενέργεια καταγράφεται."><?= icon('key') ?>Σύνδεση ως Shop Admin</button></form>
  </div>
</div>

<?php if ($shop['quota_mode'] === 'over_limit'): ?>
  <div class="alert warn"><?= icon('alert') ?><div><b>Υπέρβαση ορίου μετά από αλλαγή πακέτου.</b> Το κατάστημα έχει <?= $quota['active'] ?> ενεργά προϊόντα με όριο <?= $quota['limit'] ?>. Το storefront εμφανίζει μόνο <?= $quota['limit'] ?>· ο διαχειριστής καλείται να επιλέξει ποια θα παραμείνουν ενεργά.</div></div>
<?php endif; ?>

<div class="grid g4">
  <div class="card stat <?= $quota['active'] >= $quota['limit'] ? 'warn' : '' ?>"><span class="lbl">Ενεργά προϊόντα</span><span class="val num"><?= $quota['active'] ?> / <?= $quota['limit'] ?></span><div class="bar <?= $quota['active'] >= $quota['limit'] ? 'full' : ($qpct >= 80 ? 'warn' : '') ?>"><i style="width:<?= $qpct ?>%"></i></div></div>
  <div class="card stat"><span class="lbl">Αποθηκευμένα</span><span class="val num"><?= number_format($quota['stored'], 0, ',', '.') ?></span><span class="sub">όριο <?= number_format($quota['stored_limit'], 0, ',', '.') ?> · <?= $quota['inactive'] ?> ανενεργά</span></div>
  <div class="card stat <?= $days !== null && $days <= 10 ? ($days < 0 ? 'bad' : 'warn') : '' ?>"><span class="lbl">Λήξη συνδρομής</span><span class="val" style="font-size:20px"><?= $sub ? fmt_date($sub['end_date']) : '—' ?></span><span class="sub"><?= $days === null ? '' : ($days < 0 ? 'Έληξε πριν ' . abs($days) . ' ημέρες' : $days . ' ημέρες απομένουν') ?></span></div>
  <div class="card stat"><span class="lbl">Παραγγελίες</span><span class="val num"><?= $orders ?></span><span class="sub"><?= money($revenue) ?> · <?= $customers ?> πελάτες</span></div>
</div>

<div data-tabs-scope>
<div class="tabs" role="tablist" data-tabs>
  <button role="tab" data-tab="overview">Στοιχεία</button><button role="tab" data-tab="subscription">Συνδρομή & πακέτο</button><button role="tab" data-tab="domains">Domains</button>
  <button role="tab" data-tab="access">Πρόσβαση & λειτουργίες</button><button role="tab" data-tab="template">Template</button><button role="tab" data-tab="history">Ιστορικό</button>
  <button role="tab" data-tab="payments">Πληρωμές</button><button role="tab" data-tab="backups">Αντίγραφα</button><button role="tab" data-tab="danger">Ζώνη κινδύνου</button>
</div>

<section data-tab-panel="overview" class="grid g2" style="margin-top:16px">
  <form method="post" action="<?= $a ?>update" class="card"><?= csrf_field() ?><input type="hidden" name="_tab" value="overview">
    <div class="hd"><h2>Στοιχεία πελάτη</h2></div>
    <div class="bd form"><div class="fg">
      <div class="field"><label>Επωνυμία καταστήματος</label><input name="name" required value="<?= e($shop['name']) ?>"></div>
      <div class="field"><label>Εταιρεία</label><input name="company_name" value="<?= e($shop['company_name']) ?>"></div>
      <div class="field"><label>Υπεύθυνος</label><input name="owner_name" value="<?= e($shop['owner_name']) ?>"></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($shop['email']) ?>"></div>
      <div class="field"><label>Τηλέφωνο</label><input name="phone" value="<?= e($shop['phone']) ?>"></div>
      <div class="field"><label>ΑΦΜ</label><input name="vat_number" value="<?= e($shop['vat_number']) ?>"></div>
      <div class="field full"><label>Διεύθυνση</label><input name="address" value="<?= e($shop['address']) ?>"></div>
      <div class="field"><label>Hosting</label><select name="hosting_status"><?php foreach (['pending' => 'Σε αναμονή', 'active' => 'Ενεργό', 'error' => 'Σφάλμα'] as $k => $l): ?><option value="<?= $k ?>"<?= $shop['hosting_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Σημειώσεις hosting</label><input name="hosting_notes" value="<?= e($shop['hosting_notes']) ?>"></div>
      <div class="field full"><label>Εσωτερικές σημειώσεις</label><textarea name="notes" rows="3"><?= e($shop['notes']) ?></textarea></div>
    </div></div>
    <div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div>
  </form>
  <div class="grid">
    <div class="card"><div class="hd"><h2>Κατάσταση καταστήματος</h2></div>
      <div class="bd form">
        <dl class="kv"><dt>Κατάσταση</dt><dd><?= status_badge($shop['status']) ?></dd><dt>Δημιουργήθηκε</dt><dd><?= fmt_date($shop['created_at'], true) ?></dd><dt>Χρήστες</dt><dd><?= $staff ?></dd><dt>Ιδιοκτήτης</dt><dd><?= e($owner['email'] ?? '—') ?></dd></dl>
        <form method="post" action="<?= $a ?>status" class="row-flex"><?= csrf_field() ?><input type="hidden" name="_tab" value="overview">
          <select name="status" style="width:auto"><option value="active">Ενεργό</option><option value="locked">Κλείδωμα (σελίδα «Θα επιστρέψουμε σύντομα»)</option><option value="suspended">Αναστολή</option><option value="pending">Σε αναμονή</option></select>
          <input name="note" placeholder="Αιτιολογία (προαιρετικό)" style="max-width:240px">
          <button class="btn" data-confirm="Αλλαγή κατάστασης καταστήματος; Τα δεδομένα δεν επηρεάζονται.">Εφαρμογή</button>
        </form>
        <p class="hint muted" style="font-size:12px">Στο κλείδωμα το storefront και ο πίνακας του πελάτη δείχνουν μόνο τη σελίδα κλειδώματος· όλα τα δεδομένα διατηρούνται.</p>
      </div></div>
    <form method="post" action="<?= $a ?>owner-password" class="card"><?= csrf_field() ?><input type="hidden" name="_tab" value="overview">
      <div class="hd"><h2>Επαναφορά κωδικού ιδιοκτήτη</h2></div>
      <div class="bd"><div class="field"><label>Νέος κωδικός για <?= e($owner['email'] ?? '') ?></label><input type="text" name="password" minlength="10" required autocomplete="off"></div></div>
      <div class="ft"><button class="btn" data-confirm="Αλλαγή κωδικού του ιδιοκτήτη;">Αλλαγή κωδικού</button></div>
    </form>
  </div>
</section>

<section data-tab-panel="subscription" class="grid g2" style="margin-top:16px" hidden>
  <div class="card"><div class="hd"><div><h2>Ανανέωση / μηδενισμός χρονομέτρου</h2><p>Επηρεάζει μόνο τη συνδρομή — ποτέ προϊόντα, παραγγελίες, ρυθμίσεις ή χρήστες.</p></div></div>
    <form method="post" action="<?= $a ?>renew" class="bd form"><?= csrf_field() ?><input type="hidden" name="_tab" value="subscription">
      <dl class="kv"><dt>Τρέχουσα περίοδος</dt><dd><?= $sub ? fmt_date($sub['start_date']) . ' → ' . fmt_date($sub['end_date']) . ' (' . (int) $sub['duration_months'] . ' μήνες)' : '—' ?></dd><dt>Κατάσταση συνδρομής</dt><dd><?= $sub ? status_badge($sub['status']) : '—' ?></dd></dl>
      <div class="fg">
        <div class="field"><label>Διάρκεια</label><select name="months"><?php foreach (Plans::durations($plan) as $m): ?><option value="<?= $m ?>"><?= $m ?> <?= $m === 1 ? 'μήνας' : 'μήνες' ?> — <?= money((float) ($plan['price'] ?? 0) * $m) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Τρόπος</label><select name="mode"><option value="extend">Επέκταση από την τρέχουσα λήξη</option><option value="reset">Μηδενισμός: νέα περίοδος από σήμερα</option></select></div>
        <div class="field full"><label class="check"><input type="checkbox" name="paid" value="1"> Η πληρωμή εισπράχθηκε</label></div>
        <div class="field full"><label>Σημείωση</label><input name="note" placeholder="π.χ. πληρωμή με κατάθεση 12/10"></div>
      </div>
      <button class="btn btn-primary"><?= icon('refresh') ?>Ανανέωση / Reset timer</button>
    </form></div>
  <div class="grid">
    <form method="post" action="<?= $a ?>plan" class="card"><?= csrf_field() ?><input type="hidden" name="_tab" value="subscription">
      <div class="hd"><div><h2>Αλλαγή πακέτου</h2><p>Αναβάθμιση ή υποβάθμιση. Τα προϊόντα δεν διαγράφονται ποτέ.</p></div></div>
      <div class="bd form">
        <div class="field"><label>Νέο πακέτο</label><select name="plan_id"><?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>"<?= (int) $p['id'] === (int) $shop['plan_id'] ? ' selected' : '' ?>><?= e($p['name']) ?> — <?= (int) $p['active_product_limit'] ?> ενεργά — <?= money((float) $p['price']) ?>/μήνα</option><?php endforeach; ?></select></div>
        <fieldset class="box"><legend>Αν τα ενεργά προϊόντα (<?= $quota['active'] ?>) ξεπερνούν το νέο όριο</legend>
          <label class="check"><input type="radio" name="downgrade_mode" value="shop_choose" checked> Ο διαχειριστής επιλέγει ποια μένουν ενεργά (το storefront δείχνει προσωρινά μόνο όσα επιτρέπει το όριο)</label>
          <label class="check"><input type="radio" name="downgrade_mode" value="deactivate_excess"> Απενεργοποίηση των πιο πρόσφατα ενεργοποιημένων τώρα (θα παραμείνουν αποθηκευμένα ως ανενεργά)</label>
        </fieldset>
        <div class="field"><label>Σημείωση</label><input name="note"></div>
      </div>
      <div class="ft"><button class="btn btn-primary" data-confirm="Αλλαγή πακέτου; Αν το νέο όριο είναι μικρότερο από τα ενεργά προϊόντα, θα εφαρμοστεί η επιλογή που ορίσατε. Κανένα προϊόν δεν διαγράφεται.">Αλλαγή πακέτου</button></div>
    </form>
    <form method="post" action="<?= $a ?>limit" class="card"><?= csrf_field() ?><input type="hidden" name="_tab" value="subscription">
      <div class="hd"><div><h2>Ειδικό όριο ενεργών προϊόντων</h2><p>Προαιρετική παράκαμψη του ορίου του πακέτου μόνο για αυτό το κατάστημα.</p></div></div>
      <div class="bd"><div class="field"><label>Μέγιστα ενεργά προϊόντα</label><input type="number" min="0" name="active_limit_override" value="<?= e($shop['active_limit_override']) ?>" placeholder="Κενό = όριο πακέτου (<?= (int) ($plan['active_product_limit'] ?? 0) ?>)"></div></div>
      <div class="ft"><button class="btn">Αποθήκευση</button></div>
    </form>
  </div>
</section>

<section data-tab-panel="domains" class="grid" style="margin-top:16px" hidden>
  <div class="card"><div class="hd"><div><h2>Domains</h2><p>Mapping domain → κατάστημα. Υποστηρίζονται example.gr και www.example.gr.</p></div></div>
    <div class="bd flush table-wrap"><table class="table">
      <thead><tr><th>Domain</th><th>Κύριο</th><th>DNS</th><th>SSL</th><th>Hosting</th><th></th></tr></thead>
      <tbody><?php foreach ($domains as $d): ?>
        <tr><td><a href="<?= e(Shops::url($id)) ?>" target="_blank" rel="noopener" class="title"><?= e($d['domain']) ?></a><div class="sub">Προστέθηκε <?= fmt_date($d['created_at']) ?></div></td>
        <td><?= $d['is_primary'] ? '<span class="badge b-ok">Κύριο</span>' : '' ?></td><td><?= status_badge($d['dns_status']) ?></td><td><?= status_badge($d['ssl_status']) ?></td><td><?= status_badge($d['hosting_status']) ?></td>
        <td class="actions">
          <form method="post" action="<?= $a ?>domain-update" class="row-flex" style="justify-content:flex-end"><?= csrf_field() ?><input type="hidden" name="_tab" value="domains"><input type="hidden" name="domain_id" value="<?= $d['id'] ?>">
            <select name="ssl_status" style="width:auto" aria-label="SSL"><?php foreach (['pending', 'active', 'error', 'not_required'] as $o): ?><option value="<?= $o ?>"<?= $d['ssl_status'] === $o ? ' selected' : '' ?>>SSL: <?= e(strip_tags(status_badge($o))) ?></option><?php endforeach; ?></select>
            <select name="hosting_status" style="width:auto" aria-label="Hosting"><?php foreach (['pending', 'active', 'error'] as $o): ?><option value="<?= $o ?>"<?= $d['hosting_status'] === $o ? ' selected' : '' ?>>Hosting: <?= e(strip_tags(status_badge($o))) ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm" name="do" value="status">Αποθήκευση</button>
            <button class="btn btn-sm" name="do" value="check">Έλεγχος DNS</button>
            <?php if (!$d['is_primary']): ?><button class="btn btn-sm" name="do" value="primary">Κύριο</button><button class="btn btn-sm btn-danger" name="do" value="delete" data-confirm="Αφαίρεση του domain <?= e($d['domain']) ?>;">Αφαίρεση</button><?php endif; ?>
          </form></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <form method="post" action="<?= $a ?>domain-add" class="ft" style="justify-content:flex-start"><?= csrf_field() ?><input type="hidden" name="_tab" value="domains">
      <input name="domain" placeholder="newdomain.gr" required style="max-width:280px" aria-label="Νέο domain"><label class="check"><input type="checkbox" name="primary" value="1"> Κύριο</label><button class="btn btn-primary">Προσθήκη domain</button></form>
  </div>
</section>

<section data-tab-panel="access" style="margin-top:16px" hidden>
  <form method="post" action="<?= $a ?>features" class="card"><?= csrf_field() ?><input type="hidden" name="_tab" value="access">
    <div class="hd"><div><h2>Πρόσβαση & λειτουργίες</h2><p>Ενεργοποίηση / απενεργοποίηση πρόσβασης. «Πακέτο» = προεπιλογή από το πακέτο <?= e($plan['name'] ?? '') ?>.</p></div></div>
    <div class="bd form">
      <label class="check"><input type="checkbox" name="admin_login_enabled" value="1"<?= $shop['admin_login_enabled'] ? ' checked' : '' ?>> <span><b>Πρόσβαση διαχειριστή καταστήματος</b><br><small class="muted">Αν απενεργοποιηθεί, ο πελάτης δεν μπορεί να συνδεθεί στο /admin/ (το κατάστημα παραμένει δημόσιο αν είναι ενεργό).</small></span></label>
      <div class="table-wrap"><table class="table"><thead><tr><th>Λειτουργία</th><th>Πακέτο</th><th>Ρύθμιση για αυτό το κατάστημα</th><th>Τελικό</th></tr></thead><tbody>
      <?php foreach (Plans::FEATURES as $k => $label): $ov = $overrides[$k] ?? 'inherit'; ?>
        <tr><td><?= e($label) ?></td><td><?= $planFeatures[$k] ? '<span class="badge b-ok">Ναι</span>' : '<span class="badge b-muted">Όχι</span>' ?></td>
        <td><select name="f_<?= $k ?>" style="width:auto"><option value="inherit"<?= $ov === 'inherit' ? ' selected' : '' ?>>Όπως το πακέτο</option><option value="on"<?= $ov === 'on' ? ' selected' : '' ?>>Ενεργό</option><option value="off"<?= $ov === 'off' ? ' selected' : '' ?>>Απενεργοποιημένο</option></select></td>
        <td><?= $features[$k] ? '<span class="badge b-ok">Ενεργό</span>' : '<span class="badge b-bad">Απενεργοποιημένο</span>' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    </div>
    <div class="ft"><button class="btn btn-primary">Αποθήκευση πρόσβασης</button></div>
  </form>
</section>

<section data-tab-panel="template" style="margin-top:16px" hidden>
  <form method="post" action="<?= $a ?>template" class="card"><?= csrf_field() ?><input type="hidden" name="_tab" value="template">
    <div class="hd"><div><h2>Template καταστήματος</h2><p>Η αλλαγή επηρεάζει μόνο την εμφάνιση. Προϊόντα, κατηγορίες, παραγγελίες, πελάτες, πολυμέσα και SEO μένουν ανέπαφα.</p></div></div>
    <div class="bd form">
      <div class="tpl-grid">
      <?php foreach ($templates as $t): $m = Templates::manifest($t['key']); $tk = $m['tokens'] ?? []; $allowed = Templates::isAllowed($t['key'], (int) $shop['plan_id']); ?>
        <label class="tpl"><input type="radio" name="template_key" value="<?= e($t['key']) ?>"<?= $shop['template_key'] === $t['key'] ? ' checked' : '' ?><?= !$allowed && $shop['template_key'] !== $t['key'] ? ' disabled' : '' ?>>
          <span class="sw"><span style="background:<?= e($tk['background'] ?? '#eee') ?>"></span><span style="background:<?= e($tk['primary'] ?? '#333') ?>"></span><span style="background:<?= e($tk['accent'] ?? '#999') ?>"></span><span style="background:<?= e($tk['surface'] ?? '#fff') ?>"></span></span>
          <span class="meta"><b><?= e($t['name']) ?></b><small class="muted"><?= e($t['industry']) ?></small><a href="/superadmin/templates/<?= e($t['key']) ?>/preview?shop=<?= $id ?>" target="_blank" rel="noopener" style="font-size:12px">Προεπισκόπηση με τα δεδομένα του καταστήματος</a></span></label>
      <?php endforeach; ?>
      </div>
      <fieldset class="box"><legend>Επιλογή template από τον πελάτη</legend>
        <label class="check"><input type="checkbox" name="allow_template_choice" value="1"<?= $shop['allow_template_choice'] ? ' checked' : '' ?>> Ο διαχειριστής του καταστήματος μπορεί να διαλέξει ανάμεσα στα επιτρεπόμενα templates</label>
        <div class="row-flex"><?php foreach ($templates as $t): ?><label class="check"><input type="checkbox" name="allowed_templates[]" value="<?= e($t['key']) ?>"<?= in_array($t['key'], $allowedTemplates, true) ? ' checked' : '' ?>> <?= e($t['name']) ?></label><?php endforeach; ?></div>
      </fieldset>
    </div>
    <div class="ft"><button class="btn btn-primary">Αποθήκευση template</button></div>
  </form>
</section>

<section data-tab-panel="history" class="grid g2" style="margin-top:16px" hidden>
  <div class="card"><div class="hd"><h2>Ιστορικό συνδρομής</h2></div><div class="bd flush table-wrap"><table class="table"><thead><tr><th>Ημερομηνία</th><th>Ενέργεια</th><th>Πακέτο</th><th>Περίοδος</th><th>Ποσό</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?><tr><td class="num"><?= fmt_date($h['created_at'], true) ?></td><td><b><?= e(['created' => 'Δημιουργία', 'renewed' => 'Ανανέωση', 'timer_reset' => 'Reset timer', 'upgraded' => 'Αναβάθμιση', 'downgraded' => 'Υποβάθμιση', 'expired' => 'Λήξη', 'locked' => 'Κλείδωμα', 'suspended' => 'Αναστολή', 'reactivated' => 'Επανενεργοποίηση'][$h['action']] ?? $h['action']) ?></b><?php if ($h['note']): ?><div class="sub"><?= e($h['note']) ?></div><?php endif; ?></td>
    <td><?= e($h['plan_name']) ?></td><td class="num"><?= $h['start_date'] ? fmt_date($h['start_date']) . ' → ' . fmt_date($h['end_date']) : '—' ?><?= $h['duration_months'] ? ' (' . (int) $h['duration_months'] . 'μ)' : '' ?></td><td class="num"><?= $h['price'] !== null ? money((float) $h['price']) : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
  <div class="card"><div class="hd"><h2>Αρχείο ενεργειών καταστήματος</h2><a href="/superadmin/audit?shop=<?= $id ?>">Όλο →</a></div><div class="bd flush table-wrap"><table class="table"><thead><tr><th>Πότε</th><th>Ποιος</th><th>Ενέργεια</th><th>Αποτέλεσμα</th></tr></thead><tbody>
    <?php foreach (array_slice($audit, 0, 40) as $l): ?><tr><td class="num"><?= fmt_date($l['created_at'], true) ?></td><td><?= e($l['actor_name']) ?><div class="sub"><?= e($l['actor_type']) ?><?= $l['impersonator_id'] ? ' · μέσω Super Admin' : '' ?> · <?= e($l['ip']) ?></div></td><td><code><?= e($l['action']) ?></code></td><td><?= status_badge($l['outcome']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
</section>

<section data-tab-panel="payments" style="margin-top:16px" hidden>
  <div class="card"><div class="hd"><div><h2>Πληρωμές</h2><p>Πάροχος: χειροκίνητος (V1). Η αρχιτεκτονική είναι έτοιμη για Stripe / Viva Wallet.</p></div></div><div class="bd flush table-wrap"><table class="table"><thead><tr><th>#</th><th>Είδος</th><th>Ποσό</th><th>Κατάσταση</th><th>Ημερομηνία</th><th></th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?><tr><td class="num">#<?= $p['id'] ?></td><td><?= $p['kind'] === 'setup_fee' ? 'Setup fee' : 'Συνδρομή' ?></td><td class="num"><?= money((float) $p['amount']) ?></td><td><?= status_badge($p['status']) ?></td><td class="num"><?= fmt_date($p['paid_at'] ?: $p['created_at']) ?></td>
    <td class="actions"><?php if ($p['status'] === 'pending'): ?><form method="post" action="<?= $a ?>payment" class="row-flex" style="justify-content:flex-end"><?= csrf_field() ?><input type="hidden" name="_tab" value="payments"><input type="hidden" name="payment_id" value="<?= $p['id'] ?>"><input name="reference" placeholder="Αναφορά" style="max-width:140px"><button class="btn btn-sm">Εξοφλήθηκε</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
</section>

<section data-tab-panel="backups" style="margin-top:16px" hidden>
  <div class="card"><div class="hd"><div><h2>Αντίγραφα ασφαλείας</h2><p>Βάση δεδομένων καταστήματος + πολυμέσα + εγγραφή πλατφόρμας σε ZIP.</p></div>
    <form method="post" action="<?= $a ?>backup"><?= csrf_field() ?><input type="hidden" name="_tab" value="backups"><button class="btn btn-primary"><?= icon('archive') ?>Νέο αντίγραφο</button></form></div>
    <div class="bd flush table-wrap"><table class="table"><thead><tr><th>Αρχείο</th><th>Μέγεθος</th><th>Ημερομηνία</th><th></th></tr></thead><tbody>
    <?php if (!$backups): ?><tr><td colspan="4" class="empty">Δεν υπάρχουν αντίγραφα.</td></tr><?php endif; ?>
    <?php foreach ($backups as $b): ?><tr><td><code><?= e($b['name']) ?></code></td><td class="num"><?= number_format($b['size'] / 1048576, 2, ',', '.') ?> MB</td><td class="num"><?= date('d/m/Y H:i', $b['time']) ?></td><td class="actions"><a class="btn btn-sm" href="<?= $a ?>backups/<?= e($b['name']) ?>"><?= icon('download') ?>Λήψη</a></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</section>

<section data-tab-panel="danger" style="margin-top:16px" hidden>
  <form method="post" action="<?= $a ?>delete" class="card danger-zone" data-confirm="Οριστική διαγραφή καταστήματος. Θα δημιουργηθεί αυτόματα αντίγραφο ασφαλείας και μετά θα διαγραφούν η βάση και τα αρχεία του καταστήματος." data-confirm-type="<?= e($primary) ?>" data-confirm-field="confirm_text" data-confirm-ok="Οριστική διαγραφή"><?= csrf_field() ?><input type="hidden" name="_tab" value="danger">
    <div class="hd"><div><h2>Οριστική διαγραφή</h2><p>Πριν τη διαγραφή δημιουργείται αυτόματα αντίγραφο ασφαλείας. Τα domains αποδεσμεύονται.</p></div></div>
    <div class="bd"><p>Για επιβεβαίωση θα σας ζητηθεί να πληκτρολογήσετε το domain <code><?= e($primary) ?></code>.</p></div>
    <div class="ft"><button class="btn btn-danger"><?= icon('trash') ?>Διαγραφή καταστήματος</button></div>
  </form>
</section>
</div>
