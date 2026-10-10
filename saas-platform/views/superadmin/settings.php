<div class="page-head"><div><h1>Ρυθμίσεις πλατφόρμας</h1></div></div>
<form method="post" action="/superadmin/settings" class="grid g2"><?= csrf_field() ?>
  <div class="card"><div class="hd"><h2>Γενικά</h2></div><div class="bd form">
    <div class="field"><label>Όνομα πλατφόρμας</label><input name="platform_name" value="<?= e($s['platform_name']) ?>"></div>
    <div class="field"><label>Host(s) πίνακα πλατφόρμας</label><input name="platform_hosts" value="<?= e(implode(', ', $hosts)) ?>"><span class="hint">Πολλαπλά χωρισμένα με κόμμα.</span></div>
    <div class="field"><label>Email ειδοποιήσεων</label><input type="email" name="notify_email" value="<?= e($s['notify_email']) ?>"></div>
    <div class="field"><label>Περίοδος χάριτος μετά τη λήξη (ημέρες)</label><input type="number" min="0" max="60" name="grace_days" value="<?= (int) $s['grace_days'] ?>"><span class="hint">0 = άμεσο κλείδωμα στη λήξη (ACTIVE → EXPIRED → LOCKED).</span></div>
    <div class="field"><label>IP διακομιστή (για έλεγχο DNS)</label><input name="server_ip" value="<?= e($s['server_ip']) ?>" placeholder="π.χ. 185.xx.xx.xx"></div>
    <div class="field"><label>Μήνυμα σελίδας κλειδώματος (προαιρετικό)</label><input name="lock_message" value="<?= e($s['lock_message']) ?>" placeholder="Αυτό το ηλεκτρονικό κατάστημα δεν είναι προσωρινά διαθέσιμο. Θα επιστρέψουμε σύντομα κοντά σας."></div>
  </div></div>
  <div class="card"><div class="hd"><h2>Αποστολή email</h2></div><div class="bd form">
    <div class="field"><label>Τρόπος αποστολής</label><select name="mail_driver"><option value="log"<?= $s['mail_driver'] === 'log' ? ' selected' : '' ?>>Αρχείο (δοκιμές — data/mail)</option><option value="mail"<?= $s['mail_driver'] === 'mail' ? ' selected' : '' ?>>PHP mail()</option><option value="smtp"<?= $s['mail_driver'] === 'smtp' ? ' selected' : '' ?>>SMTP (προτείνεται)</option></select></div>
    <div class="fg"><div class="field"><label>SMTP host</label><input name="smtp_host" value="<?= e($s['smtp_host']) ?>"></div><div class="field"><label>Θύρα</label><input type="number" name="smtp_port" value="<?= (int) $s['smtp_port'] ?>"></div>
    <div class="field"><label>Κρυπτογράφηση</label><select name="smtp_secure"><option value="ssl"<?= $s['smtp_secure'] === 'ssl' ? ' selected' : '' ?>>SSL (465)</option><option value="tls"<?= $s['smtp_secure'] === 'tls' ? ' selected' : '' ?>>STARTTLS (587)</option><option value="none"<?= $s['smtp_secure'] === 'none' ? ' selected' : '' ?>>Καμία</option></select></div>
    <div class="field"><label>Χρήστης SMTP</label><input name="smtp_user" value="<?= e($s['smtp_user']) ?>" autocomplete="off"></div>
    <div class="field"><label>Κωδικός SMTP</label><input type="password" name="smtp_pass" placeholder="<?= $s['smtp_pass_set'] ? '•••••• (αποθηκευμένος)' : '' ?>" autocomplete="new-password"></div>
    <div class="field"><label>Αποστολέας (email)</label><input type="email" name="mail_from" value="<?= e($s['mail_from']) ?>"></div>
    <div class="field full"><label>Όνομα αποστολέα</label><input name="mail_from_name" value="<?= e($s['mail_from_name']) ?>"></div></div>
  </div><div class="ft"><button class="btn" formaction="/superadmin/settings/test-mail">Δοκιμαστικό email</button></div></div>
  <div class="card" style="grid-column:1 / -1"><div class="hd"><div><h2>Προγραμματισμένες εργασίες (cron)</h2><p>Έλεγχοι λήξεων, κλειδώματα, εισαγωγές σε ουρά, ανανέωση feeds. Τελευταία εκτέλεση: <?= $s['cron_last_run'] ? e(fmt_date($s['cron_last_run'], true)) : 'ποτέ' ?></p></div><button class="btn" formaction="/superadmin/settings/run-cron"><?= icon('refresh') ?>Εκτέλεση τώρα</button></div>
    <div class="bd form"><div class="field"><label>URL cron (κάθε 5–15 λεπτά από hPanel → Cron Jobs)</label><div class="input-group"><input readonly value="<?= e($cronUrl) ?>"><button type="button" class="btn" data-copy="<?= e($cronUrl) ?>">Αντιγραφή</button></div><span class="hint">Εναλλακτικά: <code>php <?= e(ROOT) ?>/cron.php</code>. Χωρίς cron, οι λήξεις ελέγχονται και σε κάθε επίσκεψη καταστήματος.</span></div></div></div>
  <div style="grid-column:1 / -1"><button class="btn btn-primary btn-lg">Αποθήκευση ρυθμίσεων</button></div>
</form>
<div class="card"><div class="hd"><h2>Τελευταίες αποστολές email</h2></div><div class="bd flush table-wrap"><table class="table"><thead><tr><th>Πότε</th><th>Προς</th><th>Θέμα</th><th>Τρόπος</th><th>Κατάσταση</th></tr></thead><tbody>
<?php foreach ($mailLog as $m): ?><tr><td class="num"><?= fmt_date($m['created_at'], true) ?></td><td><?= e($m['to_email']) ?></td><td><?= e($m['subject']) ?></td><td><?= e($m['driver']) ?></td><td><?= $m['status'] === 'sent' ? '<span class="badge b-ok">Στάλθηκε</span>' : '<span class="badge b-bad" title="' . e($m['error']) . '">Απέτυχε</span>' ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
