<div class="page-head">
  <div><h1>Καταστήματα</h1><p><?= count($rows) ?> αποτελέσματα</p></div>
  <div class="actions"><a class="btn btn-primary" href="/superadmin/shops/new"><?= icon('plus') ?>Νέο κατάστημα</a></div>
</div>
<div class="card">
  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= e(in_str('q')) ?>" placeholder="Επωνυμία, email ή domain…" aria-label="Αναζήτηση">
    <select name="status" aria-label="Κατάσταση" data-autosubmit>
      <option value="">Όλες οι καταστάσεις</option>
      <option value="expiring_soon"<?= in_str('status') === 'expiring_soon' ? ' selected' : '' ?>>Λήγουν σε ≤ 10 ημέρες</option>
      <?php foreach (Shops::STATUSES as $st): if ($st === 'deleted') continue; ?><option value="<?= $st ?>"<?= in_str('status') === $st ? ' selected' : '' ?>><?= e(status_label($st)) ?></option><?php endforeach; ?>
    </select>
    <select name="plan" aria-label="Πακέτο" data-autosubmit><option value="">Όλα τα πακέτα</option><?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>"<?= in_int('plan') === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
    <select name="template" aria-label="Template" data-autosubmit><option value="">Όλα τα templates</option><?php foreach ($templates as $t): ?><option value="<?= e($t['key']) ?>"<?= in_str('template') === $t['key'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
    <button class="btn">Φίλτρο</button>
  </form>
  <div class="table-wrap"><?php include __DIR__ . '/_shops_table.php'; ?></div>
</div>
