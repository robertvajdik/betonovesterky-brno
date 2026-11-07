<?php /** @var array $errors */ $ok = flash('inquiry'); ?>
<form method="post" class="form" id="formular"<?= recaptcha_attrs('inquiry') ?>>
  <?php if ($ok): ?><div class="alert alert--ok" role="status"><?= e($ok) ?></div><?php endif; ?>
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

  <label class="check">
    <input type="checkbox" name="gdpr" value="1" required <?= !empty($_POST['gdpr']) ? 'checked' : '' ?>>
    <span><?= te('form.gdpr') ?> (<a href="<?= e(privacy_url()) ?>" target="_blank" rel="noopener"><?= te('form.gdpr_more') ?></a>).</span>
  </label>
  <button class="btn" type="submit"><?= te('form.submit') ?></button>
  <?= recaptcha_note() ?>
</form>
