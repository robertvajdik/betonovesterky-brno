<section class="cta">
  <div class="container cta__inner">
    <div>
      <h2><?= te('cta.title') ?></h2>
      <p><?= te('cta.text') ?></p>
    </div>
    <div class="cta__actions">
      <a href="<?= e(route_url('contact')) ?>" class="btn btn--light"><?= te('cta.button') ?></a>
      <a href="<?= e(tel_href(site('phone'))) ?>" class="cta__phone"><?= e(site('phone')) ?></a>
    </div>
  </div>
</section>
