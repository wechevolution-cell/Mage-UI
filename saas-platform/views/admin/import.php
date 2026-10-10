<div class="page-head"><div><h1>Εισαγωγή / εξαγωγή</h1><p>Μαζική εισαγωγή έως 10.000 προϊόντων από CSV ή XML, σε παρτίδες χωρίς timeouts.</p></div></div>
<?php include __DIR__ . '/_quota.php'; ?>
<div class="grid g2">
  <form method="post" action="/admin/import/upload" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
    <div class="hd"><div><h2>Νέα εισαγωγή</h2><p>Ροή: Ανέβασμα → Έλεγχος → Προεπισκόπηση → Επιβεβαίωση → Ουρά → Επεξεργασία → Αναφορά</p></div></div>
    <div class="bd form">
      <div class="dropzone"><p><b>Αρχείο CSV ή XML</b></p><input type="file" name="file" accept=".csv,.txt,.xml,text/csv,text/xml,application/xml" required aria-label="Αρχείο"><p class="muted" style="margin-top:8px;font-size:12px">CSV: διαχωριστικό , ; ή tab, UTF-8 ή Windows-1253. XML: επαναλαμβανόμενα &lt;product&gt; / &lt;item&gt; (συμβατό με Skroutz, Google, WooCommerce exports).</p></div>
      <p class="muted" style="font-size:13px">Τα υπάρχοντα προϊόντα αναγνωρίζονται από το SKU (ενημέρωση), τα υπόλοιπα δημιουργούνται. Η ενεργοποίηση σέβεται το όριο του πακέτου — τα υπόλοιπα αποθηκεύονται ως ανενεργά.</p>
    </div>
    <div class="ft"><a class="btn" href="/admin/import/template.csv"><?= icon('download') ?>Υπόδειγμα CSV</a><button class="btn btn-primary"><?= icon('upload') ?>Ανέβασμα & έλεγχος</button></div>
  </form>
  <?php if ($canExport): ?>
  <form method="get" action="/admin/export" class="card">
    <div class="hd"><div><h2>Εξαγωγή CSV</h2><p>Συμβατό με την εισαγωγή (επανεισαγωγή για μαζικές αλλαγές).</p></div></div>
    <div class="bd form">
      <label class="check"><input type="radio" name="mode" value="all" checked> Όλα τα προϊόντα</label>
      <label class="check"><input type="radio" name="mode" value="active"> Μόνο ενεργά</label>
      <label class="check"><input type="radio" name="mode" value="inactive"> Μόνο ανενεργά</label>
      <label class="check"><input type="radio" name="mode" value="category"> Ανά κατηγορία:</label>
      <select name="category" aria-label="Κατηγορία"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select>
      <p class="muted" style="font-size:12.5px">Για επιλεγμένα προϊόντα: Προϊόντα → επιλογή → «Εξαγωγή CSV».</p>
    </div>
    <div class="ft"><button class="btn btn-primary"><?= icon('download') ?>Λήψη CSV</button></div>
  </form>
  <?php endif; ?>
</div>
<div class="card"><div class="hd"><h2>Ιστορικό εισαγωγών</h2></div><div class="table-wrap">
<?php if (!$imports): ?><div class="empty">Δεν υπάρχουν εισαγωγές.</div><?php else: ?>
<table class="table"><thead><tr><th>#</th><th>Αρχείο</th><th>Κατάσταση</th><th class="r">Γραμμές</th><th class="r">Νέα</th><th class="r">Ενημ.</th><th class="r">Σφάλματα</th><th class="r">Ενεργοποιήσεις</th><th>Ημερομηνία</th></tr></thead><tbody>
<?php foreach ($imports as $i): ?><tr><td><a href="/admin/import/<?= $i['id'] ?>">#<?= $i['id'] ?></a></td><td><?= e($i['filename']) ?> <span class="badge plain"><?= strtoupper(e($i['type'])) ?></span></td><td><?= status_badge($i['status']) ?></td><td class="r num"><?= $i['processed_rows'] ?>/<?= $i['total_rows'] ?></td><td class="r num"><?= $i['created_count'] ?></td><td class="r num"><?= $i['updated_count'] ?></td><td class="r num"><?= $i['error_count'] ?></td><td class="r num"><?= $i['activated_count'] ?></td><td class="num"><?= fmt_date($i['created_at'], true) ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?></div></div>
