<?php
/** @var string $content @var string $title @var string $metaDesc @var string $current @var string $currentSlug @var array $alt */
$services     = services();
$concrete     = category_by_slug('betonove-sterky');
$concreteSubs = $concrete ? subcategories((int) $concrete['id']) : [];
$fullTitle    = $title ? $title . ' | ' . site('name') : site('name') . ' – ' . t('site.tagline');
$logo         = image_url(props()['img.logo']['value_cs'] ?? '');
$favicon      = image_url(props()['img.favicon']['value_cs'] ?? '');
$other        = lang() === 'en' ? 'cs' : 'en';
$navLink = function (string $route, string $key) use ($current) {
    return '<li><a href="' . e(route_url($route)) . '"' . ($current === $route ? ' class="is-active" aria-current="page"' : '') . '>' . te($key) . '</a></li>';
};
?><!doctype html>
<html lang="<?= lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<?php $is404 = $current === '404'; ?>
<?php if ($is404 || !prop_bool('seo.indexing')): ?><meta name="robots" content="noindex<?= $is404 ? ', follow' : '' ?>"><?php endif; ?>
<?php if (!$is404): ?>
<link rel="canonical" href="<?= e(abs_url($alt[lang()])) ?>">
<link rel="alternate" hreflang="cs" href="<?= e(abs_url($alt['cs'])) ?>">
<link rel="alternate" hreflang="en" href="<?= e(abs_url($alt['en'])) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(abs_url($alt['cs'])) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<?php $ogAbs = abs_image($ogImage ?? null); if ($ogAbs): ?><meta property="og:image" content="<?= e($ogAbs) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="<?= lang() === 'en' ? 'en_GB' : 'cs_CZ' ?>">
<?php if (!$is404): ?><meta property="og:url" content="<?= e(abs_url($alt[lang()])) ?>">
<?= jsonld($current, abs_url($alt[lang()]), $fullTitle, $metaDesc, $ogAbs, $schema ?? []) ?>
<?php endif; ?>
<?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
<meta name="theme-color" content="#1f1e1c">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>

<div class="topbar">
  <div class="container topbar__inner">
    <?= email_link(site('email'), 'topbar__mail') ?>
    <a href="<?= e(tel_href(site('phone'))) ?>"><?= e(site('phone')) ?></a>
    <?php if (site('facebook')): ?><a href="<?= e(site('facebook')) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
    <?php if (site('instagram')): ?><a href="<?= e(site('instagram')) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
    <a href="<?= e($alt[$other]) ?>" class="lang-switch" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= $other === 'en' ? 'EN' : 'CZ' ?></a>
  </div>
</div>

<header class="header">
  <div class="container header__inner">
    <a href="<?= e(route_url('home')) ?>" class="logo">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="<?= e(site('name')) ?>" class="logo__img">
      <?php else: ?>
        <span class="logo__mark" aria-hidden="true"></span><span><?= e(site('name')) ?></span>
      <?php endif; ?>
    </a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="nav" aria-label="<?= te('nav.menu') ?>">
      <span></span><span></span><span></span>
    </button>
    <nav id="nav" class="nav" aria-label="<?= te('nav.menu') ?>">
      <ul>
        <?= $navLink('home', 'nav.home') ?>
        <?php foreach ($services as $s): ?>
          <?php $subs = $s['slug'] === 'betonove-sterky' ? $concreteSubs : []; $active = $currentSlug === $s['slug']; ?>
          <li class="<?= $subs ? 'has-sub' : '' ?>">
            <a href="<?= e(item_url($s)) ?>"<?= $active ? ' class="is-active" aria-current="page"' : '' ?>><?= e(tr($s, 'menu_title') ?: tr($s, 'title')) ?></a>
            <?php if ($subs): ?>
              <button class="sub-toggle" aria-expanded="false" aria-label="+"><span aria-hidden="true">▾</span></button>
              <ul class="sub">
                <?php foreach ($subs as $c): ?>
                  <li><a href="<?= e(item_url($c)) ?>"<?= $currentSlug === $c['slug'] ? ' class="is-active"' : '' ?>><?= e(tr($c, 'menu_title') ?: tr($c, 'title')) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?= $navLink('references', 'nav.references') ?>
        <?= $navLink('reviews', 'nav.reviews') ?>
        <?= $navLink('pricing', 'nav.pricing') ?>
        <li><a href="<?= e(route_url('contact')) ?>" class="btn btn--small"><?= te('nav.contact') ?></a></li>
        <li class="nav__mobile-extra">
          <a href="<?= e(tel_href(site('phone'))) ?>"><?= e(site('phone')) ?></a>
          <a href="<?= e($alt[$other]) ?>" hreflang="<?= $other ?>"><?= $other === 'en' ? 'English' : 'Česky' ?></a>
        </li>
      </ul>
    </nav>
  </div>
</header>

<main>
<?= $content ?>
</main>

<section class="service-strip">
  <div class="container service-strip__inner">
    <?php foreach ($services as $s): ?>
      <a href="<?= e(item_url($s)) ?>"><?= e(tr($s, 'title')) ?> <span aria-hidden="true">→</span></a>
    <?php endforeach; ?>
  </div>
</section>

<footer class="footer">
  <div class="container footer__grid">
    <div>
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="<?= e(site('name')) ?>" class="footer__logo" loading="lazy">
      <?php else: ?>
        <div class="logo logo--light"><span class="logo__mark" aria-hidden="true"></span><span><?= e(site('name')) ?></span></div>
      <?php endif; ?>
      <p><?= te('site.tagline') ?></p>
    </div>
    <div>
      <h4><?= te('footer.navigation') ?></h4>
      <ul>
        <li><a href="<?= e(route_url('references')) ?>"><?= te('nav.references') ?></a></li>
        <li><a href="<?= e(route_url('reviews')) ?>"><?= te('nav.reviews') ?></a></li>
        <li><a href="<?= e(route_url('pricing')) ?>"><?= te('nav.pricing') ?></a></li>
        <li><a href="<?= e(route_url('contact')) ?>"><?= te('nav.contact') ?></a></li>
      </ul>
    </div>
    <?php $ap = address_parts(); ?>
    <div<?= rdfa('vocab="https://schema.org/" typeof="HomeAndConstructionBusiness" resource="' . e(business_id()) . '"') ?>>
      <h4><?= te('footer.contact') ?></h4>
      <?php if (schema_on('rdfa')): ?><meta property="name" content="<?= e(site('name')) ?>"><link property="url" href="<?= e(abs_url(route_url('home'))) ?>"><?php endif; ?>
      <ul>
        <li><a href="<?= e(tel_href(site('phone'))) ?>"<?= rdfa('property="telephone" content="' . e(site('phone')) . '"') ?>><?= e(site('phone')) ?></a></li>
        <li><?= email_link(site('email')) ?></li>
        <li<?= rdfa('property="address" typeof="PostalAddress"') ?>>
          <span<?= rdfa('property="streetAddress"') ?>><?= e($ap['street']) ?></span><?php if ($ap['city'] !== ''): ?>,
          <span<?= rdfa('property="postalCode"') ?>><?= e($ap['zip']) ?></span>
          <span<?= rdfa('property="addressLocality"') ?>><?= e($ap['city']) ?></span><?php endif; ?>
          <?php if (schema_on('rdfa')): ?><meta property="addressCountry" content="CZ"><?php endif; ?>
        </li>
      </ul>
    </div>
  </div>
  <div class="container footer__bottom">
    <span>© <?= date('Y') ?> <?= e(site('owner')) ?> · <?= te('footer.ico') ?> <?= e(site('ico')) ?></span>
    <?php if (site('gdpr_url')): ?><a href="<?= e(site('gdpr_url')) ?>"><?= te('footer.privacy') ?></a><?php endif; ?>
  </div>
</footer>

<a href="<?= e(tel_href(site('phone'))) ?>" class="call-fab" aria-label="<?= te('contact.phone') ?>">
  <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
</a>

<div class="lightbox" hidden>
  <button class="lightbox__close" aria-label="<?= te('ui.close') ?>">×</button>
  <button class="lightbox__prev" aria-label="<?= te('ui.prev') ?>">‹</button>
  <figure><img alt=""><figcaption></figcaption></figure>
  <button class="lightbox__next" aria-label="<?= te('ui.next') ?>">›</button>
</div>

<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
