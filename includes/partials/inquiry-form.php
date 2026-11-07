<?php /** @var array $errors */ $ok = flash('inquiry'); ?>
<form method="post" class="form" id="formular">
  <?php if ($ok): ?><div class="alert alert--ok" role="status"><?= e($ok) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert--err" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="inquiry">
  <div class="hp" aria-hidden="true"><label>Web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

  <div class="form__grid">
    <?php foreach (form_fields() as $f): ?>
      <?= render_field($f) ?>
    <?php endforeach; ?>
  </div>

  <label class="check">
    <input type="checkbox" name="gdpr" value="1" required <?= !empty($_POST['gdpr']) ? 'checked' : '' ?>>
    <span><?= te('form.gdpr') ?><?php if (site('gdpr_url')): ?> (<a href="<?= e(site('gdpr_url')) ?>" target="_blank" rel="noopener"><?= te('form.gdpr_more') ?></a>)<?php endif; ?>.</span>
  </label>
  <button class="btn" type="submit"><?= te('form.submit') ?></button>
</form>
