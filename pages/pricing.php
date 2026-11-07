<?php
$title    = t('pricing.title');
$metaDesc = t('pricing.meta_desc');
$errors   = handle_inquiry('cenik', route_url('pricing'));
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([[$title, null]]) ?>
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= te('pricing.lead') ?></p>
  </div>
</section>

<section class="section section--tight">
  <div class="container split split--top">
    <div class="prose">
      <h2><?= te('pricing.factors_title') ?></h2>
      <?= t('pricing.factors') /* HTML z administrace */ ?>
    </div>
    <div class="panel">
      <h2><?= te('pricing.form_title') ?></h2>
      <?php require ROOT_DIR . '/includes/partials/inquiry-form.php'; ?>
    </div>
  </div>
</section>
