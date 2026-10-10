<?php
$v = fn($k, $d = '') => e($prefill[$k] ?? $d);
$err = fn($k) => isset($errors[$k]) ? '<span class="field-err" id="err-' . $k . '">' . e($errors[$k]) . '</span>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
$zones = array_filter($shipping['zones'], fn($z) => $z['enabled']);
?>
<div class="wrap page-pad">
  <?= partial('breadcrumbs', ['crumbs' => [['Αρχική', '/'], ['Καλάθι', '/cart/'], ['Ταμείο', null]]]) ?>
  <h1 class="page-title">Ολοκλήρωση παραγγελίας</h1>
  <?php if (!$customer): ?><p class="muted">Έχετε λογαριασμό; <a href="/account/login/?next=checkout">Συνδεθείτε</a> — ή συνεχίστε ως επισκέπτης.</p><?php endif; ?>
  <form class="checkout" method="post" action="/checkout/" data-checkout novalidate><?= csrf_field() ?>
    <div class="co-main">
      <fieldset class="co-step"><legend><span class="n">1</span>Στοιχεία επικοινωνίας</legend><div class="fgrid">
        <div class="field full"><label for="co-email">Email *</label><input id="co-email" type="email" name="email" required autocomplete="email" value="<?= $v('email') ?>"<?= $inv('email') ?>><?= $err('email') ?></div>
        <div class="field"><label for="co-fn">Όνομα *</label><input id="co-fn" name="first_name" required autocomplete="given-name" value="<?= $v('first_name') ?>"<?= $inv('first_name') ?>><?= $err('first_name') ?></div>
        <div class="field"><label for="co-ln">Επώνυμο *</label><input id="co-ln" name="last_name" required autocomplete="family-name" value="<?= $v('last_name') ?>"<?= $inv('last_name') ?>><?= $err('last_name') ?></div>
        <div class="field full"><label for="co-ph">Κινητό τηλέφωνο *</label><input id="co-ph" type="tel" name="phone" required autocomplete="tel" value="<?= $v('phone') ?>"<?= $inv('phone') ?>><?= $err('phone') ?></div>
      </div></fieldset>
      <fieldset class="co-step"><legend><span class="n">2</span>Αποστολή</legend>
        <div class="radios">
          <?php foreach ($zones as $k => $z): ?><label class="radio-card"><input type="radio" name="shipping_zone" value="<?= e($k) ?>" data-ship="<?= e((string) $z['cost']) ?>"<?= ($prefill['shipping_zone'] ?? array_key_first($zones)) === $k ? ' checked' : '' ?>><span><b><?= e($z['label']) ?></b><small><?= e($shipping['delivery_text']) ?></small></span><span class="rc-price"><?= $totals['free_threshold'] > 0 && $totals['subtotal'] >= $totals['free_threshold'] ? 'Δωρεάν' : money((float) $z['cost']) ?></span></label><?php endforeach; ?>
          <?php if ($shipping['pickup']['enabled']): ?><label class="radio-card"><input type="radio" name="shipping_zone" value="pickup" data-ship="0" data-pickup<?= ($prefill['shipping_zone'] ?? '') === 'pickup' ? ' checked' : '' ?>><span><b><?= e($shipping['pickup']['label']) ?></b><small><?= e($shipping['pickup']['address']) ?></small></span><span class="rc-price">Δωρεάν</span></label><?php endif; ?>
        </div><?= $err('shipping_zone') ?>
        <div class="fgrid" data-address>
          <div class="field full"><label for="co-ad">Διεύθυνση (οδός, αριθμός) *</label><input id="co-ad" name="address" autocomplete="street-address" value="<?= $v('address') ?>"<?= $inv('address') ?>><?= $err('address') ?></div>
          <div class="field"><label for="co-ci">Πόλη *</label><input id="co-ci" name="city" autocomplete="address-level2" value="<?= $v('city') ?>"<?= $inv('city') ?>><?= $err('city') ?></div>
          <div class="field"><label for="co-pc">Τ.Κ. *</label><input id="co-pc" name="postcode" autocomplete="postal-code" inputmode="numeric" value="<?= $v('postcode') ?>"<?= $inv('postcode') ?>><?= $err('postcode') ?></div>
          <div class="field full"><label for="co-rg">Νομός / περιοχή</label><input id="co-rg" name="region" autocomplete="address-level1" value="<?= $v('region') ?>"></div>
          <?php if ($customer): ?><label class="check full"><input type="checkbox" name="save_address" value="1" checked> Αποθήκευση διεύθυνσης στον λογαριασμό μου</label><?php endif; ?>
        </div>
      </fieldset>
      <fieldset class="co-step"><legend><span class="n">3</span>Πληρωμή</legend>
        <div class="radios"><?php $first = true; foreach (['cod', 'bank'] as $k): if (empty($payments[$k]['enabled'])) continue; ?><label class="radio-card"><input type="radio" name="payment_method" value="<?= $k ?>" data-fee="<?= e((string) $payments[$k]['fee']) ?>"<?= ($prefill['payment_method'] ?? ($first ? $k : '')) === $k ? ' checked' : '' ?>><span><b><?= e($payments[$k]['label']) ?></b><small><?= e(excerpt($payments[$k]['instructions'], 90)) ?></small></span><span class="rc-price"><?= (float) $payments[$k]['fee'] > 0 ? '+' . money((float) $payments[$k]['fee']) : '' ?></span></label><?php $first = false; endforeach; ?></div><?= $err('payment_method') ?>
        <?php if ($checkoutCfg['invoice_enabled']): ?><details class="invoice"><summary><label class="check"><input type="checkbox" name="invoice" value="1"<?= !empty($prefill['invoice']) ? ' checked' : '' ?>> Θέλω τιμολόγιο</label></summary><div class="fgrid">
          <div class="field full"><label for="co-co">Επωνυμία</label><input id="co-co" name="company" value="<?= $v('company') ?>"></div><div class="field"><label for="co-vat">ΑΦΜ</label><input id="co-vat" name="vat_number" value="<?= $v('vat_number') ?>"></div><div class="field"><label for="co-to">ΔΟΥ</label><input id="co-to" name="tax_office" value="<?= $v('tax_office') ?>"></div><div class="field full"><label for="co-act">Δραστηριότητα</label><input id="co-act" name="activity" value="<?= $v('activity') ?>"></div>
        </div></details><?php endif; ?>
        <?php if ($checkoutCfg['gift_wrap_enabled']): ?><label class="check"><input type="checkbox" name="gift_wrap" value="1" data-gift="<?= e((string) $checkoutCfg['gift_wrap_price']) ?>"<?= !empty($prefill['gift_wrap']) ? ' checked' : '' ?>> Συσκευασία δώρου (+<?= money((float) $checkoutCfg['gift_wrap_price']) ?>)</label><?php endif; ?>
        <div class="field"><label for="co-notes">Σχόλια παραγγελίας</label><textarea id="co-notes" name="notes" rows="3"><?= $v('notes') ?></textarea></div>
      </fieldset>
    </div>
    <aside class="summary co-summary" aria-label="Σύνοψη παραγγελίας"><h2>Η παραγγελία σας</h2>
      <ul class="sum-lines"><?php foreach ($lines as $l): ?><li><span class="sl-img"><?= sf_img($l['product']['image'] ?? null, '64px') ?><b><?= (int) $l['qty'] ?></b></span><span class="sl-name"><?= e($l['name']) ?><?php if ($l['options']): ?><small><?= e($l['options']) ?></small><?php endif; ?></span><span><?= money($l['total']) ?></span></li><?php endforeach; ?></ul>
      <div class="row"><span>Υποσύνολο</span><b data-subtotal="<?= e((string) $totals['subtotal']) ?>"><?= money($totals['subtotal']) ?></b></div>
      <div class="row"><span>Αποστολή</span><span data-ship-out><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Δωρεάν' ?></span></div>
      <div class="row" data-fee-row hidden><span>Χρέωση πληρωμής</span><span data-fee-out></span></div>
      <div class="row" data-gift-row hidden><span>Συσκευασία δώρου</span><span data-gift-out></span></div>
      <div class="row total"><span>Σύνολο</span><b data-total-out><?= money($totals['total']) ?></b></div>
      <small class="muted">Περιλαμβάνεται ΦΠΑ <?= (int) $totals['vat_rate'] ?>%.</small>
      <label class="check"><input type="checkbox" name="newsletter" value="1"> Θέλω να λαμβάνω ενημερώσεις και προσφορές</label>
      <label class="check"><input type="checkbox" name="accept_terms" value="1" required<?= $inv('accept_terms') ?>> <span>Έχω διαβάσει και αποδέχομαι τους <a href="<?= e($termsUrl) ?>" target="_blank">Όρους χρήσης</a> και την <a href="<?= e($privacyUrl) ?>" target="_blank">Πολιτική απορρήτου</a> *</span></label><?= $err('accept_terms') ?>
      <button class="btn btn--primary btn--block btn--lg">Ολοκλήρωση παραγγελίας</button>
      <p class="muted secure"><?= icon('shield') ?>Τα στοιχεία σας μεταδίδονται με ασφάλεια.</p>
    </aside>
  </form>
</div>
