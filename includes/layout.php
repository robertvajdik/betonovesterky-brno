<?php
/** @var string $content @var string $title @var string $metaDesc @var string $current @var string $currentSlug @var array $alt */
$services     = services();
$concrete     = category_by_slug('betonove-sterky');
$concreteSubs = $concrete ? subcategories((int) $concrete['id']) : [];
$homeTitle    = trim(t('seo.home_title'));
$fullTitle    = $title ? $title . ' | ' . site('name')
              : (($homeTitle !== '' && $homeTitle !== 'seo.home_title') ? $homeTitle : site('name') . ' – ' . t('site.tagline'));
$logoFile     = (string) (props()['img.logo']['value_cs'] ?? '');
$logo         = image_url($logoFile);
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
<?php if (($kw = trim(t('seo.keywords'))) !== '' && $kw !== 'seo.keywords'): ?><meta name="keywords" content="<?= e($kw) ?>"><?php endif; ?>
<?php $is404 = $current === '404'; $private = !empty($noindex); ?>
<?php if ($is404 || $private || !prop_bool('seo.indexing')): ?><meta name="robots" content="noindex<?= $is404 ? ', follow' : '' ?>"><?php endif; ?>
<?php if (!$is404): ?>
<link rel="canonical" href="<?= e(abs_url($alt[lang()])) ?>">
<link rel="alternate" hreflang="cs" href="<?= e(abs_url($alt['cs'])) ?>">
<link rel="alternate" hreflang="en" href="<?= e(abs_url($alt['en'])) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(abs_url($alt['cs'])) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<?php $ogAbs = abs_image(($ogImage ?? null) ?: ((string) (props()['seo.og_image']['value_cs'] ?? '') ?: ((string) (props()['img.hero']['value_cs'] ?? '') ?: ($logoFile ?: null)))); if ($ogAbs): ?><meta property="og:image" content="<?= e($ogAbs) ?>">
<meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="<?= lang() === 'en' ? 'en_GB' : 'cs_CZ' ?>">
<?php if (!$is404): ?><meta property="og:url" content="<?= e(abs_url($alt[lang()])) ?>">
<?= $private ? '' : jsonld($current, abs_url($alt[lang()]), $fullTitle, $metaDesc, $ogAbs, $schema ?? []) ?>
<?php endif; ?>
<?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
<?php if ($touch = app_icon_url(180)): ?><link rel="apple-touch-icon" href="<?= e($touch) ?>"><?php endif; ?>
<link rel="manifest" href="<?= e(lang() === 'en' ? url('en/manifest.webmanifest') : url('manifest.webmanifest')) ?>">
<meta name="apple-mobile-web-app-title" content="<?= e(mb_substr(site('name'), 0, 20)) ?>">
<meta name="theme-color" content="#1f1e1c">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>

<div class="topbar">
  <div class="container topbar__inner">
    <?= email_link(site('email'), 'topbar__mail', true) ?>
    <a href="<?= e(tel_href(site('phone'))) ?>" class="topbar__phone"><?= icon('phone', 15) ?><span><?= e(site('phone')) ?></span></a>
    <?php if ($wa = whatsapp_url()): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="topbar__social" aria-label="WhatsApp" title="WhatsApp"><?= icon('whatsapp', 16) ?></a><?php endif; ?>
    <?php if (site('facebook')): ?><a href="<?= e(site('facebook')) ?>" target="_blank" rel="noopener" class="topbar__social" aria-label="Facebook"><?= icon('facebook', 16) ?></a><?php endif; ?>
    <?php if (site('instagram')): ?><a href="<?= e(site('instagram')) ?>" target="_blank" rel="noopener" class="topbar__social" aria-label="Instagram"><?= icon('instagram', 16) ?></a><?php endif; ?>
    <?php if (accounts_on()): $cu = customer(); ?>
      <a href="<?= e(route_url($cu ? 'account' : 'login')) ?>" class="topbar__account"><?= icon('user', 15) ?><span><?= te($cu ? 'nav.account' : 'nav.login') ?></span></a>
    <?php endif; ?>
    <a href="<?= e($alt[$other]) ?>" class="lang-switch" hreflang="<?= $other ?>" lang="<?= $other ?>"><?= $other === 'en' ? 'EN' : 'CZ' ?></a>
  </div>
</div>

<header class="header">
  <div class="container header__inner">
    <a href="<?= e(route_url('home')) ?>" class="logo">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="<?= e(site('name')) ?>" class="logo__img"<?= img_dims($logoFile) ?>>
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
          <a href="<?= e(tel_href(site('phone'))) ?>"><?= icon('mobile') ?> <?= e(site('phone')) ?></a>
          <?= email_link(site('email'), '', true) ?>
          <?php if (accounts_on()): ?><a href="<?= e(route_url(customer() ? 'account' : 'login')) ?>"><?= icon('user') ?> <?= te(customer() ? 'nav.account' : 'nav.login') ?></a><?php endif; ?>
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
        <img src="<?= e($logo) ?>" alt="<?= e(site('name')) ?>" class="footer__logo"<?= img_dims($logoFile) ?> loading="lazy">
      <?php else: ?>
        <div class="logo logo--light"><span class="logo__mark" aria-hidden="true"></span><span><?= e(site('name')) ?></span></div>
      <?php endif; ?>
      <p><?= te('site.tagline') ?></p>
    </div>
    <div>
      <h2 class="footer__title"><?= te('footer.navigation') ?></h2>
      <ul>
        <li><a href="<?= e(route_url('references')) ?>"><?= te('nav.references') ?></a></li>
        <li><a href="<?= e(route_url('reviews')) ?>"><?= te('nav.reviews') ?></a></li>
        <li><a href="<?= e(route_url('pricing')) ?>"><?= te('nav.pricing') ?></a></li>
        <li><a href="<?= e(route_url('contact')) ?>"><?= te('nav.contact') ?></a></li>
      </ul>
    </div>
    <?php $ap = address_parts(); ?>
    <div<?= rdfa('vocab="https://schema.org/" typeof="HomeAndConstructionBusiness" resource="' . e(business_id()) . '"') ?>>
      <h2 class="footer__title"><?= te('footer.contact') ?></h2>
      <?php if (schema_on('rdfa')): ?><meta property="name" content="<?= e(site('name')) ?>"><link property="url" href="<?= e(abs_url(route_url('home'))) ?>"><?php endif; ?>
      <ul>
        <li class="has-icon"><?= icon('phone', 16) ?><a href="<?= e(tel_href(site('phone'))) ?>"<?= rdfa('property="telephone" content="' . e(site('phone')) . '"') ?>><?= e(site('phone')) ?></a></li>
        <li class="has-icon"><?= icon('mail', 16) ?><?= email_link(site('email')) ?></li>
        <?php if ($wa): ?><li class="has-icon"><?= icon('whatsapp', 16) ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
        <li class="has-icon"<?= rdfa('property="address" typeof="PostalAddress"') ?>><?= icon('pin', 16) ?>
          <span><span<?= rdfa('property="streetAddress"') ?>><?= e($ap['street']) ?></span><?php if ($ap['city'] !== ''): ?>, <span<?= rdfa('property="postalCode"') ?>><?= e($ap['zip']) ?></span> <span<?= rdfa('property="addressLocality"') ?>><?= e($ap['city']) ?></span><?php endif; ?></span>
          <?php if (schema_on('rdfa')): ?><meta property="addressCountry" content="CZ"><?php endif; ?>
        </li>
      </ul>
    </div>
  </div>
  <div class="container footer__bottom">
    <span>© <?= date('Y') ?> <?= e(site('owner')) ?> · <?= te('footer.ico') ?> <?= e(site('ico')) ?></span>
    <a href="<?= e(privacy_url()) ?>"<?= $current === 'privacy' ? ' aria-current="page"' : '' ?>><?= te('footer.privacy') ?></a>
    <?php if ($gaId = ga_id()): ?><button type="button" class="link-btn footer__consent" data-consent-open><?= te('consent.settings') ?></button><?php endif; ?>
  </div>
</footer>

<a href="<?= e(tel_href(site('phone'))) ?>" class="call-fab" aria-label="<?= te('contact.phone') ?>">
  <?= icon('phone', 24) ?>
</a>

<?php if ($gaId): /* lišta souhlasu: Google Analytics se načte až po kliknutí na „Povolit měření“ */ ?>
<div class="consent" data-consent data-ga="<?= e($gaId) ?>" role="dialog" aria-live="polite" aria-label="<?= te('consent.title') ?>" hidden>
  <div class="consent__text"><strong><?= te('consent.title') ?></strong> <?= te('consent.text') ?> <a href="<?= e(privacy_url()) ?>"><?= te('consent.more') ?></a></div>
  <div class="consent__actions">
    <button type="button" class="btn btn--small btn--light" data-consent-choice="denied"><?= te('consent.reject') ?></button>
    <button type="button" class="btn btn--small" data-consent-choice="granted"><?= te('consent.accept') ?></button>
  </div>
</div>
<?php endif; ?>

<div class="lightbox" hidden>
  <button class="lightbox__close" aria-label="<?= te('ui.close') ?>">×</button>
  <button class="lightbox__prev" aria-label="<?= te('ui.prev') ?>">‹</button>
  <figure><img alt=""><figcaption></figcaption></figure>
  <button class="lightbox__next" aria-label="<?= te('ui.next') ?>">›</button>
</div>

<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
