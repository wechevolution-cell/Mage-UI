<div class="page-head"><div><h1>Αρχείο ενεργειών</h1><p>Ποιος, τι, πότε, σε ποιο κατάστημα, από ποια IP και με ποιο αποτέλεσμα.</p></div></div>
<div class="card">
  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= e(in_str('q')) ?>" placeholder="Ενέργεια, χρήστης ή IP" aria-label="Αναζήτηση">
    <select name="shop" data-autosubmit aria-label="Κατάστημα"><option value="">Όλα τα καταστήματα</option><?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>"<?= in_int('shop') === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <select name="outcome" data-autosubmit aria-label="Αποτέλεσμα"><option value="">Όλα</option><option value="success"<?= in_str('outcome') === 'success' ? ' selected' : '' ?>>Επιτυχία</option><option value="denied"<?= in_str('outcome') === 'denied' ? ' selected' : '' ?>>Απόρριψη</option><option value="failed"<?= in_str('outcome') === 'failed' ? ' selected' : '' ?>>Αποτυχία</option></select>
    <button class="btn">Φίλτρο</button>
  </form>
  <div class="table-wrap"><table class="table"><thead><tr><th>Πότε</th><th>Ποιος</th><th>Ενέργεια</th><th>Κατάστημα</th><th>Οντότητα</th><th>IP</th><th>Αποτέλεσμα</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="num"><?= fmt_date($r['created_at'], true) ?></td><td><?= e($r['actor_name']) ?><div class="sub"><?= e($r['actor_type']) ?><?= $r['impersonator_id'] ? ' · impersonation (SA #' . (int) $r['impersonator_id'] . ')' : '' ?></div></td>
  <td><code><?= e($r['action']) ?></code><?php if ($r['details']): ?><div class="sub mono" style="max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($r['details']) ?>"><?= e($r['details']) ?></div><?php endif; ?></td>
  <td><?= $r['shop_id'] ? '<a href="/superadmin/shops/' . (int) $r['shop_id'] . '">' . e($r['shop_name'] ?? '#' . $r['shop_id']) . '</a>' : '—' ?></td><td class="sub"><?= e($r['entity_type']) ?> <?= e($r['entity_id']) ?></td><td class="mono"><?= e($r['ip']) ?></td><td><?= status_badge($r['outcome']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php $pg_base = $pg; include ROOT . '/views/admin/_pager.php'; ?>
</div>
