<div class="wrap page-pad">
  <?= partial('breadcrumbs', ['crumbs' => [['Αρχική', '/'], ['Αγαπημένα', null]]]) ?>
  <h1 class="page-title">Αγαπημένα</h1>
  <div class="grid-products" data-wishlist-grid><?php foreach ($items as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach; ?></div>
  <div class="empty-state" data-wishlist-empty<?= $items ? ' hidden' : '' ?>><h2>Δεν έχετε αγαπημένα ακόμη</h2><p>Πατήστε την καρδιά σε ένα προϊόν για να το αποθηκεύσετε.</p><a class="btn btn--primary" href="/products/">Ανακαλύψτε προϊόντα</a></div>
</div>
