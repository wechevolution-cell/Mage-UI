<?php /** @var array|null $e entity */ $e = $e ?? []; ?>
<div class="card"><div class="hd"><h2>SEO</h2></div><div class="bd form">
  <div class="field"><label>Λέξη-κλειδί εστίασης</label><input name="focus_keyword" value="<?= e($e['focus_keyword'] ?? '') ?>"></div>
  <div class="field"><label>Τίτλος SEO</label><input name="seo_title" maxlength="200" value="<?= e($e['seo_title'] ?? '') ?>"><span class="hint">Ιδανικά 30–60 χαρακτήρες.</span></div>
  <div class="field"><label>Meta description</label><textarea name="seo_description" rows="3" maxlength="320"><?= e($e['seo_description'] ?? '') ?></textarea><span class="hint">Ιδανικά 120–160 χαρακτήρες.</span></div>
  <?php if (!isset($noCanonical)): ?><div class="field"><label>Canonical URL (προαιρετικό)</label><input name="canonical_url" value="<?= e($e['canonical_url'] ?? '') ?>"></div>
  <div class="field"><label>Robots</label><select name="robots"><option value="">index, follow</option><option value="noindex"<?= ($e['robots'] ?? '') === 'noindex' ? ' selected' : '' ?>>noindex</option><option value="noindex,nofollow"<?= ($e['robots'] ?? '') === 'noindex,nofollow' ? ' selected' : '' ?>>noindex, nofollow</option></select></div><?php endif; ?>
</div></div>
<div class="card"><div class="hd"><h2>Ανάλυση SEO / AEO</h2></div><div class="bd" data-seo-out><p class="muted">Υπολογισμός…</p></div></div>
