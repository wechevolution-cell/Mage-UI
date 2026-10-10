<form method="post" action="/admin/appearance" class="grid"><?= csrf_field() ?>
<div class="page-head"><div><h1>Εμφάνιση</h1><p>Template: <b><?= e($manifest['name'] ?? '') ?></b> (<?= e($manifest['industry'] ?? '') ?>). Μπορείτε να αλλάξετε λογότυπο, χρώματα και στοιχεία· η διάταξη του template προστατεύεται.</p></div><div class="actions"><a class="btn" href="/" target="_blank" rel="noopener"><?= icon('eye') ?>Προβολή</a><button class="btn btn-primary">Αποθήκευση</button></div></div>
<div class="grid g2">
  <div class="card"><div class="hd"><h2>Λογότυπο & favicon</h2></div><div class="bd form">
    <?php $name = 'logo_id'; $media = $logo; $label = 'Λογότυπο'; $hint = 'PNG/WebP με διαφανές φόντο, ύψος ≥ 120px.'; include __DIR__ . '/_img_pick.php'; ?>
    <div class="field"><label>Κείμενο λογότυπου (αν δεν υπάρχει εικόνα)</label><input name="logo_text" value="<?= e($app['logo_text'] ?? '') ?>" placeholder="<?= e(ShopSettings::storeName($db, $shop)) ?>"></div>
    <?php $name = 'favicon_id'; $media = $favicon; $label = 'Favicon'; $hint = 'Τετράγωνη εικόνα, τουλάχιστον 64×64.'; include __DIR__ . '/_img_pick.php'; ?>
    <?php if ($canFonts): ?><div class="field"><label>Γραμματοσειρά</label><select name="font"><?php foreach (['default' => 'Του template', 'serif' => 'Serif (κομψή)', 'sans' => 'Sans (καθαρή)', 'rounded' => 'Στρογγυλεμένη (φιλική)'] as $k => $l): ?><option value="<?= $k ?>"<?= ($app['font'] ?? 'default') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div><?php endif; ?>
  </div></div>
  <div class="card"><div class="hd"><div><h2>Χρώματα (design tokens)</h2><p>Προκαθορισμένα tokens εξασφαλίζουν ότι η διάταξη δεν «σπάει».</p></div><button class="btn btn-sm" name="reset_tokens" value="1" data-confirm="Επαναφορά των χρωμάτων του template;">Επαναφορά</button></div><div class="bd"><div class="fg">
    <?php foreach (ShopSettings::TOKEN_LABELS as $k => $label): if (!isset($tokens[$k])) continue; ?>
      <div class="field"><label for="t_<?= $k ?>"><?= e($label) ?></label><div class="color-row"><input type="color" value="<?= e($tokens[$k]) ?>" aria-label="<?= e($label) ?>"><input type="text" id="t_<?= $k ?>" name="t_<?= $k ?>" value="<?= e($tokens[$k]) ?>" pattern="#[0-9A-Fa-f]{6}"><?= strtoupper($tokens[$k]) !== strtoupper($defaults[$k]) ? '<span class="badge b-info plain">αλλαγμένο</span>' : '' ?></div></div>
    <?php endforeach; ?>
  </div><p class="hint muted" style="margin-top:10px;font-size:12px">Ελέγξτε την αντίθεση κειμένου/φόντου για αναγνωσιμότητα (WCAG AA).</p></div></div>
</div>
<?php if ($choices): ?>
<div class="card"><div class="hd"><div><h2>Template</h2><p>Ο πάροχος σας επιτρέπει να επιλέξετε ανάμεσα στα παρακάτω. Η αλλαγή δεν επηρεάζει προϊόντα, παραγγελίες ή ρυθμίσεις.</p></div></div><div class="bd"><div class="tpl-grid">
  <?php foreach ($choices as $t): $tk = Templates::tokens($t['key']); ?><label class="tpl"><input type="radio" name="template_key" value="<?= e($t['key']) ?>"<?= $shop['template_key'] === $t['key'] ? ' checked' : '' ?>><span class="sw"><span style="background:<?= e($tk['background'] ?? '') ?>"></span><span style="background:<?= e($tk['primary'] ?? '') ?>"></span><span style="background:<?= e($tk['accent'] ?? '') ?>"></span></span><span class="meta"><b><?= e($t['name']) ?></b><small class="muted"><?= e($t['industry']) ?></small></span></label><?php endforeach; ?>
</div></div></div>
<?php endif; ?>
</form>
