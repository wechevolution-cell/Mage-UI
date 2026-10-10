<?php
/** @var string $title @var string $content @var string $scope @var array $nav */
$scope = $scope ?? 'sa';
$crumbs = $crumbs ?? [];
?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title ?? 'Διαχείριση') ?> · <?= e($brand ?? 'wechevo e-shop') ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<?php if (!empty($favicon)): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
</head>
<body data-scope="<?= e($scope) ?>" data-media-url="<?= e($mediaUrl ?? '') ?>">
<a class="skip" href="#content">Μετάβαση στο περιεχόμενο</a>
<div class="shell">
  <aside class="side" aria-label="Κύρια πλοήγηση">
    <div class="brand"><i><?= e(mb_substr($brand ?? 'W', 0, 1)) ?></i><span><?= e($brand ?? 'wechevo e-shop') ?><small><?= e($brandSub ?? '') ?></small></span></div>
    <nav>
      <?php foreach ($nav as $sec): ?>
        <?php if (!empty($sec['label'])): ?><div class="sec"><?= e($sec['label']) ?></div><?php endif; ?>
        <?php foreach ($sec['items'] as $it): ?>
          <a href="<?= e($it['href']) ?>"<?= !empty($it['active']) ? ' aria-current="page"' : '' ?>><?= icon($it['icon'] ?? 'file') ?><span><?= e($it['label']) ?></span><?php if (!empty($it['count'])): ?><span class="count"><?= (int) $it['count'] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="foot"><?= $sideFoot ?? '' ?></div>
  </aside>
  <div class="scrim"></div>
  <div class="main">
    <?php if (!empty($impersonating)): ?>
      <div class="imp-bar" role="status"><?= icon('eye') ?> Λειτουργία υποστήριξης: είστε συνδεδεμένοι ως διαχειριστής του καταστήματος. Όλες οι ενέργειες καταγράφονται.
        <form method="post" action="/admin/logout" style="display:inline"><?= csrf_field() ?><button>Έξοδος από την προσομοίωση</button></form></div>
    <?php endif; ?>
    <header class="top">
      <button class="btn btn-sm menu-btn" type="button" aria-label="Μενού"><?= icon('menu') ?></button>
      <div class="crumbs"><?php foreach ($crumbs as $i => [$label, $href]): ?><?= $i ? ' / ' : '' ?><?= $href ? '<a href="' . e($href) . '">' . e($label) . '</a>' : e($label) ?><?php endforeach; ?></div>
      <div class="right"><?= $topRight ?? '' ?></div>
    </header>
    <main class="content" id="content">
      <?php $fl = flashes(); if ($fl): ?><div class="flashes"><?php foreach ($fl as $f): ?><div class="alert <?= e($f['type']) ?>" role="<?= $f['type'] === 'bad' ? 'alert' : 'status' ?>"><?= icon($f['type'] === 'ok' ? 'check' : ($f['type'] === 'bad' ? 'alert' : 'info')) ?><div><?= e($f['msg']) ?></div></div><?php endforeach; ?></div><?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body>
</html>
