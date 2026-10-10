<!doctype html>
<html lang="el">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title ?? 'Σφάλμα') ?></title>
<style>
:root{color-scheme:light}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F4F5F7;color:#101828;font:15px/1.6 -apple-system,"Segoe UI",Roboto,"Noto Sans",sans-serif;padding:24px}
main{max-width:520px;text-align:center}b{display:block;font-size:56px;line-height:1;color:#98A2B3;font-weight:800}h1{font-size:24px;margin:12px 0}
a{color:#4F46E5}pre{text-align:left;background:#fff;border:1px solid #E4E7EC;border-radius:8px;padding:12px;white-space:pre-wrap;font-size:12px}
</style></head>
<body><main><b><?= (int) ($code ?? 500) ?></b><h1><?= e($title ?? 'Σφάλμα') ?></h1><p><?= e($message ?? '') ?></p><p><a href="/">Αρχική σελίδα</a></p>
<?php if (!empty($debug)): ?><pre><?= e($debug) ?></pre><?php endif; ?></main></body></html>
