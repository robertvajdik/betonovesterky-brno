<?php
$title    = t('contact.title');
$metaDesc = t('contact.meta_desc');
$errors   = handle_inquiry('kontakt', route_url('contact'));
$address  = site('address');
$mapsQ    = rawurlencode($address);
$hours    = site('hours');
?>
<section class="page-head">
  <div class="container">
    <?= breadcrumbs([[$title, null]]) ?>
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= te('contact.lead') ?></p>
  </div>
</section>

<section class="section section--tight">
  <div class="container split split--top contact">
    <div class="contact-card">
      <h2><?= e(site('owner')) ?></h2>

      <div class="contact-actions">
        <a href="<?= e(tel_href(site('phone'))) ?>" class="contact-action contact-action--primary">
          <?= icon('mobile', 22) ?>
          <span><small><?= te('contact.call') ?></small><?= e(site('phone')) ?></span>
        </a>
        <a href="#formular" class="contact-action">
          <?= icon('mail', 22) ?>
          <span><small><?= te('contact.write') ?></small><?= te('contact.form_title') ?></span>
        </a>
      </div>

      <dl>
        <dt><?= icon('mail', 16) ?> <?= te('contact.email') ?></dt><dd><?= email_link(site('email')) ?></dd>
        <dt><?= icon('pin', 16) ?> <?= te('contact.address') ?></dt>
        <dd><address><?= e($address) ?></address>
          <a href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= $mapsQ ?>" target="_blank" rel="noopener" class="link-arrow contact__nav"><?= te('contact.navigate') ?></a></dd>
        <?php if ($hours !== ''): ?><dt><?= icon('clock', 16) ?> <?= te('contact.hours') ?></dt><dd><?= nl2br(e($hours)) ?></dd><?php endif; ?>
        <dt><?= icon('id', 16) ?> <?= te('footer.ico') ?></dt><dd><?= e(site('ico')) ?></dd>
      </dl>

      <!-- Mapa se načte až po kliknutí: žádné cookies Googlu bez souhlasu, rychlejší stránka -->
      <div class="map map--placeholder" data-map-src="https://www.google.com/maps?q=<?= $mapsQ ?>&amp;output=embed&amp;hl=<?= lang() ?>" data-map-title="<?= te('contact.map_title') ?>">
        <button type="button" class="btn btn--light"><?= te('contact.map_load') ?></button>
        <p><?= te('contact.map_note') ?> <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= $mapsQ ?>" target="_blank" rel="noopener"><?= te('contact.map_open') ?></a></p>
      </div>
    </div>

    <div class="panel" id="poptavka">
      <h2><?= te('contact.form_title') ?></h2>
      <p class="muted"><?= te('contact.form_lead') ?></p>
      <?php require ROOT_DIR . '/includes/partials/inquiry-form.php'; ?>
    </div>
  </div>
</section>

<section class="section section--tint">
  <div class="container">
    <div class="section__head"><h2><?= te('contact.steps_title') ?></h2></div>
    <ol class="steps">
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <li class="step">
          <span class="step__num" aria-hidden="true">0<?= $i ?></span>
          <h3><?= te("contact.step{$i}_title") ?></h3>
          <p><?= te("contact.step{$i}_text") ?></p>
        </li>
      <?php endfor; ?>
    </ol>
  </div>
</section>
