<?php if (!empty($faq)): ?>
<section class="faq" aria-labelledby="h-faq"><h2 id="h-faq">Συχνές ερωτήσεις</h2>
<?php foreach ($faq as $i => $f): ?><details class="acc"<?= $i === 0 ? ' open' : '' ?>><summary><?= e($f['q']) ?><?= icon('plus', 'ic acc-ic') ?></summary><div class="acc-body prose"><?= Sanitizer::html($f['a']) ?></div></details><?php endforeach; ?>
</section>
<?php endif; ?>
