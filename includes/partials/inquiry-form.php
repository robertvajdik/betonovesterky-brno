<?php /** @var array $errors */ $ok = flash('inquiry'); ?>
<form method="post" class="form" id="formular" enctype="multipart/form-data"<?= recaptcha_attrs('inquiry') ?>>
  <?php if ($ok): ?><div class="alert alert--ok" role="status" data-ga-event="generate_lead" data-ga-form="inquiry"><?= e($ok) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="inquiry">
  <div class="hp" aria-hidden="true"><label>Web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <?php if (accounts_on()): ?>
    <p class="form__account"><?= icon('user', 16) ?>
      <?php if (customer()): ?><span><?= te('acc.form_logged') ?></span>
      <?php else: $nx = '?next=' . rawurlencode(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '#') . '#formular'); ?>
        <span><?= te('acc.form_hint') ?> <a href="<?= e(route_url('login') . $nx) ?>"><?= te('acc.login_link') ?></a> · <a href="<?= e(route_url('register') . $nx) ?>"><?= te('acc.register_link') ?></a></span>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <div class="form__grid">
    <?php foreach (form_fields() as $f): ?>
      <?= render_field($f) ?>
    <?php endforeach; ?>
  </div>

  <div class="photos" data-photos data-max="<?= INQ_MAX_PHOTOS ?>" data-remove="<?= te('form.photos_remove') ?>">
    <label class="photos__drop">
      <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/*" multiple class="photos__input">
      <span class="photos__icon" aria-hidden="true"><?= icon('plus', 22) ?></span>
      <span><strong><?= te('form.photos_label') ?></strong> <span class="muted"><?= te('form.optional') ?></span><br>
        <small><?= te('form.photos_hint', ['max' => INQ_MAX_PHOTOS]) ?></small></span>
    </label>
    <ul class="photos__list" aria-live="polite"></ul>
  </div>

  <label class="check">
    <input type="checkbox" name="gdpr" value="1" required <?= !empty($_POST['gdpr']) ? 'checked' : '' ?>>
    <span><?= te('form.gdpr') ?> (<a href="<?= e(privacy_url()) ?>" target="_blank" rel="noopener"><?= te('form.gdpr_more') ?></a>).</span>
  </label>
  <button class="btn" type="submit"><?= te('form.submit') ?></button>
  <?= recaptcha_note('inquiry') ?>
</form>
