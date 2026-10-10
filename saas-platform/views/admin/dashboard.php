<?php $max = max(1, max(array_column($series, 't'))); ?>
<div class="page-head"><div><h1>Καλώς ήρθατε, <?= e(explode(' ', $user['name'])[0]) ?></h1><p><?= e(ShopSettings::storeName($db, $shop)) ?> · <?= status_badge($shop['status']) ?></p></div>
  <div class="actions"><?php if (ShopAdmin::can('products')): ?><a class="btn btn-primary" href="/admin/products/new"><?= icon('plus') ?>Νέο προϊόν</a><?php endif; ?><?php if (ShopAdmin::can('import') && ShopAdmin::feature('import')): ?><a class="btn" href="/admin/import"><?= icon('upload') ?>Εισαγωγή</a><?php endif; ?></div></div>

<?php if ($daysLeft !== null && $daysLeft <= 10): ?><div class="alert <?= $daysLeft <= 3 ? 'bad' : 'warn' ?>"><?= icon('clock') ?><div>Η συνδρομή σας λήγει σε <b><?= max(0, $daysLeft) ?> ημέρες</b> (<?= fmt_date($sub['end_date']) ?>). Επικοινωνήστε με τον πάροχο για ανανέωση ώστε το κατάστημα να παραμείνει ενεργό.</div></div><?php endif; ?>
<?php if ($shop['status'] === 'pending'): ?><div class="alert info"><?= icon('info') ?><div>Το κατάστημα είναι σε κατάσταση προετοιμασίας και δεν είναι ακόμη δημόσιο.</div></div><?php endif; ?>

<?php include __DIR__ . '/_quota.php'; ?>

<div class="grid g4">
  <div class="card stat"><span class="lbl"><?= icon('receipt') ?>Παραγγελίες σήμερα</span><span class="val num"><?= $stats['orders_today'] ?></span><span class="sub"><?= $stats['orders_month'] ?> τον μήνα</span></div>
  <div class="card stat"><span class="lbl"><?= icon('chart') ?>Πωλήσεις μήνα</span><span class="val num"><?= money($stats['revenue_month']) ?></span></div>
  <div class="card stat <?= $stats['pending'] ? 'warn' : '' ?>"><span class="lbl"><?= icon('clock') ?>Προς επεξεργασία</span><span class="val num"><?= $stats['pending'] ?></span><a class="sub" href="/admin/orders?status=pending">Παραγγελίες</a></div>
  <div class="card stat"><span class="lbl"><?= icon('users') ?>Πελάτες / συνδρομητές</span><span class="val num"><?= $stats['customers'] ?> / <?= $stats['subscribers'] ?></span></div>
</div>

<?php if (!$onbDismissed): $done = count(array_filter($onboarding, fn($o) => $o[1])); ?>
<div class="card"><div class="hd"><div><h2>Ξεκινήστε το κατάστημά σας</h2><p><?= $done ?> από <?= count($onboarding) ?> βήματα ολοκληρώθηκαν</p></div><form method="post" action="/admin/onboarding/dismiss"><?= csrf_field() ?><button class="btn btn-sm btn-ghost">Απόκρυψη</button></form></div>
  <div class="bd"><div class="bar" style="margin-bottom:12px"><i style="width:<?= round($done / count($onboarding) * 100) ?>%"></i></div>
  <ul class="onb"><?php foreach ($onboarding as [$label, $ok, $href]): ?><li class="<?= $ok ? 'done' : '' ?>"><span class="dot"><?= $ok ? '✓' : '' ?></span><span><?= e($label) ?></span><a href="<?= e($href) ?>" class="btn btn-sm">Μετάβαση</a></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<div class="split">
  <div class="card"><div class="hd"><h2>Πωλήσεις 14 ημερών</h2></div><div class="bd">
    <svg viewBox="0 0 700 180" width="100%" height="180" role="img" aria-label="Πωλήσεις 14 ημερών">
      <?php foreach ([0, 0.5, 1] as $g): $y = 150 - $g * 130; ?><line x1="40" x2="690" y1="<?= $y ?>" y2="<?= $y ?>" stroke="#EAECF0"/><text x="34" y="<?= $y + 4 ?>" font-size="10" text-anchor="end" fill="#667085"><?= number_format($max * $g, 0, ',', '.') ?>€</text><?php endforeach; ?>
      <?php foreach ($series as $i => $s): $h = $s['t'] / $max * 130; $x = 48 + $i * 46; ?>
        <rect x="<?= $x ?>" y="<?= 150 - $h ?>" width="30" height="<?= max(1, $h) ?>" rx="4" fill="var(--accent)" opacity="<?= $s['t'] ? 1 : .25 ?>"><title><?= fmt_date($s['d']) ?>: <?= money($s['t']) ?> (<?= $s['n'] ?>)</title></rect>
        <?php if ($i % 2 === 0): ?><text x="<?= $x + 15 ?>" y="168" font-size="10" text-anchor="middle" fill="#667085"><?= date('d/m', strtotime($s['d'])) ?></text><?php endif; ?>
      <?php endforeach; ?>
    </svg></div></div>
  <div class="card"><div class="hd"><h2>Χαμηλό απόθεμα</h2></div><div class="bd">
    <?php if (!$lowStock): ?><p class="muted">Κανένα ενεργό προϊόν με απόθεμα ≤ 3.</p><?php endif; ?>
    <ul class="timeline"><?php foreach ($lowStock as $p): ?><li><time class="num"><?= (int) $p['stock'] ?> τεμ.</time><a href="/admin/products/<?= $p['id'] ?>"><?= e($p['name']) ?></a></li><?php endforeach; ?></ul></div></div>
</div>

<div class="card"><div class="hd"><h2>Πρόσφατες παραγγελίες</h2><a href="/admin/orders">Όλες →</a></div><div class="bd flush table-wrap">
  <?php if (!$recent): ?><div class="empty"><h3>Δεν υπάρχουν παραγγελίες ακόμη</h3><p>Οι παραγγελίες του καταστήματος θα εμφανίζονται εδώ.</p></div><?php else: ?>
  <table class="table"><thead><tr><th>Αριθμός</th><th>Πελάτης</th><th>Ημερομηνία</th><th>Κατάσταση</th><th class="r">Σύνολο</th></tr></thead><tbody>
  <?php foreach ($recent as $o): ?><tr><td><a class="title" href="/admin/orders/<?= $o['id'] ?>"><?= e($o['number']) ?></a></td><td><?= e($o['first_name'] . ' ' . $o['last_name']) ?></td><td class="num"><?= fmt_date($o['created_at'], true) ?></td><td><?= status_badge($o['status']) ?></td><td class="r num"><?= money((float) $o['total']) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?></div></div>
