<nav class="tabbar" aria-label="Γρήγορη πλοήγηση">
  <a href="/"<?= req_path() === '/' ? ' aria-current="page"' : '' ?>><?= icon('store') ?><span>Αρχική</span></a>
  <a href="/products/"<?= str_starts_with(req_path(), '/products') || str_starts_with(req_path(), '/category') ? ' aria-current="page"' : '' ?>><?= icon('grid') ?><span>Κατηγορίες</span></a>
  <button type="button" data-search-open><?= icon('search') ?><span>Αναζήτηση</span></button>
  <a href="/wishlist/"<?= req_path() === '/wishlist/' ? ' aria-current="page"' : '' ?>><?= icon('heart') ?><span>Αγαπημένα</span></a>
  <button type="button" data-cart-open><?= icon('bag') ?><span>Καλάθι</span><b class="badge-count" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= (int) $cartCount ?></b></button>
</nav>
