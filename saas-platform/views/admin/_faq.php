<?php /** @var array $faq */ ?>
<div class="card" data-repeater><div class="hd"><div><h2>Συχνές ερωτήσεις (FAQ)</h2><p>Εμφανίζονται στη σελίδα και ως FAQPage structured data (AEO).</p></div><button type="button" class="btn btn-sm" data-add><?= icon('plus') ?>Ερώτηση</button></div>
  <div class="bd"><div class="rep" style="--cols:1">
    <?php foreach ($faq as $f): ?><div class="rep-row" style="--cols:2"><input data-name="faq[__i__][q]" value="<?= e($f['q']) ?>" placeholder="Ερώτηση" aria-label="Ερώτηση"><textarea data-name="faq[__i__][a]" rows="2" placeholder="Απάντηση" aria-label="Απάντηση"><?= e(strip_tags($f['a'], '<a><strong><em><br><ul><li>')) ?></textarea><button type="button" class="btn rm" aria-label="Αφαίρεση">✕</button></div><?php endforeach; ?>
  </div></div>
  <template><div class="rep-row" style="--cols:2"><input data-name="faq[__i__][q]" placeholder="Ερώτηση" aria-label="Ερώτηση"><textarea data-name="faq[__i__][a]" rows="2" placeholder="Απάντηση" aria-label="Απάντηση"></textarea><button type="button" class="btn rm" aria-label="Αφαίρεση">✕</button></div></template>
</div>
