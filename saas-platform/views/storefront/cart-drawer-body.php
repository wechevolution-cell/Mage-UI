<?php $t = $totals; ?>
<?php if ($t['free_threshold'] > 0 && $lines): ?>
  <div class="freebar"><?php if ($t['to_free'] > 0): ?>Ακόμη <b><?= money($t['to_free']) ?></b> για δωρεάν αποστολή<?php else: ?><b>Έχετε δωρεάν αποστολή</b><?php endif; ?><span class="bar"><i style="width:<?= min(100, (int) round($t['subtotal'] / $t['free_threshold'] * 100)) ?>%"></i></span></div>
<?php endif; ?>
<?php if (!$lines): ?>
  <div class="cart-empty"><?= icon('bag') ?><p>Το καλάθι σας είναι άδειο.</p><a class="btn btn--primary" href="/products/" data-cart-close>Συνέχεια αγορών</a></div>
<?php else: ?>
  <ul class="cart-lines">
  <?php foreach ($lines as $l): ?>
    <li class="cart-line">
      <a class="cl-img" href="<?= e(Products::url($l['product'])) ?>"><?= sf_img($l['product']['image'] ?? null, '96px') ?></a>
      <div class="cl-info"><a class="cl-name" href="<?= e(Products::url($l['product'])) ?>"><?= e($l['name']) ?></a><?php if ($l['options']): ?><span class="cl-opt"><?= e($l['options']) ?></span><?php endif; ?>
        <form class="cl-qty" method="post" action="/cart/update/" data-ajax-cart><?= csrf_field() ?>
          <div class="qty qty--sm" data-qty><button type="button" data-step="-1" aria-label="Μείωση"><?= icon('minus') ?></button><label class="sr" for="q-<?= e($l['key']) ?>">Ποσότητα</label><input id="q-<?= e($l['key']) ?>" type="number" name="qty[<?= e($l['key']) ?>]" value="<?= (int) $l['qty'] ?>" min="0" max="<?= (int) ($l['max'] ?? 99) ?>" data-autosubmit-qty><button type="button" data-step="1" aria-label="Αύξηση"><?= icon('plus') ?></button></div>
          <button class="link-btn cl-rm" name="remove" value="<?= e($l['key']) ?>">Αφαίρεση</button>
        </form></div>
      <span class="cl-total"><?= money($l['total']) ?></span>
    </li>
  <?php endforeach; ?>
  </ul>
  <div class="cart-sum">
    <div class="row"><span>Υποσύνολο</span><b><?= money($t['subtotal']) ?></b></div>
    <div class="row muted"><span>Αποστολή</span><span><?= $t['shipping'] > 0 ? 'από ' . money($t['shipping']) : 'Δωρεάν' ?></span></div>
    <div class="row total"><span>Σύνολο</span><b><?= money($t['total']) ?></b></div>
    <a class="btn btn--primary btn--block" href="/checkout/">Ολοκλήρωση παραγγελίας</a>
    <a class="btn btn--ghost btn--block" href="/cart/">Προβολή καλαθιού</a>
  </div>
<?php endif; ?>
