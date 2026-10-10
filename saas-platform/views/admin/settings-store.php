<div class="page-head"><div><h1>Ρυθμίσεις καταστήματος</h1></div></div>
<?php include __DIR__ . '/_settings_tabs.php'; ?>
<form method="post" action="/admin/settings" class="grid g2"><?= csrf_field() ?>
  <div class="card"><div class="hd"><h2>Στοιχεία επιχείρησης</h2></div><div class="bd form"><div class="fg">
    <div class="field"><label>Όνομα καταστήματος</label><input name="name" value="<?= e($s['name']) ?>"></div><div class="field"><label>Επωνυμία εταιρείας</label><input name="company_name" value="<?= e($s['company_name']) ?>"></div>
    <div class="field"><label>ΑΦΜ</label><input name="vat_number" value="<?= e($s['vat_number']) ?>"></div><div class="field"><label>ΔΟΥ</label><input name="tax_office" value="<?= e($s['tax_office']) ?>"></div>
    <div class="field full"><label>Σύντομη περιγραφή (footer / schema)</label><textarea name="about_short" rows="2"><?= e($s['about_short']) ?></textarea></div>
  </div></div></div>
  <div class="card"><div class="hd"><h2>Επικοινωνία</h2></div><div class="bd form"><div class="fg">
    <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($s['email']) ?>"></div><div class="field"><label>Τηλέφωνο</label><input name="phone" value="<?= e($s['phone']) ?>"></div>
    <div class="field full"><label>Διεύθυνση</label><input name="address" value="<?= e($s['address']) ?>"></div><div class="field"><label>Πόλη</label><input name="city" value="<?= e($s['city']) ?>"></div><div class="field"><label>Τ.Κ.</label><input name="postcode" value="<?= e($s['postcode']) ?>"></div>
    <div class="field full"><label>Ωράριο</label><input name="hours" value="<?= e($s['hours']) ?>" placeholder="Δευ–Παρ 09:00–17:00"></div>
    <div class="field full"><label>Σύνδεσμος Google Maps</label><input name="map_url" value="<?= e($s['map_url']) ?>" placeholder="https://www.google.com/maps/embed?…"></div>
  </div></div></div>
  <div class="card"><div class="hd"><h2>Social media (sameAs)</h2></div><div class="bd form"><div class="fg"><?php foreach ($s['socials'] as $k => $url): ?><div class="field"><label><?= e(ucfirst($k)) ?></label><input name="social_<?= $k ?>" value="<?= e($url) ?>" placeholder="https://"></div><?php endforeach; ?></div></div></div>
  <div class="card"><div class="hd"><h2>Cookies</h2></div><div class="bd form"><div class="field"><label>Κείμενο banner cookies</label><textarea name="cookie_text" rows="3"><?= e($cookies['banner_text']) ?></textarea></div></div><div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div></div>
</form>
