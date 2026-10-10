<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Σύνδεση') ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body data-scope="<?= e($scope ?? 'sa') ?>">
<main class="auth">
  <div class="box<?= !empty($wide) ? ' wide' : '' ?>">
    <div class="logo"><i><?= e(mb_substr($brand ?? 'W', 0, 1)) ?></i><span><?= e($brand ?? 'wechevo e-shop') ?></span></div>
    <?php $fl = flashes(); foreach ($fl as $f): ?><div class="alert <?= e($f['type']) ?>" role="alert"><div><?= e($f['msg']) ?></div></div><?php endforeach; ?>
    <?= $content ?>
  </div>
</main>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body>
</html>
