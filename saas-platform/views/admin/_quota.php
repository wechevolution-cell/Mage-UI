<?php $qcls = $quota['active'] >= $quota['limit'] ? 'full' : ($quota['pct'] >= 80 ? 'warn' : ''); ?>
<div class="card"><div class="bd quota">
  <div class="row"><span class="big">ΕΝΕΡΓΑ ΠΡΟΪΟΝΤΑ: <span class="num" data-quota-text><?= $quota['active'] ?> / <?= $quota['limit'] ?></span></span><span class="muted num">Αποθηκευμένα: <?= number_format($quota['stored'], 0, ',', '.') ?> / <?= number_format($quota['stored_limit'], 0, ',', '.') ?> · Ανενεργά: <?= number_format($quota['inactive'], 0, ',', '.') ?></span></div>
  <div class="bar <?= $qcls ?>"><i data-quota-bar style="width:<?= $quota['pct'] ?>%"></i></div>
  <?php if ($quota['over'] > 0): ?><div class="alert warn"><?= icon('alert') ?><div><b>Υπέρβαση ορίου κατά <?= $quota['over'] ?>.</b> Το πακέτο σας άλλαξε· το κατάστημα εμφανίζει μόνο <?= $quota['limit'] ?> προϊόντα. Απενεργοποιήστε όσα δεν θέλετε να εμφανίζονται για να επιλέξετε ποια μένουν ενεργά.</div></div>
  <?php elseif ($quota['active'] >= $quota['limit']): ?><div class="alert warn"><?= icon('alert') ?><div>Έχετε φτάσει το όριο ενεργών προϊόντων του πακέτου σας. Απενεργοποιήστε ένα προϊόν για να ενεργοποιήσετε άλλο ή ζητήστε αναβάθμιση.</div></div><?php endif; ?>
</div></div>
