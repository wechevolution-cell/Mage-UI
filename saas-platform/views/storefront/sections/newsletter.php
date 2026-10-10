<section class="sec sec-news" aria-labelledby="h-news"><div class="wrap news">
  <div><h2 id="h-news"><?= e($sec['title']) ?></h2><?php if ($sec['text']): ?><p><?= e($sec['text']) ?></p><?php endif; ?></div>
  <form class="news-form" method="post" action="/newsletter/" data-ajax-form><?= csrf_field() ?><input type="hidden" name="source" value="newsletter"><input type="hidden" name="_ts" value="<?= time() ?>">
    <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="news-row"><label class="sr" for="nl-email">Email</label><input id="nl-email" type="email" name="email" required placeholder="Το email σας" autocomplete="email"><button class="btn btn--primary">Εγγραφή</button></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required> <span>Συμφωνώ με την <a href="/privacy/">Πολιτική απορρήτου</a> και την αποστολή ενημερώσεων.</span></label>
    <p class="form-msg" role="status"></p>
  </form>
</div></section>
