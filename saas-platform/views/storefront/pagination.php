<?php if ($pg['pages'] > 1): ?>
<nav class="pagination" aria-label="Σελίδες">
  <?php if ($pg['page'] > 1): ?><a href="<?= e(query_with(['page' => $pg['page'] - 1 > 1 ? $pg['page'] - 1 : null])) ?>" rel="prev" aria-label="Προηγούμενη"><?= icon('chevron-left') ?></a><?php endif; ?>
  <?php $s = max(1, $pg['page'] - 2); $en = min($pg['pages'], $pg['page'] + 2); if ($s > 1): ?><a href="<?= e(query_with(['page' => null])) ?>">1</a><?php if ($s > 2): ?><span>…</span><?php endif; endif; ?>
  <?php for ($i = $s; $i <= $en; $i++): ?><?= $i === $pg['page'] ? '<span aria-current="page">' . $i . '</span>' : '<a href="' . e(query_with(['page' => $i > 1 ? $i : null])) . '">' . $i . '</a>' ?><?php endfor; ?>
  <?php if ($en < $pg['pages']): if ($en < $pg['pages'] - 1): ?><span>…</span><?php endif; ?><a href="<?= e(query_with(['page' => $pg['pages']])) ?>"><?= $pg['pages'] ?></a><?php endif; ?>
  <?php if ($pg['page'] < $pg['pages']): ?><a href="<?= e(query_with(['page' => $pg['page'] + 1])) ?>" rel="next" aria-label="Επόμενη"><?= icon('chevron-right') ?></a><?php endif; ?>
</nav>
<?php endif; ?>
