<div class="wrap page-pad page page--<?= e($page['kind']) ?>">
  <?= partial('breadcrumbs', ['crumbs' => [['Αρχική', '/'], [$page['title'], null]]]) ?>
  <h1 class="page-title"><?= e($page['title']) ?></h1>
  <?php if ($page['kind'] === 'contact'): ?>
    <div class="contact-grid">
      <div class="prose"><?= Sanitizer::html($page['content']) ?>
        <ul class="contact-list">
          <?php if (!empty($store['address'])): ?><li><?= icon('pin') ?><span><b>Διεύθυνση</b><?= e(trim($store['address'] . ', ' . ($store['postcode'] ?? '') . ' ' . ($store['city'] ?? ''), ', ')) ?></span></li><?php endif; ?>
          <?php if (!empty($store['phone'])): ?><li><?= icon('phone') ?><span><b>Τηλέφωνο</b><a href="tel:<?= e(preg_replace('/\s+/', '', $store['phone'])) ?>"><?= e($store['phone']) ?></a></span></li><?php endif; ?>
          <?php if (!empty($store['email'])): ?><li><?= icon('mail') ?><span><b>Email</b><a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a></span></li><?php endif; ?>
          <?php if (!empty($store['hours'])): ?><li><?= icon('clock') ?><span><b>Ωράριο</b><?= e($store['hours']) ?></span></li><?php endif; ?>
        </ul>
      </div>
      <form class="contact-form card-box" id="contact-form" method="post" action="/contact/send/"><?= csrf_field() ?><input type="hidden" name="_ts" value="<?= (int) ($_SESSION['form_ts'] ?? time()) ?>"><input type="hidden" name="_back" value="/<?= e($page['slug']) ?>/">
        <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <h2>Στείλτε μας μήνυμα</h2>
        <div class="fgrid">
          <div class="field"><label for="cf-name">Ονοματεπώνυμο *</label><input id="cf-name" name="name" required autocomplete="name" value="<?= e(old('name')) ?>"></div>
          <div class="field"><label for="cf-email">Email *</label><input id="cf-email" type="email" name="email" required autocomplete="email" value="<?= e(old('email')) ?>"></div>
          <div class="field"><label for="cf-phone">Τηλέφωνο</label><input id="cf-phone" type="tel" name="phone" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
          <div class="field"><label for="cf-subject">Θέμα</label><input id="cf-subject" name="subject" value="<?= e(old('subject')) ?>"></div>
          <div class="field full"><label for="cf-msg">Μήνυμα *</label><textarea id="cf-msg" name="message" rows="5" required><?= e(old('message')) ?></textarea></div>
        </div>
        <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Συμφωνώ με την <a href="/privacy/">Πολιτική απορρήτου</a> για την επεξεργασία των στοιχείων μου. *</span></label>
        <button class="btn btn--primary">Αποστολή μηνύματος</button>
      </form>
    </div>
    <?php if (!empty($store['map_url']) && str_starts_with($store['map_url'], 'https://www.google.com/maps')): ?><div class="map"><iframe src="<?= e($store['map_url']) ?>" title="Χάρτης" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div><?php endif; ?>
  <?php else: ?>
    <div class="prose page-body"><?= Sanitizer::html($page['content']) ?></div>
  <?php endif; ?>
  <?= partial('faq', ['faq' => $faq]) ?>
</div>
