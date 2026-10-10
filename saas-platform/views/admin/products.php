<?php $qs = http_build_query(array_diff_key($_GET, ['page' => 1])); ?>
<div class="page-head"><div><h1>Προϊόντα</h1><p>Μόνο τα ενεργά προϊόντα εμφανίζονται στο κατάστημα. Τα ανενεργά μένουν αποθηκευμένα.</p></div>
  <div class="actions"><?php if (ShopAdmin::feature('export')): ?><a class="btn" href="/admin/import"><?= icon('download') ?>Εξαγωγή</a><?php endif; ?><a class="btn btn-primary" href="/admin/products/new"><?= icon('plus') ?>Νέο προϊόν</a></div></div>
<?php include __DIR__ . '/_quota.php'; ?>
<div class="card">
  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= e(in_str('q')) ?>" placeholder="Όνομα, SKU, barcode, μάρκα…" aria-label="Αναζήτηση">
    <select name="status" data-autosubmit aria-label="Κατάσταση"><option value="">Όλα</option><?php foreach (['active' => 'Ενεργά', 'inactive' => 'Ανενεργά', 'out' => 'Εξαντλημένα', 'featured' => 'Προτεινόμενα'] as $k => $l): ?><option value="<?= $k ?>"<?= in_str('status') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="category" data-autosubmit aria-label="Κατηγορία"><option value="">Όλες οι κατηγορίες</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"<?= in_int('category') === (int) $c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="sort" data-autosubmit aria-label="Ταξινόμηση"><?php foreach (['newest' => 'Νεότερα', 'oldest' => 'Παλαιότερα', 'updated' => 'Τελευταία ενημέρωση', 'name' => 'Όνομα', 'price_asc' => 'Τιμή ↑', 'price_desc' => 'Τιμή ↓', 'stock' => 'Απόθεμα ↑'] as $k => $l): ?><option value="<?= $k ?>"<?= in_str('sort', 'newest') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="per" data-autosubmit aria-label="Ανά σελίδα"><?php foreach ([25, 50, 100, 200] as $n): ?><option value="<?= $n ?>"<?= in_int('per', 50) === $n ? ' selected' : '' ?>><?= $n ?> / σελίδα</option><?php endforeach; ?></select>
    <button class="btn">Φίλτρο</button>
  </form>
  <div class="bulkbar" id="bulkbar">
    <b><span data-bulk-count>0</span> επιλεγμένα</b>
    <form method="post" action="/admin/products/bulk" class="row-flex"><?= csrf_field() ?><input type="hidden" name="_qs" value="<?= e($qs) ?>">
      <button class="btn btn-sm" name="action" value="activate">Ενεργοποίηση</button><button class="btn btn-sm" name="action" value="deactivate">Απενεργοποίηση</button>
      <?php if (ShopAdmin::feature('export')): ?><button class="btn btn-sm" name="action" value="export">Εξαγωγή CSV</button><?php endif; ?>
      <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="Διαγραφή των επιλεγμένων προϊόντων; Οι παραγγελίες που τα περιέχουν δεν επηρεάζονται." data-confirm-type="ΔΙΑΓΡΑΦΗ">Διαγραφή</button>
    </form>
    <form method="post" action="/admin/products/bulk" class="row-flex"><?= csrf_field() ?><input type="hidden" name="_qs" value="<?= e($qs) ?>"><input type="hidden" name="action" value="category">
      <select name="category_id" style="width:auto;height:30px" aria-label="Κατηγορία"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= str_repeat('— ', (int) $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select><label class="check"><input type="checkbox" name="set_primary" value="1"> κύρια</label><button class="btn btn-sm">Ανάθεση κατηγορίας</button></form>
    <form method="post" action="/admin/products/bulk" class="row-flex"><?= csrf_field() ?><input type="hidden" name="_qs" value="<?= e($qs) ?>"><input type="hidden" name="action" value="price">
      <select name="price_field" style="width:auto;height:30px" aria-label="Πεδίο"><option value="price">Τιμή</option><option value="sale_price">Τιμή προσφοράς</option></select>
      <input name="percent" placeholder="± %" style="width:70px;height:30px" aria-label="Ποσοστό"><input name="fixed" placeholder="ή € σταθερή" style="width:100px;height:30px" aria-label="Σταθερή τιμή"><button class="btn btn-sm" data-confirm="Ενημέρωση τιμών στα επιλεγμένα προϊόντα;">Ενημέρωση τιμών</button></form>
  </div>
  <div class="table-wrap" data-bulk-table="#bulkbar">
  <?php if (!$rows): ?><div class="empty"><h3>Δεν βρέθηκαν προϊόντα</h3><p>Προσθέστε προϊόν ή εισάγετε από CSV/XML.</p><a class="btn btn-primary" href="/admin/products/new">Νέο προϊόν</a></div><?php else: ?>
  <table class="table"><thead><tr><th style="width:34px"><input type="checkbox" data-bulk-all aria-label="Επιλογή όλων"></th><th></th><th>Προϊόν</th><th>Κατηγορία</th><th class="r">Τιμή</th><th class="r">Απόθεμα</th><th>Ενεργό</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $p): ?>
    <tr>
      <td><input type="checkbox" data-bulk value="<?= $p['id'] ?>" aria-label="Επιλογή <?= e($p['name']) ?>"></td>
      <td><?php if ($p['image']): ?><img class="thumb" src="<?= e(media_url($p['image'], 400)) ?>" alt="" loading="lazy" width="44" height="44"><?php else: ?><span class="thumb"></span><?php endif; ?></td>
      <td><a class="title" href="/admin/products/<?= $p['id'] ?>"><?= e($p['name']) ?></a><div class="sub">SKU <?= e($p['sku'] ?: '—') ?><?= $p['featured'] ? ' · ★ Προτεινόμενο' : '' ?><?= $p['has_variants'] ? ' · Παραλλαγές' : '' ?></div></td>
      <td class="sub"><?= e($p['cat_name'] ?? '—') ?></td>
      <td class="r num"><?php if (Products::onSale($p)): ?><s class="muted"><?= money((float) $p['price']) ?></s><br><?php endif; ?><b><?= money(Products::effectivePrice($p)) ?></b></td>
      <td class="r num"><?= $p['manage_stock'] ? (int) $p['stock'] : '∞' ?><?= $p['availability'] === 'out_of_stock' ? '<div class="sub">Εξαντλημένο</div>' : '' ?></td>
      <td><label class="check" title="Ενεργοποίηση με έλεγχο ορίου πακέτου"><input type="checkbox" data-toggle-active="/admin/products/<?= $p['id'] ?>/toggle"<?= $p['is_active'] ? ' checked' : '' ?> aria-label="Ενεργό"><span class="sr">Ενεργό</span></label></td>
      <td class="actions"><a class="btn btn-sm" href="/admin/products/<?= $p['id'] ?>"><?= icon('edit') ?></a> <?php if ($p['is_active']): ?><a class="btn btn-sm btn-ghost" href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener" aria-label="Προβολή"><?= icon('eye') ?></a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody></table><?php endif; ?>
  </div>
  <?php include __DIR__ . '/_pager.php'; ?>
</div>
