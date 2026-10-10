<?php
$facetsOn = !empty($facets['attrs']) || !empty($facets['brands']) || $facets['max'] > 0;
$active = [];
foreach ((array) ($_GET['f'] ?? []) as $n => $vals) foreach ((array) $vals as $vv) $active[] = [$n . ': ' . $vv, sf_qs(['f' => array_map(fn($x) => array_values(array_diff((array) $x, [$vv])), (array) $_GET['f'])])];
foreach ((array) ($_GET['brand'] ?? []) as $b) $active[] = [$b, sf_qs(['brand' => array_values(array_diff((array) $_GET['brand'], [$b]))])];
if (in_str('stock') === '1') $active[] = ['Διαθέσιμα', sf_qs(['stock' => null])];
if (in_str('sale') === '1') $active[] = ['Προσφορές', sf_qs(['sale' => null])];
if (in_str('min') !== '' || in_str('max') !== '') $active[] = ['Τιμή ' . in_str('min', '0') . '–' . in_str('max', '∞') . ' €', sf_qs(['min' => null, 'max' => null])];
$catImg = $cat && $cat['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$cat['image_id']]) : null;
?>
<div class="listing">
  <div class="wrap">
    <?= partial('breadcrumbs', ['crumbs' => $crumbs]) ?>
    <header class="listing-head<?= $catImg ? ' has-img' : '' ?>">
      <div class="listing-title"><h1><?= e($heading) ?></h1><?php if ($cat && $cat['description']): ?><p class="listing-desc"><?= e($cat['description']) ?></p><?php endif; ?><span class="listing-count"><?= number_format($res['total'], 0, ',', '.') ?> προϊόντα</span></div>
      <?php if ($catImg): ?><div class="listing-img"><?= sf_img($catImg, '(max-width: 900px) 100vw, 40vw', '', true) ?></div><?php endif; ?>
    </header>
    <?php if ($children): ?><nav class="subcats" aria-label="Υποκατηγορίες"><?php foreach ($children as $c): ?><a class="chip" href="<?= e(Categories::url($c)) ?>"><?= e($c['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <div class="listing-bar">
      <?php if ($facetsOn): ?><button class="btn btn--ghost filters-btn" type="button" data-filters-open><?= icon('filter') ?>Φίλτρα<?= $active ? ' (' . count($active) . ')' : '' ?></button><?php endif; ?>
      <div class="active-filters"><?php foreach ($active as [$label, $href]): ?><a class="chip chip--x" href="<?= e($href) ?>"><?= e($label) ?> <?= icon('close') ?></a><?php endforeach; ?><?php if ($active): ?><a class="link" href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>">Καθαρισμός</a><?php endif; ?></div>
      <form class="sort" method="get"><?= sf_hidden_inputs(['sort', 'page']) ?>
        <label for="sort">Ταξινόμηση</label><select id="sort" name="sort" data-autosubmit><?php foreach (Catalog::SORTS as $k => $l): ?><option value="<?= $k ?>"<?= ($opt['sort'] ?? 'recommended') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
    </div>
    <div class="listing-body<?= $facetsOn ? '' : ' no-filters' ?>">
      <?php if ($facetsOn): ?>
      <aside class="filters" data-filters aria-label="Φίλτρα">
        <form method="get" class="filters-form">
          <?php if (!empty($searchQuery)): ?><input type="hidden" name="q" value="<?= e($searchQuery) ?>"><?php endif; ?>
          <input type="hidden" name="sort" value="<?= e($opt['sort'] ?? '') ?>">
          <div class="filters-head"><h2>Φίλτρα</h2><button class="icon-btn" type="button" data-filters-close aria-label="Κλείσιμο"><?= icon('close') ?></button></div>
          <details class="fgroup" open><summary>Διαθεσιμότητα</summary><label class="check"><input type="checkbox" name="stock" value="1"<?= in_str('stock') === '1' ? ' checked' : '' ?>> Άμεσα διαθέσιμα</label><label class="check"><input type="checkbox" name="sale" value="1"<?= in_str('sale') === '1' ? ' checked' : '' ?>> Σε προσφορά</label></details>
          <?php if ($facets['max'] > 0): ?><details class="fgroup" open><summary>Τιμή (€)</summary><div class="range"><label class="sr" for="pmin">Από</label><input id="pmin" type="number" min="0" step="1" name="min" placeholder="<?= (int) floor($facets['min']) ?>" value="<?= e(in_str('min')) ?>"><span>–</span><label class="sr" for="pmax">Έως</label><input id="pmax" type="number" min="0" step="1" name="max" placeholder="<?= (int) ceil($facets['max']) ?>" value="<?= e(in_str('max')) ?>"></div></details><?php endif; ?>
          <?php if (count($facets['brands']) > 1): ?><details class="fgroup" open><summary>Μάρκα</summary><?php foreach ($facets['brands'] as $b): ?><label class="check"><input type="checkbox" name="brand[]" value="<?= e($b['value']) ?>"<?= in_array($b['value'], (array) ($_GET['brand'] ?? []), true) ? ' checked' : '' ?>> <?= e($b['value']) ?> <span class="n"><?= (int) $b['n'] ?></span></label><?php endforeach; ?></details><?php endif; ?>
          <?php foreach ($facets['attrs'] as $name => $vals): if (count($vals) < 2 && empty($_GET['f'][$name])) continue; ?><details class="fgroup" open><summary><?= e($name) ?></summary><?php foreach ($vals as $vv): ?><label class="check"><input type="checkbox" name="f[<?= e($name) ?>][]" value="<?= e($vv['value']) ?>"<?= in_array($vv['value'], (array) ($_GET['f'][$name] ?? []), true) ? ' checked' : '' ?>> <?= e($vv['value']) ?> <span class="n"><?= (int) $vv['n'] ?></span></label><?php endforeach; ?></details><?php endforeach; ?>
          <div class="filters-actions"><button class="btn btn--primary">Εφαρμογή</button></div>
        </form>
      </aside>
      <?php endif; ?>
      <div class="listing-main">
        <?php if (!$res['items']): ?><div class="empty-state"><h2>Δεν βρέθηκαν προϊόντα</h2><p><?= !empty($searchQuery) ? 'Δοκιμάστε διαφορετικούς όρους αναζήτησης.' : 'Δοκιμάστε να αφαιρέσετε κάποια φίλτρα.' ?></p><a class="btn btn--primary" href="/products/">Όλα τα προϊόντα</a></div>
        <?php else: ?><div class="grid-products"><?php foreach ($res['items'] as $p): ?><?= partial('product-card', ['p' => $p]) ?><?php endforeach; ?></div><?= partial('pagination', ['pg' => $res['pager']]) ?><?php endif; ?>
      </div>
    </div>
    <?php if ($cat && trim(strip_tags((string) $cat['content'])) !== ''): ?><section class="seo-text prose"><?= Sanitizer::html($cat['content']) ?></section><?php endif; ?>
    <?= partial('faq', ['faq' => $faq]) ?>
  </div>
</div>
