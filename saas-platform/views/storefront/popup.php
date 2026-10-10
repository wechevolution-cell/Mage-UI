<?php $img = $popup['image_id'] ? db_one($db, 'SELECT * FROM media WHERE id = ?', [$popup['image_id']]) : null; ?>
<div class="popup" data-popup data-popup-id="<?= (int) $popup['id'] ?>" data-trigger="<?= e($popup['trigger_type']) ?>" data-delay="<?= (int) $popup['delay_seconds'] ?>" data-freq="<?= (int) $popup['frequency_days'] ?>" hidden>
  <div class="popup-box<?= $img ? ' has-img' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="popup-title">
    <button class="icon-btn popup-x" type="button" data-popup-close aria-label="Κλείσιμο"><?= icon('close') ?></button>
    <?php if ($img): ?><div class="popup-img"><?= media_img($img, '360px') ?></div><?php endif; ?>
    <div class="popup-body">
      <h2 id="popup-title"><?= e($popup['title']) ?></h2>
      <?php if ($popup['body']): ?><p><?= nl2br(e($popup['body'])) ?></p><?php endif; ?>
      <?php if ($popup['collect_email']): ?>
        <form method="post" action="/newsletter/" data-ajax-form data-popup-form><?= csrf_field() ?><input type="hidden" name="popup_id" value="<?= (int) $popup['id'] ?>"><input type="hidden" name="_ts" value="<?= time() - 10 ?>">
          <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <label class="sr" for="pp-email">Email</label><input id="pp-email" type="email" name="email" required placeholder="Το email σας" autocomplete="email">
          <?php if ($popup['require_consent']): ?><label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e($popup['consent_text'] ?: 'Συμφωνώ με την Πολιτική απορρήτου.') ?></span></label><?php else: ?><input type="hidden" name="consent" value="1"><?php endif; ?>
          <button class="btn btn--primary btn--block"><?= e($popup['button_text'] ?: 'Εγγραφή') ?></button><p class="form-msg" role="status"></p>
        </form>
      <?php elseif ($popup['cta_url']): ?><a class="btn btn--primary" href="<?= e($popup['cta_url']) ?>" data-popup-close><?= e($popup['button_text'] ?: 'Δείτε περισσότερα') ?></a><?php endif; ?>
    </div>
  </div>
</div>
