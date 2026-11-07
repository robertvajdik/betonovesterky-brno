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
          <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
          <span><small><?= te('contact.call') ?></small><?= e(site('phone')) ?></span>
        </a>
        <a href="#formular" class="contact-action">
          <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5z"/></svg>
          <span><small><?= te('contact.write') ?></small><?= te('contact.form_title') ?></span>
        </a>
      </div>

      <dl>
        <dt><?= te('contact.email') ?></dt><dd><?= email_link(site('email')) ?></dd>
        <dt><?= te('contact.address') ?></dt>
        <dd><address><?= e($address) ?></address>
          <a href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= $mapsQ ?>" target="_blank" rel="noopener" class="link-arrow contact__nav"><?= te('contact.navigate') ?></a></dd>
        <?php if ($hours !== ''): ?><dt><?= te('contact.hours') ?></dt><dd><?= nl2br(e($hours)) ?></dd><?php endif; ?>
        <dt><?= te('footer.ico') ?></dt><dd><?= e(site('ico')) ?></dd>
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
