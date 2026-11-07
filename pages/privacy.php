<?php
$title    = t('privacy.title');
$metaDesc = t('privacy.meta_desc');

// Proměnné v textu zásad: {owner} {ico} {address} {email} {phone} {domain}
$vars = [
    'owner'   => e(site('owner')),
    'ico'     => e(site('ico')),
    'address' => e(site('address')),
    'email'   => email_link(site('email')),
    'phone'   => '<a href="' . e(tel_href(site('phone'))) . '">' . e(site('phone')) . '</a>',
    'domain'  => e(preg_replace('~^https?://~', '', rtrim(site('domain'), '/')) ?: ($_SERVER['HTTP_HOST'] ?? '')),
];
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([[$title, null]]) ?>
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= te('privacy.lead') ?></p>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <div class="prose prose--doc">
      <?= t('privacy.body', $vars) /* HTML z administrace */ ?>
    </div>
  </div>
</section>
