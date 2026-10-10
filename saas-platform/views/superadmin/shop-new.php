<div class="page-head"><div><h1>Νέο κατάστημα</h1><p>Οδηγός δημιουργίας: στοιχεία πελάτη → domain → πακέτο → διάρκεια → template → λογαριασμός διαχειριστή → έλεγχος.</p></div></div>
<form method="post" action="/superadmin/shops/new" class="card" data-wizard id="wizard">
  <?= csrf_field() ?>
  <div class="hd"><nav class="steps" aria-label="Βήματα"><button type="button">Πελάτης</button><button type="button">Domain</button><button type="button">Πακέτο</button><button type="button">Διάρκεια</button><button type="button">Template</button><button type="button">Διαχειριστής</button><button type="button">Έλεγχος</button></nav></div>
  <div class="bd">
    <section class="step-panel form">
      <h2>Στοιχεία πελάτη</h2>
      <div class="fg">
        <div class="field"><label for="w-name">Επωνυμία καταστήματος *</label><input id="w-name" name="name" required value="<?= e(old('name')) ?>"></div>
        <div class="field"><label for="w-company">Εταιρεία / νομικό πρόσωπο</label><input id="w-company" name="company_name" value="<?= e(old('company_name')) ?>"></div>
        <div class="field"><label for="w-owner">Υπεύθυνος επικοινωνίας</label><input id="w-owner" name="owner_name" value="<?= e(old('owner_name')) ?>"></div>
        <div class="field"><label for="w-email">Email</label><input id="w-email" type="email" name="email" value="<?= e(old('email')) ?>"></div>
        <div class="field"><label for="w-phone">Τηλέφωνο</label><input id="w-phone" type="tel" name="phone" value="<?= e(old('phone')) ?>"></div>
        <div class="field"><label for="w-vat">ΑΦΜ</label><input id="w-vat" name="vat_number" value="<?= e(old('vat_number')) ?>"></div>
        <div class="field full"><label for="w-address">Διεύθυνση</label><input id="w-address" name="address" value="<?= e(old('address')) ?>"></div>
        <div class="field full"><label for="w-notes">Σημειώσεις (εσωτερικές)</label><textarea id="w-notes" name="notes" rows="2"><?= e(old('notes')) ?></textarea></div>
      </div>
    </section>
    <section class="step-panel form" hidden>
      <h2>Domain & hosting</h2>
      <div class="fg">
        <div class="field"><label for="w-domain">Domain *</label><input id="w-domain" name="domain" required placeholder="example.gr" value="<?= e(old('domain')) ?>"><span class="hint">Χωρίς https://. Η εκδοχή www και non-www εξυπηρετούνται αυτόματα. Για δοκιμές: <code>onoma.localhost</code>. Μπορείτε να δώσετε και το domain της πλατφόρμας (<code><?= e((string) (Config::get('platform_hosts')[0] ?? '')) ?></code>): τότε το κατάστημα εμφανίζεται στο <code>/</code>, η διαχείρισή του στο <code>/admin</code> και το Super Admin παραμένει στο <code>/superadmin</code>.</span></div>
        <div class="field"><label for="w-slug">Εσωτερικό αναγνωριστικό (slug)</label><input id="w-slug" name="slug" data-slug-from="#w-name" value="<?= e(old('slug')) ?>"></div>
        <div class="field"><label for="w-hs">Κατάσταση hosting</label><select id="w-hs" name="hosting_status"><option value="pending">Σε αναμονή</option><option value="active">Ενεργό</option></select></div>
        <div class="field"><label for="w-hn">Σημειώσεις hosting</label><input id="w-hn" name="hosting_notes" placeholder="π.χ. Hostinger parked domain, DNS A → IP" value="<?= e(old('hosting_notes')) ?>"></div>
      </div>
      <div class="alert info"><?= icon('info') ?><div>Στο Hostinger προσθέστε το domain ως <b>Parked/Alias domain</b> που δείχνει στον ίδιο φάκελο της πλατφόρμας και ενεργοποιήστε SSL. Δείτε το README.</div></div>
    </section>
    <section class="step-panel form" hidden>
      <h2>Πακέτο</h2>
      <div class="plan-grid">
        <?php foreach ($plans as $i => $p): ?>
        <label class="plan"><input type="radio" name="plan_id" value="<?= $p['id'] ?>" required data-durations="<?= e($p['duration_options']) ?>" data-name="<?= e($p['name']) ?>" data-price="<?= e($p['price']) ?>"<?= (int) old('plan_id', $plans[1]['id'] ?? $p['id']) === (int) $p['id'] ? ' checked' : '' ?>>
          <b><?= e($p['name']) ?></b><span class="price"><?= money((float) $p['price']) ?><small class="muted">/μήνα</small></span>
          <span><?= (int) $p['active_product_limit'] ?> ενεργά προϊόντα</span><small class="muted">Έως <?= number_format((int) $p['stored_product_limit'], 0, ',', '.') ?> αποθηκευμένα · <?= (int) $p['staff_limit'] ?> χρήστες · Setup <?= money((float) $p['setup_fee']) ?></small></label>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="step-panel form" hidden>
      <h2>Διάρκεια συνδρομής</h2>
      <div class="fg">
        <div class="field"><label for="w-duration">Διάρκεια</label><select id="w-duration" name="duration" required></select></div>
        <div class="field"><label for="w-start">Ημερομηνία έναρξης</label><input id="w-start" type="date" name="start_date" value="<?= e(old('start_date', today())) ?>"></div>
        <div class="field"><label for="w-status">Αρχική κατάσταση</label><select id="w-status" name="status"><option value="active">Ενεργό (δημόσιο)</option><option value="pending">Σε αναμονή (προετοιμασία)</option></select><span class="hint">Σε αναμονή: ο διαχειριστής μπορεί να συνδεθεί, το κατάστημα δεν είναι δημόσιο.</span></div>
        <div class="field"><span class="lbl">Πληρωμές</span><label class="check"><input type="checkbox" name="setup_paid" value="1"> Το setup fee εξοφλήθηκε</label><label class="check"><input type="checkbox" name="subscription_paid" value="1"> Η συνδρομή εξοφλήθηκε</label></div>
      </div>
    </section>
    <section class="step-panel form" hidden>
      <h2>Template</h2>
      <p class="muted">Το template αλλάζει μόνο την εμφάνιση· μπορείτε να το αλλάξετε οποτεδήποτε χωρίς απώλεια δεδομένων.</p>
      <div class="tpl-grid">
        <?php foreach ($templates as $i => $t): $m = Templates::manifest($t['key']); $tk = $m['tokens'] ?? []; ?>
        <label class="tpl"><input type="radio" name="template_key" value="<?= e($t['key']) ?>" required data-plans="<?= e($t['allowed_plans'] ?? '') ?>"<?= !$t['enabled'] ? ' disabled' : '' ?><?= old('template_key', 'none') === $t['key'] || (old('template_key') === '' && $i === 0) ? ' checked' : '' ?>>
          <span class="sw"><span style="background:<?= e($tk['background'] ?? '#eee') ?>"></span><span style="background:<?= e($tk['primary'] ?? '#333') ?>"></span><span style="background:<?= e($tk['accent'] ?? '#999') ?>"></span><span style="background:<?= e($tk['surface'] ?? '#fff') ?>"></span></span>
          <span class="meta"><b><?= e($t['name']) ?></b><small class="muted"><?= e($t['industry']) ?></small><?php if (!$t['enabled']): ?><small class="badge b-muted plain">Απενεργοποιημένο</small><?php endif; ?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="check"><input type="checkbox" name="demo_content" value="1" checked> Προσθήκη δείγματος προϊόντων (για παρουσίαση· σημειώνονται ως «δείγμα»)</label>
    </section>
    <section class="step-panel form" hidden>
      <h2>Λογαριασμός διαχειριστή καταστήματος</h2>
      <div class="fg">
        <div class="field"><label for="w-an">Όνομα</label><input id="w-an" name="admin_name" value="<?= e(old('admin_name')) ?>"></div>
        <div class="field"><label for="w-ae">Email σύνδεσης *</label><input id="w-ae" type="email" name="admin_email" required value="<?= e(old('admin_email')) ?>"></div>
        <div class="field"><label for="w-ap">Αρχικός κωδικός *</label><input id="w-ap" type="text" name="admin_password" required minlength="10" autocomplete="off" value=""><span class="hint">≥ 10 χαρακτήρες, γράμματα και αριθμοί. Δεν αποστέλλεται με email.</span></div>
        <div class="field"><span class="lbl">Ειδοποίηση</span><label class="check"><input type="checkbox" name="send_credentials" value="1" checked> Αποστολή email καλωσορίσματος με τη διεύθυνση σύνδεσης</label></div>
      </div>
    </section>
    <section class="step-panel form" hidden>
      <h2>Έλεγχος & δημιουργία</h2>
      <dl class="kv" id="review"></dl>
      <div class="alert info"><?= icon('info') ?><div>Θα δημιουργηθούν: η απομονωμένη βάση δεδομένων του καταστήματος, η συνδρομή, οι σελίδες (όροι, απόρρητο, επικοινωνία κ.λπ.), τα μενού και τα feeds.</div></div>
    </section>
  </div>
  <div class="ft">
    <button type="button" class="btn" data-prev>Πίσω</button>
    <button type="button" class="btn btn-primary" data-next>Επόμενο</button>
    <button class="btn btn-primary" data-finish hidden>Δημιουργία καταστήματος</button>
  </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const w = document.getElementById('wizard'), dur = document.getElementById('w-duration');
  const fill = () => {
    const p = w.querySelector('input[name=plan_id]:checked'); if (!p) return;
    const opts = JSON.parse(p.dataset.durations || '[1,3,6,12]'), cur = dur.value;
    dur.innerHTML = opts.map(m => '<option value="' + m + '">' + m + (m === 1 ? ' μήνας' : ' μήνες') + ' — ' + (p.dataset.price * m).toFixed(2).replace('.', ',') + ' €</option>').join('');
    if (opts.includes(+cur)) dur.value = cur;
    w.querySelectorAll('input[name=template_key]').forEach(t => { const pl = t.dataset.plans ? JSON.parse(t.dataset.plans) : null; if (pl) t.disabled = !pl.includes(+p.value); });
  };
  w.addEventListener('change', e => { if (e.target.name === 'plan_id') fill(); });
  fill();
  w.addEventListener('wizard:review', () => {
    const v = n => (w.querySelector('[name="' + n + '"]')?.value || '—');
    const p = w.querySelector('input[name=plan_id]:checked'), t = w.querySelector('input[name=template_key]:checked');
    const rows = [['Κατάστημα', v('name')], ['Domain', v('domain')], ['Πακέτο', p ? p.dataset.name : '—'], ['Διάρκεια', dur.selectedOptions[0]?.textContent || '—'], ['Έναρξη', v('start_date')], ['Template', t ? t.closest('.tpl').querySelector('b').textContent : '—'], ['Διαχειριστής', v('admin_email')]];
    document.getElementById('review').innerHTML = rows.map(([k, val]) => '<dt>' + k + '</dt><dd>' + String(val).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])) + '</dd>').join('');
  });
});
</script>
