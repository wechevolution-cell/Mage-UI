<?php /** @var string $name @var ?array $media @var string $label */ ?>
<div class="field"><span class="lbl"><?= e($label) ?></span>
  <div class="img-pick"><span class="prev" style="<?= $media ? 'background-image:url(\'' . e(media_url($media, 400)) . '\')' : '' ?>"></span><input type="hidden" name="<?= e($name) ?>" value="<?= (int) ($media['id'] ?? 0) ?: '' ?>">
  <button type="button" class="btn btn-sm" data-media-pick><?= icon('image') ?>Επιλογή</button><button type="button" class="btn btn-sm btn-ghost" data-media-clear>Αφαίρεση</button></div>
  <?php if (!empty($hint)): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
</div>
