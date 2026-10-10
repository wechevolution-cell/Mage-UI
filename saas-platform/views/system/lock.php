<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($name) ?> — Θα επιστρέψουμε σύντομα</title>
<?php if (is_file(ROOT . '/templates/' . $templateKey . '/assets/fonts.css')): ?><link rel="stylesheet" href="/templates/<?= e($templateKey) ?>/assets/fonts.css"><?php endif; ?>
<style>
:root{--bg:<?= e($tokens['background'] ?? '#F4F5F7') ?>;--fg:<?= e($tokens['text'] ?? '#111') ?>;--muted:<?= e($tokens['muted'] ?? '#666') ?>;--accent:<?= e($tokens['accent'] ?? '#333') ?>;--surface:<?= e($tokens['surface'] ?? '#fff') ?>;color-scheme:light}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--fg);font:16px/1.6 -apple-system,"Segoe UI",Roboto,"Noto Sans",sans-serif;padding:24px}
main{max-width:560px;width:100%;text-align:center;display:grid;gap:22px;justify-items:center}
.logo img{max-height:64px;width:auto}
.logo span{font:600 28px/1.1 <?= $font ? '"' . e($font) . '",' : '' ?>Georgia,serif;letter-spacing:.02em}
.mark{width:72px;height:72px;border-radius:50%;background:var(--surface);display:grid;place-items:center;box-shadow:0 10px 30px rgba(0,0,0,.08)}
.mark svg{width:30px;height:30px;stroke:var(--accent);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
h1{margin:0;font:600 clamp(24px,4vw,34px)/1.2 <?= $font ? '"' . e($font) . '",' : '' ?>Georgia,serif;text-wrap:balance}
p{margin:0;color:var(--muted);max-width:44ch}
.line{width:56px;height:2px;background:var(--accent);border-radius:2px}
</style>
</head>
<body>
<main>
  <div class="logo"><?= $logo ? media_img($logo, '240px', '', true, $name) : '<span>' . e($name) . '</span>' ?></div>
  <div class="mark" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
  <h1>Θα επιστρέψουμε σύντομα</h1>
  <div class="line" aria-hidden="true"></div>
  <p><?= e($message) ?></p>
</main>
</body>
</html>
