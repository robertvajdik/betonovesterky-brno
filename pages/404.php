<?php
/** @var string $slug  požadovaná adresa (bez /en/) */
$title    = t('404.title');
$metaDesc = t('404.lead');

// „Nemysleli jste…?“ – nejpodobnější stránka podle slugu
$suggest = null;
$needle  = strtolower((string) preg_replace('~[^a-z0-9-]+~i', '-', basename(trim((string) ($slug ?? ''), '/'))));
if ($needle !== '' && strlen($needle) <= 120) {
    $best = PHP_INT_MAX;
    $rows = array_merge(
        q("SELECT id, slug, slug_en, title, title_en, menu_title, menu_title_en FROM categories WHERE is_active = 1")->fetchAll(),
        q("SELECT id, slug, slug_en, title, title_en, NULL AS menu_title, NULL AS menu_title_en FROM projects WHERE is_published = 1")->fetchAll()
    );
    foreach ($rows as $r) {
        foreach (array_filter([$r['slug'], $r['slug_en']]) as $s) {
            $d = levenshtein($needle, $s);
            if (str_contains($s, $needle) || str_contains($needle, $s)) $d = min($d, 2);
            if ($d < $best) { $best = $d; $suggest = $r; }
        }
    }
    if ($best > max(3, (int) (strlen($needle) * .4))) $suggest = null;
}
?>
<section class="page-head page-head--center">
  <div class="container">
    <p class="eyebrow"><?= te('404.eyebrow') ?></p>
    <h1><?= e($title) ?></h1>
    <p class="lead"><?= te('404.lead') ?></p>
    <?php if ($suggest): ?>
      <p class="notfound__suggest"><?= te('404.suggest') ?>
        <a href="<?= e(item_url($suggest)) ?>"><?= e(tr($suggest, 'menu_title') ?: tr($suggest, 'title')) ?></a>?</p>
    <?php endif; ?>
    <div class="hero__actions notfound__actions">
      <a href="<?= e(route_url('home')) ?>" class="btn"><?= te('404.button') ?></a>
      <a href="<?= e(route_url('references')) ?>" class="btn btn--ghost"><?= te('nav.references') ?></a>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <div class="section__head"><h2><?= te('404.links_title') ?></h2></div>
    <div class="cards">
      <?php foreach (services() as $s): ?>
        <a class="card" href="<?= e(item_url($s)) ?>">
          <?= img($s['cover_image'], tr($s, 'title'), 'card__img') ?>
          <div class="card__body"><h3><?= e(tr($s, 'menu_title') ?: tr($s, 'title')) ?></h3><p><?= e(tr($s, 'intro')) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="notfound__contact"><?= te('404.contact_text') ?>
      <a href="<?= e(tel_href(site('phone'))) ?>"><?= e(site('phone')) ?></a> · <?= email_link(site('email')) ?>
    </p>
  </div>
</section>
