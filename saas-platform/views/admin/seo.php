<div class="page-head"><div><h1>SEO / AEO / GEO</h1><p>Ρυθμίσεις αναζήτησης, structured data, sitemap, robots.txt, llms.txt και εργαλεία.</p></div>
  <div class="actions"><?php if ($tools): ?><a class="btn" href="/admin/seo/redirects">Ανακατευθύνσεις (<?= $diag['redirects'] ?>)</a><a class="btn" href="/admin/seo/404">404 (<?= $diag['not_found'] ?>)</a><?php endif; ?></div></div>
<div class="grid g4">
  <div class="card stat <?= $diag['missing_meta_products'] ? 'warn' : 'ok' ?>"><span class="lbl">Ενεργά προϊόντα χωρίς meta description</span><span class="val num"><?= $diag['missing_meta_products'] ?></span></div>
  <div class="card stat <?= $diag['thin_products'] ? 'warn' : 'ok' ?>"><span class="lbl">Προϊόντα με σύντομη περιγραφή (&lt;300)</span><span class="val num"><?= $diag['thin_products'] ?></span></div>
  <div class="card stat <?= $diag['missing_alt'] ? 'warn' : 'ok' ?>"><span class="lbl">Εικόνες χωρίς alt</span><span class="val num"><?= $diag['missing_alt'] ?></span><a class="sub" href="/admin/media">Πολυμέσα</a></div>
  <div class="card stat <?= $diag['missing_meta_categories'] ? 'warn' : 'ok' ?>"><span class="lbl">Κατηγορίες χωρίς meta description</span><span class="val num"><?= $diag['missing_meta_categories'] ?></span></div>
</div>
<div class="card"><div class="hd"><h2>Διαγνωστικά</h2></div><div class="bd"><ul class="checks">
  <li class="<?= !$diag['no_gsc'] ? 'pass' : '' ?>">Google Search Console επαλήθευση</li><li class="<?= !$diag['no_ga'] ? 'pass' : '' ?>">Google Analytics 4 (φορτώνεται μόνο μετά από συγκατάθεση cookies)</li>
  <li class="<?= !$diag['no_local'] ? 'pass' : '' ?>">Τοπικό SEO: διεύθυνση καταστήματος</li><li class="<?= !$diag['no_sameas'] ? 'pass' : '' ?>">sameAs: προφίλ social media</li>
  <li class="pass">sitemap.xml: <a href="<?= e($base) ?>/sitemap.xml" target="_blank" rel="noopener">/sitemap.xml</a></li><li class="pass">robots.txt: <a href="<?= e($base) ?>/robots.txt" target="_blank" rel="noopener">/robots.txt</a></li>
  <li class="pass">llms.txt: <a href="<?= e($base) ?>/llms.txt" target="_blank" rel="noopener">/llms.txt</a></li><li class="pass">RSS blog: <a href="<?= e($base) ?>/feed/blog.xml" target="_blank" rel="noopener">/feed/blog.xml</a></li>
</ul></div></div>
<form method="post" action="/admin/seo" class="grid g2"><?= csrf_field() ?>
  <div class="card"><div class="hd"><h2>Γενικά</h2></div><div class="bd form">
    <div class="field"><label>Τίτλος ιστότοπου (αρχική)</label><input name="site_title" value="<?= e($seo['site_title']) ?>"></div>
    <div class="field"><label>Κατάληξη τίτλων</label><input name="title_suffix" value="<?= e($seo['title_suffix']) ?>" placeholder="| Όνομα καταστήματος"></div>
    <div class="field"><label>Προεπιλεγμένη meta description</label><textarea name="default_description" rows="3"><?= e($seo['default_description']) ?></textarea></div>
    <div class="field"><label>Τύπος οργανισμού (Schema.org)</label><select name="org_type"><?php foreach (['OnlineStore' => 'Online Store', 'Store' => 'Φυσικό κατάστημα (Store)', 'LocalBusiness' => 'Τοπική επιχείρηση', 'Organization' => 'Οργανισμός'] as $k => $l): ?><option value="<?= $k ?>"<?= $seo['org_type'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <?php $name = 'default_og_image_id'; $media = $og; $label = 'Προεπιλεγμένη εικόνα κοινοποίησης (Open Graph)'; $hint = '1200×630 προτείνεται.'; include __DIR__ . '/_img_pick.php'; ?>
  </div></div>
  <div class="card"><div class="hd"><h2>Analytics & AI</h2></div><div class="bd form">
    <div class="field"><label>Google Analytics 4 Measurement ID</label><input name="ga4_id" value="<?= e($seo['ga4_id']) ?>" placeholder="G-XXXXXXXXXX"><span class="hint">Φορτώνεται μόνο αφού ο επισκέπτης αποδεχτεί τα cookies στατιστικών.</span></div>
    <div class="field"><label>Google Search Console (meta επαλήθευσης)</label><input name="gsc_verification" value="<?= e($seo['gsc_verification']) ?>" placeholder='<meta name="google-site-verification" content="…">'></div>
    <label class="check"><input type="checkbox" name="allow_ai_bots" value="1"<?= $seo['allow_ai_bots'] ? ' checked' : '' ?>> Να επιτρέπονται AI crawlers (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended)</label>
    <div class="field"><label>Εισαγωγή llms.txt</label><textarea name="llms_intro" rows="3" placeholder="Σύντομη, πραγματική περιγραφή του καταστήματος για LLMs."><?= e($seo['llms_intro']) ?></textarea></div>
    <div class="field"><label>Επιπλέον κανόνες robots.txt</label><textarea name="robots_extra" rows="3" class="code" placeholder="Disallow: /search/"><?= e($seo['robots_extra']) ?></textarea></div>
  </div><div class="ft"><button class="btn btn-primary">Αποθήκευση</button></div></div>
</form>
