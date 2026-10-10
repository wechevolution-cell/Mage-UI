<div class="page-head"><div><h1>Αποστολές</h1></div></div>
<?php include __DIR__ . '/_settings_tabs.php'; ?>
<form method="post" action="/admin/settings/shipping" class="grid g2"><?= csrf_field() ?>
  <div class="card"><div class="hd"><h2>Ζώνες αποστολής</h2></div><div class="bd form">
    <?php foreach ($s['zones'] as $k => $z): ?><fieldset class="box"><legend><label class="check"><input type="checkbox" name="zone_<?= $k ?>_enabled" value="1"<?= $z['enabled'] ? ' checked' : '' ?>> <?= e($z['label']) ?></label></legend><div class="fg"><div class="field"><label>Ετικέτα</label><input name="zone_<?= $k ?>_label" value="<?= e($z['label']) ?>"></div><div class="field"><label>Κόστος (€)</label><input name="zone_<?= $k ?>_cost" inputmode="decimal" value="<?= e(number_format((float) $z['cost'], 2, ',', '')) ?>"></div></div></fieldset><?php endforeach; ?>
    <div class="field"><label>Δωρεάν αποστολή για παραγγελίες άνω των (€)</label><input name="free_threshold" inputmode="decimal" value="<?= e(number_format((float) $s['free_threshold'], 2, ',', '')) ?>"><span class="hint">0 = χωρίς δωρεάν αποστολή.</span></div>
  </div></div>
  <div class="card"><div class="hd"><h2>Παραλαβή & χρόνοι</h2></div><div class="bd form">
    <label class="check"><input type="checkbox" name="pickup_enabled" value="1"<?= $s['pickup']['enabled'] ? ' checked' : '' ?>> Παραλαβή από το κατάστημα (δωρεάν)</label>
    <div class="field"><label>Ετικέτα</label><input name="pickup_label" value="<?= e($s['pickup']['label']) ?>"></div><div class="field"><label>Διεύθυνση παραλαβής</label><input name="pickup_address" value="<?= e($s['pickup']['address']) ?>"></div>
    <div class="field"><label>Χρόνος παράδοσης (εμφανίζεται στο προϊόν)</label><input name="delivery_text" value="<?= e($s['delivery_text']) ?>"></div>
  </div><div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div></div>
</form>
