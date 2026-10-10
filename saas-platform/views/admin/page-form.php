<?php
$isPost = $kind === 'post';
$base = $isPost ? '/admin/posts' : '/admin/pages';
$public = $p ? ($isPost ? '/blog/' . $p['slug'] . '/' : '/' . $p['slug'] . '/') : null;
?>
<form method="post" action="<?= $base ?>/save" enctype="multipart/form-data" class="grid" data-seo-analyze="/admin/seo/analyze" data-seo-type="<?= $isPost ? 'post' : 'page' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
<div class="page-head"><div><h1><?= $p ? e($p['title']) : ($isPost ? 'Νέο άρθρο' : 'Νέα σελίδα') ?></h1></div><div class="actions"><?php if ($public): ?><a class="btn" href="<?= e($public) ?>" target="_blank" rel="noopener"><?= icon('eye') ?>Προβολή</a><?php endif; ?><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="split"><div class="grid">
  <div class="card"><div class="bd form">
    <div class="field"><label for="pg-title">Τίτλος *</label><input id="pg-title" name="title" value="<?= e($p['title'] ?? '') ?>"></div>
    <div class="field"><label for="pg-slug">Slug</label><div class="input-group"><span class="addon"><?= $isPost ? '/blog/' : '/' ?></span><input id="pg-slug" name="slug" data-slug-from="#pg-title" value="<?= e($p['slug'] ?? '') ?>"></div></div>
    <?php if ($isPost): ?>
      <div class="field"><label for="pg-tldr">TL;DR — σύντομη απάντηση</label><textarea id="pg-tldr" name="tldr" rows="3"><?= e($p['tldr'] ?? '') ?></textarea><span class="hint">2–3 προτάσεις που απαντούν απευθείας στο ερώτημα του άρθρου (AEO).</span></div>
      <div class="field"><label for="pg-ex">Περίληψη</label><textarea id="pg-ex" name="excerpt" rows="2"><?= e($p['excerpt'] ?? '') ?></textarea></div>
    <?php endif; ?>
    <div class="field"><label for="pg-content">Περιεχόμενο</label><textarea id="pg-content" name="content" rows="16" data-rte><?= e($p['content'] ?? '') ?></textarea><span class="hint">Επιτρέπεται βασικό HTML (επικεφαλίδες, λίστες, πίνακες, σύνδεσμοι, εικόνες, iframes YouTube/Vimeo/Google Maps). Όλα φιλτράρονται για ασφάλεια.</span></div>
  </div></div>
  <details class="card"><summary class="hd" style="cursor:pointer"><div><h2>Εισαγωγή από Markdown</h2><p>Επικεφαλίδες, λίστες, πίνακες, παραθέσεις, κώδικας, σύνδεσμοι. Ενότητα «## FAQ» με «### Ερώτηση» → FAQ.</p></div></summary>
    <div class="bd form"><textarea name="markdown" rows="10" class="code" placeholder="# Τίτλος&#10;&#10;Κείμενο...&#10;&#10;## FAQ&#10;### Ερώτηση;&#10;Απάντηση."></textarea><label class="muted">ή αρχείο .md <input type="file" name="md_file" accept=".md,.markdown,text/markdown,text/plain"></label><span class="hint">Αν συμπληρωθεί, αντικαθιστά το περιεχόμενο κατά την αποθήκευση.</span></div></details>
  <?php include __DIR__ . '/_faq.php'; ?>
</div><div class="grid">
  <div class="card"><div class="hd"><h2>Δημοσίευση</h2></div><div class="bd form">
    <div class="field"><label>Κατάσταση</label><select name="status"><option value="published">Δημοσιευμένο</option><option value="draft"<?= ($p['status'] ?? ($isPost ? 'draft' : 'published')) === 'draft' ? ' selected' : '' ?>>Πρόχειρο</option><?php if ($isPost): ?><option value="scheduled"<?= ($p['status'] ?? '') === 'scheduled' ? ' selected' : '' ?>>Προγραμματισμένο</option><?php endif; ?></select></div>
    <?php if ($isPost): ?>
      <div class="field"><label>Ημερομηνία δημοσίευσης</label><input type="datetime-local" name="published_at" value="<?= e($p && $p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : '') ?>"></div>
      <div class="field"><label>Συντάκτης</label><input name="author" value="<?= e($p['author'] ?? $user['name']) ?>"></div>
      <div class="field"><label>Κατηγορία</label><input name="category" value="<?= e($p['category'] ?? '') ?>"></div>
      <div class="field"><label>Ετικέτες</label><input name="tags" value="<?= e($p['tags'] ?? '') ?>"></div>
      <div class="field"><label>Πηγές (μία ανά γραμμή)</label><textarea name="sources" rows="3"><?= e($p['sources'] ?? '') ?></textarea></div>
      <?php $name = 'image_id'; $media = $img ?? null; $label = 'Κύρια εικόνα'; include __DIR__ . '/_img_pick.php'; ?>
    <?php else: ?>
      <div class="field"><label>Τύπος</label><select name="kind"><?php foreach (['page' => 'Σελίδα', 'contact' => 'Επικοινωνία (με φόρμα)', 'faq' => 'Συχνές ερωτήσεις', 'legal' => 'Νομική / πληροφορίες'] as $k => $l): ?><option value="<?= $k ?>"<?= ($p['kind'] ?? 'page') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <label class="check"><input type="checkbox" name="show_in_footer" value="1"<?= !empty($p['show_in_footer']) ? ' checked' : '' ?>> Εμφάνιση στο υποσέλιδο</label>
      <div class="field"><label>Σειρά</label><input type="number" name="sort" value="<?= (int) ($p['sort'] ?? 0) ?>"></div>
    <?php endif; ?>
  </div></div>
  <?php $e = $p ?? []; if (!$isPost) { $noCanonical = null; } include __DIR__ . '/_seo_fields.php'; ?>
</div></div></form>
<?php if ($p): ?><form method="post" action="<?= $base ?>/<?= $p['id'] ?>/delete"><?= csrf_field() ?><button class="btn btn-danger" data-confirm="Διαγραφή; Η διεύθυνση θα επιστρέφει 410 (Gone)." data-confirm-type="ΔΙΑΓΡΑΦΗ"><?= icon('trash') ?>Διαγραφή</button></form><?php endif; ?>
