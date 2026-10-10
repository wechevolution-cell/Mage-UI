<?php
$m = fn($id) => $id ? ($media[$id] ?? null) : null;
$labels = ['hero' => 'Hero', 'benefits' => 'Πλεονεκτήματα', 'categories' => 'Κατηγορίες', 'featured' => 'Προτεινόμενα προϊόντα', 'banners' => 'Promo banners', 'story' => 'Ιστορία / αξίες', 'newest' => 'Νέες αφίξεις', 'brands' => 'Μάρκες', 'newsletter' => 'Newsletter'];
$order = $hp['order'];
foreach (array_keys($labels) as $k) if (!in_array($k, $order, true)) $order[] = $k;
?>
<form method="post" action="/admin/homepage" class="grid"><?= csrf_field() ?>
<div class="page-head"><div><h1>Αρχική σελίδα</h1><p>Ενότητες της αρχικής χωρίς κώδικα. Κάθε template τις παρουσιάζει με το δικό του ύφος.</p></div><div class="actions"><a class="btn" href="/" target="_blank" rel="noopener"><?= icon('eye') ?>Προβολή</a><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="card"><div class="hd"><div><h2>Σειρά ενοτήτων</h2><p>Μετακινήστε με τα βέλη.</p></div></div><div class="bd"><div class="mb" id="order-list">
  <?php foreach ($order as $k): ?><div class="mb-item" style="grid-template-columns:1fr auto"><span><input type="hidden" name="order[]" value="<?= $k ?>"><b><?= e($labels[$k]) ?></b> <?= !empty($hp[$k]['enabled']) ? '<span class="badge b-ok plain">ενεργή</span>' : '<span class="badge b-muted plain">κρυφή</span>' ?></span><span class="tools"><button type="button" onclick="const i=this.closest('.mb-item');i.previousElementSibling&&i.parentNode.insertBefore(i,i.previousElementSibling)" aria-label="Πάνω">↑</button><button type="button" onclick="const i=this.closest('.mb-item');i.nextElementSibling&&i.parentNode.insertBefore(i.nextElementSibling,i)" aria-label="Κάτω">↓</button></span></div><?php endforeach; ?>
</div></div></div>

<div class="card"><div class="hd"><h2>Hero</h2><label class="check"><input type="checkbox" name="hero_enabled" value="1"<?= $hp['hero']['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div><div class="bd form"><div class="fg">
  <div class="field"><label>Μικρός τίτλος (eyebrow)</label><input name="hero_eyebrow" value="<?= e($hp['hero']['eyebrow']) ?>"></div>
  <div class="field"><label>Τίτλος</label><input name="hero_title" value="<?= e($hp['hero']['title']) ?>"></div>
  <div class="field full"><label>Υπότιτλος</label><textarea name="hero_subtitle" rows="2"><?= e($hp['hero']['subtitle']) ?></textarea></div>
  <div class="field"><label>Κουμπί 1 — κείμενο</label><input name="hero_cta_text" value="<?= e($hp['hero']['cta_text']) ?>"></div><div class="field"><label>Κουμπί 1 — σύνδεσμος</label><input name="hero_cta_url" value="<?= e($hp['hero']['cta_url']) ?>" placeholder="/products/"></div>
  <div class="field"><label>Κουμπί 2 — κείμενο</label><input name="hero_cta2_text" value="<?= e($hp['hero']['cta2_text']) ?>"></div><div class="field"><label>Κουμπί 2 — σύνδεσμος</label><input name="hero_cta2_url" value="<?= e($hp['hero']['cta2_url']) ?>"></div>
  <div class="full"><?php $name = 'hero_image_id'; $media2 = $media; $media = $m($hp['hero']['image_id']); $label = 'Εικόνα hero'; $hint = 'Μεγάλη εικόνα (≥ 1600px πλάτος). Αν λείπει, χρησιμοποιείται προϊόν.'; include __DIR__ . '/_img_pick.php'; $media = $media2; ?></div>
</div></div></div>

<div class="card" data-repeater><div class="hd"><h2>Πλεονεκτήματα</h2><div class="row-flex"><label class="check"><input type="checkbox" name="benefits_enabled" value="1"<?= $hp['benefits']['enabled'] ? ' checked' : '' ?>> Ενεργό</label><button type="button" class="btn btn-sm" data-add>+ Στοιχείο</button></div></div><div class="bd"><div class="rep">
  <?php $icons = ['truck' => 'Αποστολή', 'return' => 'Επιστροφές', 'card' => 'Πληρωμή', 'shield' => 'Ασφάλεια', 'gift' => 'Δώρο', 'leaf' => 'Φύση', 'star' => 'Ποιότητα', 'clock' => 'Χρόνος', 'phone' => 'Εξυπηρέτηση']; ?>
  <?php foreach ($hp['benefits']['items'] as $b): ?><div class="rep-row" style="--cols:3"><select data-name="benefits[__i__][icon]" aria-label="Εικονίδιο"><?php foreach ($icons as $k => $l): ?><option value="<?= $k ?>"<?= $b['icon'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><input data-name="benefits[__i__][title]" value="<?= e($b['title']) ?>" placeholder="Τίτλος" aria-label="Τίτλος"><input data-name="benefits[__i__][text]" value="<?= e($b['text']) ?>" placeholder="Κείμενο" aria-label="Κείμενο"><button type="button" class="btn rm">✕</button></div><?php endforeach; ?>
</div></div><template><div class="rep-row" style="--cols:3"><select data-name="benefits[__i__][icon]" aria-label="Εικονίδιο"><?php foreach ($icons as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select><input data-name="benefits[__i__][title]" placeholder="Τίτλος" aria-label="Τίτλος"><input data-name="benefits[__i__][text]" placeholder="Κείμενο" aria-label="Κείμενο"><button type="button" class="btn rm">✕</button></div></template></div>

<div class="grid g2">
  <div class="card"><div class="hd"><h2>Κατηγορίες</h2><label class="check"><input type="checkbox" name="categories_enabled" value="1"<?= $hp['categories']['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div><div class="bd form">
    <div class="field"><label>Τίτλος ενότητας</label><input name="categories_title" value="<?= e($hp['categories']['title']) ?>"></div>
    <fieldset class="box" style="max-height:220px;overflow:auto"><legend>Επιλεγμένες (κενό = κορυφαίες κατηγορίες)</legend><?php foreach ($cats as $c): ?><label class="check" style="padding-left:<?= (int) $c['depth'] * 14 ?>px"><input type="checkbox" name="categories_ids[]" value="<?= $c['id'] ?>"<?= in_array((int) $c['id'], array_map('intval', $hp['categories']['ids']), true) ? ' checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?></fieldset>
  </div></div>
  <div class="card"><div class="hd"><h2>Προϊόντα</h2></div><div class="bd form">
    <div class="row-flex"><label class="check"><input type="checkbox" name="featured_enabled" value="1"<?= $hp['featured']['enabled'] ? ' checked' : '' ?>> Προτεινόμενα</label></div>
    <div class="fg"><div class="field"><label>Τίτλος</label><input name="featured_title" value="<?= e($hp['featured']['title']) ?>"></div><div class="field"><label>Πλήθος</label><input type="number" min="2" max="24" name="featured_count" value="<?= (int) $hp['featured']['count'] ?>"></div></div>
    <div class="row-flex"><label class="check"><input type="checkbox" name="newest_enabled" value="1"<?= $hp['newest']['enabled'] ? ' checked' : '' ?>> Νέες αφίξεις</label></div>
    <div class="fg"><div class="field"><label>Τίτλος</label><input name="newest_title" value="<?= e($hp['newest']['title']) ?>"></div><div class="field"><label>Πλήθος</label><input type="number" min="2" max="24" name="newest_count" value="<?= (int) $hp['newest']['count'] ?>"></div></div>
  </div></div>
</div>

<div class="card"><div class="hd"><h2>Promo banners (έως 3)</h2><label class="check"><input type="checkbox" name="banners_enabled" value="1"<?= $hp['banners']['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div><div class="bd grid g3">
  <?php for ($i = 0; $i < 3; $i++): $b = $hp['banners']['items'][$i] ?? ['title' => '', 'text' => '', 'cta' => '', 'url' => '', 'image_id' => null]; ?>
  <fieldset class="box"><legend>Banner <?= $i + 1 ?></legend>
    <input name="banners[<?= $i ?>][title]" value="<?= e($b['title']) ?>" placeholder="Τίτλος" aria-label="Τίτλος"><input name="banners[<?= $i ?>][text]" value="<?= e($b['text']) ?>" placeholder="Κείμενο" aria-label="Κείμενο">
    <input name="banners[<?= $i ?>][cta]" value="<?= e($b['cta']) ?>" placeholder="Κείμενο κουμπιού" aria-label="Κουμπί"><input name="banners[<?= $i ?>][url]" value="<?= e($b['url']) ?>" placeholder="Σύνδεσμος" aria-label="Σύνδεσμος">
    <?php $name = "banners[$i][image_id]"; $media2 = $media; $media = $m($b['image_id']); $label = 'Εικόνα'; $hint = ''; include __DIR__ . '/_img_pick.php'; $media = $media2; ?>
  </fieldset><?php endfor; ?>
</div></div>

<div class="card"><div class="hd"><h2>Ιστορία / αξίες</h2><label class="check"><input type="checkbox" name="story_enabled" value="1"<?= $hp['story']['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div><div class="bd form"><div class="fg">
  <div class="field"><label>Μικρός τίτλος</label><input name="story_eyebrow" value="<?= e($hp['story']['eyebrow']) ?>"></div><div class="field"><label>Τίτλος</label><input name="story_title" value="<?= e($hp['story']['title']) ?>"></div>
  <div class="field full"><label>Κείμενο</label><textarea name="story_text" rows="3"><?= e($hp['story']['text']) ?></textarea></div>
  <div class="field"><label>Κουμπί</label><input name="story_cta_text" value="<?= e($hp['story']['cta_text']) ?>"></div><div class="field"><label>Σύνδεσμος</label><input name="story_cta_url" value="<?= e($hp['story']['cta_url']) ?>" placeholder="/about/"></div>
  <div class="full"><?php $name = 'story_image_id'; $media2 = $media; $media = $m($hp['story']['image_id']); $label = 'Εικόνα'; $hint = ''; include __DIR__ . '/_img_pick.php'; $media = $media2; ?></div>
</div></div></div>

<div class="card" data-repeater><div class="hd"><h2>Μάρκες</h2><div class="row-flex"><label class="check"><input type="checkbox" name="brands_enabled" value="1"<?= $hp['brands']['enabled'] ? ' checked' : '' ?>> Ενεργό</label><button type="button" class="btn btn-sm" data-add>+ Μάρκα</button></div></div><div class="bd form">
  <div class="field"><label>Τίτλος</label><input name="brands_title" value="<?= e($hp['brands']['title']) ?>"></div>
  <div class="rep"><?php foreach ($hp['brands']['items'] as $b): ?><div class="rep-row" style="--cols:3"><input data-name="brands[__i__][name]" value="<?= e($b['name']) ?>" placeholder="Όνομα" aria-label="Όνομα"><input data-name="brands[__i__][url]" value="<?= e($b['url']) ?>" placeholder="Σύνδεσμος" aria-label="Σύνδεσμος"><input data-name="brands[__i__][image_id]" value="<?= e($b['image_id']) ?>" placeholder="ID εικόνας λογότυπου (Πολυμέσα)" aria-label="Εικόνα"><button type="button" class="btn rm">✕</button></div><?php endforeach; ?></div>
  <template><div class="rep-row" style="--cols:3"><input data-name="brands[__i__][name]" placeholder="Όνομα" aria-label="Όνομα"><input data-name="brands[__i__][url]" placeholder="Σύνδεσμος" aria-label="Σύνδεσμος"><input data-name="brands[__i__][image_id]" placeholder="ID εικόνας λογότυπου (Πολυμέσα)" aria-label="Εικόνα"><button type="button" class="btn rm">✕</button></div></template>
</div></div>

<div class="card"><div class="hd"><h2>Newsletter</h2><label class="check"><input type="checkbox" name="newsletter_enabled" value="1"<?= $hp['newsletter']['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div><div class="bd form"><div class="fg">
  <div class="field"><label>Τίτλος</label><input name="newsletter_title" value="<?= e($hp['newsletter']['title']) ?>"></div><div class="field"><label>Κείμενο</label><input name="newsletter_text" value="<?= e($hp['newsletter']['text']) ?>"></div>
</div></div></div>
<div><button class="btn btn-primary btn-lg">Αποθήκευση αρχικής</button></div>
</form>
