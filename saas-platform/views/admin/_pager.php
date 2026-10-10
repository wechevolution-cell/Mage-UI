<?php $pg = $pg_base ?? $pg; if (($pg['pages'] ?? 1) > 1 || ($pg['total'] ?? 0) > 0): ?>
<nav class="pager" aria-label="Σελίδες"><span class="muted num"><?= $pg['from'] ?>–<?= $pg['to'] ?> από <?= number_format($pg['total'], 0, ',', '.') ?></span>
  <span class="pages"><?php
  $start = max(1, $pg['page'] - 2); $end = min($pg['pages'], $pg['page'] + 2);
  if ($pg['page'] > 1) echo '<a href="' . e(query_with(['page' => $pg['page'] - 1])) . '" aria-label="Προηγούμενη">‹</a>';
  if ($start > 1) echo '<a href="' . e(query_with(['page' => 1])) . '">1</a>' . ($start > 2 ? '<span class="muted">…</span>' : '');
  for ($i = $start; $i <= $end; $i++) echo $i === $pg['page'] ? '<span class="cur">' . $i . '</span>' : '<a href="' . e(query_with(['page' => $i])) . '">' . $i . '</a>';
  if ($end < $pg['pages']) echo ($end < $pg['pages'] - 1 ? '<span class="muted">…</span>' : '') . '<a href="' . e(query_with(['page' => $pg['pages']])) . '">' . $pg['pages'] . '</a>';
  if ($pg['page'] < $pg['pages']) echo '<a href="' . e(query_with(['page' => $pg['page'] + 1])) . '" aria-label="Επόμενη">›</a>';
  ?></span></nav>
<?php endif; ?>
