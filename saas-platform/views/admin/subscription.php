<div class="page-head"><div><h1>Συνδρομή & πακέτο</h1><p>Για αλλαγή πακέτου ή ανανέωση επικοινωνήστε με τον πάροχο.</p></div></div>
<?php include __DIR__ . '/_quota.php'; ?>
<div class="grid g3">
  <div class="card stat"><span class="lbl">Πακέτο</span><span class="val"><?= e($plan['name'] ?? '—') ?></span><span class="sub"><?= money((float) ($plan['price'] ?? 0)) ?> / μήνα</span></div>
  <div class="card stat <?= $days !== null && $days <= 10 ? 'warn' : '' ?>"><span class="lbl">Λήξη</span><span class="val" style="font-size:20px"><?= $sub ? fmt_date($sub['end_date']) : '—' ?></span><span class="sub"><?= $days !== null ? max(0, $days) . ' ημέρες' : '' ?></span></div>
  <div class="card stat"><span class="lbl">Χρήστες</span><span class="val"><?= (int) $staffLimit ?></span><span class="sub">μέγιστο</span></div>
</div>
<div class="grid g2">
  <div class="card"><div class="hd"><h2>Λειτουργίες πακέτου</h2></div><div class="bd"><ul class="checks"><?php foreach (Plans::FEATURES as $k => $l): ?><li class="<?= $features[$k] ? 'pass' : '' ?>"><?= e($l) ?></li><?php endforeach; ?></ul></div></div>
  <div class="card"><div class="hd"><h2>Ιστορικό</h2></div><div class="bd"><ul class="timeline"><?php foreach ($history as $h): ?><li><time><?= fmt_date($h['created_at']) ?></time><div><b><?= e(['created' => 'Δημιουργία', 'renewed' => 'Ανανέωση', 'timer_reset' => 'Ανανέωση', 'upgraded' => 'Αναβάθμιση', 'downgraded' => 'Αλλαγή πακέτου', 'expired' => 'Λήξη', 'locked' => 'Κλείδωμα', 'suspended' => 'Αναστολή', 'reactivated' => 'Επανενεργοποίηση'][$h['action']] ?? $h['action']) ?></b> <?= e($h['plan_name']) ?><?= $h['end_date'] ? '<div class="sub">έως ' . fmt_date($h['end_date']) . '</div>' : '' ?></div></li><?php endforeach; ?></ul></div></div>
</div>
