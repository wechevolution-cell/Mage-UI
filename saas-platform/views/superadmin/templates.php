<div class="page-head"><div><h1>Templates</h1><p>10 έτοιμα templates. Ενεργοποιήστε/απενεργοποιήστε τα και ορίστε σε ποια πακέτα διατίθενται. Τα templates είναι μόνο παρουσίαση — όλα χρησιμοποιούν το ίδιο σχήμα δεδομένων.</p></div></div>
<form method="post" action="/superadmin/templates/save" class="grid"><?= csrf_field() ?>
  <div class="tpl-grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
  <?php foreach ($templates as $t): $m = $t['manifest']; $tk = $m['tokens'] ?? []; $ap = jd($t['allowed_plans'], []); ?>
    <div class="card">
      <div class="sw" style="display:flex;height:70px;border-radius:10px 10px 0 0;overflow:hidden"><?php foreach (['background', 'surface', 'primary', 'accent', 'secondary'] as $c): ?><span style="flex:1;background:<?= e($tk[$c] ?? '#eee') ?>"></span><?php endforeach; ?></div>
      <div class="bd form">
        <div class="row-flex"><h2><?= e($t['name']) ?></h2><span class="muted"><?= e($t['key']) ?></span><label class="check right"><input type="checkbox" name="enabled[]" value="<?= e($t['key']) ?>"<?= $t['enabled'] ? ' checked' : '' ?>> Ενεργό</label></div>
        <div><b><?= e($t['industry']) ?></b><p class="muted" style="margin:4px 0 0"><?= e($t['description']) ?></p></div>
        <div class="muted" style="font-size:12.5px">Γραμματοσειρές: <?= e(implode(' + ', array_filter((array) ($m['fonts'] ?? [])))) ?> · Καταστήματα: <?= $t['shops'] ?></div>
        <div class="field"><span class="lbl">Διαθέσιμο στα πακέτα</span><div class="row-flex"><?php foreach ($plans as $p): ?><label class="check"><input type="checkbox" name="plans[<?= e($t['key']) ?>][]" value="<?= $p['id'] ?>"<?= !$ap || in_array((int) $p['id'], array_map('intval', $ap), true) ? ' checked' : '' ?>> <?= e($p['name']) ?></label><?php endforeach; ?></div></div>
        <?php if ($previewShops): ?>
        <div class="row-flex"><select aria-label="Κατάστημα για προεπισκόπηση" data-preview-shop style="width:auto;flex:1"><?php foreach ($previewShops as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
          <a class="btn btn-sm" target="_blank" rel="noopener" href="/superadmin/templates/<?= e($t['key']) ?>/preview?shop=<?= $previewShops[0]['id'] ?>" data-preview-link="<?= e($t['key']) ?>"><?= icon('eye') ?>Προεπισκόπηση</a></div>
        <?php else: ?><p class="muted" style="font-size:12px">Δημιουργήστε ένα κατάστημα για προεπισκόπηση με πραγματικά δεδομένα.</p><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <div><button class="btn btn-primary btn-lg">Αποθήκευση ρυθμίσεων</button></div>
</form>
<script>document.addEventListener('change', e => { if (e.target.matches('[data-preview-shop]')) { const a = e.target.parentElement.querySelector('[data-preview-link]'); a.href = '/superadmin/templates/' + a.dataset.previewLink + '/preview?shop=' + e.target.value; } });</script>
