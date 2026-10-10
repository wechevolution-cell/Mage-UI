<div class="wrap page-pad notfound">
  <p class="eyebrow">Σφάλμα 404</p>
  <h1 class="page-title">Η σελίδα δεν βρέθηκε</h1>
  <p>Η σελίδα που ζητήσατε δεν υπάρχει ή μετακινήθηκε.</p>
  <form class="big-search" action="/search/" role="search"><label class="sr" for="nfq">Αναζήτηση</label><input id="nfq" type="search" name="q" placeholder="Αναζήτηση προϊόντων…"><button class="btn btn--primary">Αναζήτηση</button></form>
  <p><a class="btn btn--ghost" href="/">Αρχική σελίδα</a> <a class="btn btn--ghost" href="/products/">Όλα τα προϊόντα</a></p>
  <?php if ($suggest): ?><section class="sec"><div class="sec-head"><h2>Δημοφιλή προϊόντα</h2></div><div class="grid-products"><?php foreach ($suggest as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach; ?></div></section><?php endif; ?>
</div>
