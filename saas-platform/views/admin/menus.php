<div class="page-head"><div><h1>Μενού</h1><p>Συνδέστε στοιχεία μενού με κατηγορίες, προϊόντα, σελίδες, άρθρα ή εξωτερικούς συνδέσμους. Έως 3 επίπεδα.</p></div></div>
<nav class="tabs"><?php foreach (Menus::LOCATIONS as $k => $l): ?><a href="/admin/menus?location=<?= $k ?>"<?= $loc === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
<form method="post" action="/admin/menus" class="card"><?= csrf_field() ?><input type="hidden" name="location" value="<?= e($loc) ?>"><input type="hidden" name="tree" value="">
  <div class="bd" data-menu-builder data-tree="<?= e(je($tree)) ?>" data-options="<?= e(je($opts)) ?>">
    <div class="mb"></div>
    <button type="button" class="btn btn-sm mt" data-mb-add><?= icon('plus') ?>Προσθήκη στοιχείου</button>
  </div>
  <div class="ft"><button class="btn btn-primary">Αποθήκευση μενού</button></div>
</form>
