<?php if (!empty($sec['items'])): ?>
<section class="benefits" aria-label="Πλεονεκτήματα"><div class="wrap benefits-grid">
<?php foreach ($sec['items'] as $b): ?><div class="benefit"><span class="benefit-ic"><?= icon($b['icon']) ?></span><span><b><?= e($b['title']) ?></b><?= e($b['text']) ?></span></div><?php endforeach; ?>
</div></section>
<?php endif; ?>
