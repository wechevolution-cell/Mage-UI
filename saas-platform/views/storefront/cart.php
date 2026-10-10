<div class="wrap page-pad">
  <?= partial('breadcrumbs', ['crumbs' => [['Αρχική', '/'], ['Καλάθι', null]]]) ?>
  <h1 class="page-title">Καλάθι</h1>
  <?php if (!$lines): ?><div class="empty-state"><h2>Το καλάθι σας είναι άδειο</h2><p>Ανακαλύψτε τα προϊόντα μας.</p><a class="btn btn--primary" href="/products/">Συνέχεια αγορών</a></div>
  <?php else: ?>
  <div class="cart-page">
    <form class="cart-table" method="post" action="/cart/update/"><?= csrf_field() ?>
      <table class="tbl"><thead><tr><th>Προϊόν</th><th>Τιμή</th><th>Ποσότητα</th><th class="r">Σύνολο</th><th><span class="sr">Ενέργειες</span></th></tr></thead><tbody>
      <?php foreach ($lines as $l): ?><tr>
        <td><div class="ct-prod"><a href="<?= e(Products::url($l['product'])) ?>" class="ct-img"><?= sf_img($l['product']['image'] ?? null, '80px') ?></a><span><a href="<?= e(Products::url($l['product'])) ?>"><?= e($l['name']) ?></a><?php if ($l['options']): ?><small><?= e($l['options']) ?></small><?php endif; ?><?php if ($l['sku']): ?><small>Κωδ. <?= e($l['sku']) ?></small><?php endif; ?></span></div></td>
        <td data-label="Τιμή"><?= money($l['unit']) ?><?= $l['unit'] < $l['regular'] ? '<br><s class="muted">' . money($l['regular']) . '</s>' : '' ?></td>
        <td data-label="Ποσότητα"><div class="qty" data-qty><button type="button" data-step="-1" aria-label="Μείωση"><?= icon('minus') ?></button><label class="sr" for="cq-<?= e($l['key']) ?>">Ποσότητα</label><input id="cq-<?= e($l['key']) ?>" type="number" name="qty[<?= e($l['key']) ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= (int) ($l['max'] ?? 99) ?>"><button type="button" data-step="1" aria-label="Αύξηση"><?= icon('plus') ?></button></div></td>
        <td class="r" data-label="Σύνολο"><b><?= money($l['total']) ?></b></td>
        <td><button class="icon-btn" name="remove" value="<?= e($l['key']) ?>" aria-label="Αφαίρεση <?= e($l['name']) ?>"><?= icon('close') ?></button></td>
      </tr><?php endforeach; ?></tbody></table>
      <div class="row-btns"><a class="btn btn--ghost" href="/products/"><?= icon('arrow-left') ?>Συνέχεια αγορών</a><button class="btn btn--ghost">Ενημέρωση καλαθιού</button></div>
    </form>
    <aside class="summary"><h2>Σύνοψη</h2>
      <?php if ($totals['free_threshold'] > 0): ?><div class="freebar"><?= $totals['to_free'] > 0 ? 'Ακόμη <b>' . money($totals['to_free']) . '</b> για δωρεάν αποστολή' : '<b>Έχετε δωρεάν αποστολή</b>' ?><span class="bar"><i style="width:<?= min(100, (int) round($totals['subtotal'] / $totals['free_threshold'] * 100)) ?>%"></i></span></div><?php endif; ?>
      <div class="row"><span>Υποσύνολο</span><b><?= money($totals['subtotal']) ?></b></div>
      <div class="row muted"><span>Εκτίμηση αποστολής</span><span><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Δωρεάν' ?></span></div>
      <div class="row total"><span>Σύνολο</span><b><?= money($totals['total']) ?></b></div>
      <small class="muted">Περιλαμβάνεται ΦΠΑ. Το τελικό κόστος αποστολής υπολογίζεται στο ταμείο.</small>
      <a class="btn btn--primary btn--block" href="/checkout/">Ολοκλήρωση παραγγελίας</a>
    </aside>
  </div>
  <?php if ($suggest): ?><section class="sec"><div class="sec-head"><h2>Μήπως σας ενδιαφέρουν</h2></div><div class="grid-products"><?php foreach ($suggest as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach; ?></div></section><?php endif; ?>
  <?php endif; ?>
</div>
