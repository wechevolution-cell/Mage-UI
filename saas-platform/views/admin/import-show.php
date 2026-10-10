<?php $st = $imp['status']; ?>
<div class="page-head"><div><h1>Εισαγωγή #<?= $imp['id'] ?> <?= status_badge($st) ?></h1><p><?= e($imp['filename']) ?> · <?= number_format((int) $imp['total_rows'], 0, ',', '.') ?> γραμμές</p></div>
  <div class="actions"><?php if (in_array($st, ['uploaded', 'validated', 'queued', 'processing'], true)): ?><form method="post" action="/admin/import/<?= $imp['id'] ?>/cancel"><?= csrf_field() ?><button class="btn" data-confirm="Ακύρωση εισαγωγής;">Ακύρωση</button></form><?php endif; ?><a class="btn" href="/admin/import">Πίσω</a></div></div>

<?php if ($preview): ?>
<form method="post" action="/admin/import/<?= $imp['id'] ?>/confirm" class="grid"><?= csrf_field() ?>
  <div class="card"><div class="hd"><div><h2>1. Αντιστοίχιση πεδίων</h2><p>Έγινε αυτόματη αναγνώριση στηλών. Ελέγξτε και διορθώστε.</p></div></div>
    <div class="bd"><div class="fg fg3"><?php foreach (Importer::FIELDS as $f => $label): ?>
      <div class="field"><label><?= e($label) ?></label><select name="map[<?= $f ?>]"><option value="">— Όχι —</option><?php foreach ($headers as $h): ?><option value="<?= e($h) ?>"<?= ($mapping[$f] ?? '') === $h ? ' selected' : '' ?>><?= e($h) ?></option><?php endforeach; ?></select></div>
    <?php endforeach; ?></div></div></div>
  <div class="card"><div class="hd"><div><h2>2. Προεπισκόπηση & έλεγχος (πρώτες γραμμές)</h2><p>Η προεπισκόπηση ανανεώνεται μετά την επιβεβαίωση αντιστοίχισης.</p></div></div>
    <div class="bd flush table-wrap"><table class="table"><thead><tr><th>#</th><th>SKU</th><th>Όνομα</th><th>Τιμή</th><th>Απόθεμα</th><th>Κατηγορία</th><th>Έλεγχος</th></tr></thead><tbody>
    <?php foreach ($preview['rows'] as $r): ?><tr><td class="num"><?= $r['row'] ?></td><td><?= e($r['data']['sku'] ?? '') ?></td><td><?= e(mb_substr((string) ($r['data']['name'] ?? ''), 0, 60)) ?></td><td class="num"><?= e($r['data']['price'] ?? '') ?></td><td class="num"><?= e($r['data']['stock'] ?? '') ?></td><td class="sub"><?= e($r['data']['category'] ?? '') ?></td>
    <td><?= $r['errors'] ? '<span class="badge b-bad">' . e(implode(', ', $r['errors'])) . '</span>' : '<span class="badge b-ok">OK</span>' ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="card"><div class="hd"><h2>3. Επιλογές</h2></div><div class="bd form">
    <label class="check"><input type="checkbox" name="update_existing" value="1" checked> Ενημέρωση υπαρχόντων προϊόντων με το ίδιο SKU (αποφυγή διπλότυπων)</label>
    <label class="check"><input type="checkbox" name="download_images" value="1"> Λήψη εικόνων από τα URL (πιο αργό — γίνεται σε παρτίδες)</label>
    <fieldset class="box"><legend>Ενεργοποίηση (έως το όριο: απομένουν <?= $quota['remaining'] ?> θέσεις)</legend>
      <label class="check"><input type="radio" name="activate" value="file" checked> Σύμφωνα με τη στήλη «Ενεργό» (αν λείπει: ενεργοποίηση όσων χωρούν)</label>
      <label class="check"><input type="radio" name="activate" value="all"> Ενεργοποίηση όσων χωρούν στο όριο</label>
      <label class="check"><input type="radio" name="activate" value="none"> Όλα ως ανενεργά (τα επιλέγετε μετά)</label>
    </fieldset>
  </div><div class="ft"><button class="btn btn-primary btn-lg">Επιβεβαίωση & έναρξη εισαγωγής</button></div></div>
</form>
<?php endif; ?>

<?php if (in_array($st, ['queued', 'processing'], true)): ?>
<div class="card" data-import-progress="/admin/import/<?= $imp['id'] ?>/process"><div class="hd"><div><h2>Επεξεργασία σε εξέλιξη…</h2><p>Κρατήστε τη σελίδα ανοιχτή για γρηγορότερη ολοκλήρωση (με cron συνεχίζει και στο παρασκήνιο).</p></div></div>
  <div class="bd quota"><div class="bar progress-big"><i style="width:<?= $progress['pct'] ?>%"></i></div><div class="row"><b data-txt><?= $progress['processed'] ?> / <?= $progress['total'] ?></b><span class="muted" data-stats></span></div></div></div>
<?php endif; ?>

<?php if (in_array($st, ['completed', 'cancelled', 'failed'], true)): ?>
<div class="grid g4">
  <div class="card stat ok"><span class="lbl">Νέα προϊόντα</span><span class="val num"><?= $progress['created'] ?></span></div>
  <div class="card stat"><span class="lbl">Ενημερώσεις</span><span class="val num"><?= $progress['updated'] ?></span></div>
  <div class="card stat"><span class="lbl">Ενεργοποιήσεις</span><span class="val num"><?= $progress['activated'] ?></span><span class="sub"><?= $progress['quota_blocked'] ?> ανενεργά λόγω ορίου</span></div>
  <div class="card stat <?= $progress['errors'] ? 'bad' : '' ?>"><span class="lbl">Σφάλματα / παραλείψεις</span><span class="val num"><?= $progress['errors'] ?> / <?= $progress['skipped'] ?></span></div>
</div>
<?php endif; ?>

<div class="grid g2">
  <div class="card"><div class="hd"><h2>Αρχείο καταγραφής</h2></div><div class="bd"><ul class="timeline"><?php foreach ($logs as $l): ?><li><time><?= fmt_date($l['created_at'], true) ?></time><div><span class="badge <?= $l['level'] === 'error' ? 'b-bad' : ($l['level'] === 'warning' ? 'b-warn' : 'b-info') ?> plain"><?= e($l['level']) ?></span> <?= $l['row_no'] ? 'Γραμμή ' . (int) $l['row_no'] . ': ' : '' ?><?= e($l['message']) ?></div></li><?php endforeach; ?></ul></div></div>
  <div class="card"><div class="hd"><h2>Γραμμές με σφάλμα / παράλειψη</h2></div><div class="bd flush table-wrap"><table class="table"><tbody>
  <?php if (!$errors): ?><tr><td class="empty">Καμία.</td></tr><?php endif; ?>
  <?php foreach ($errors as $er): ?><tr><td class="num">#<?= (int) $er['row_no'] ?></td><td><?= e($er['message']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
