<?php
$metaDesc = t('home.meta_desc');
$concrete = category_by_slug('betonove-sterky');
$interior = $concrete ? subcategories((int) $concrete['id'], 'interior') : [];
$exterior = $concrete ? subcategories((int) $concrete['id'], 'exterior') : [];
$latest   = q("SELECT * FROM gallery_images WHERE in_references = 1 ORDER BY created_at DESC, id DESC LIMIT 8")->fetchAll();
$heroImg  = (props()['img.hero']['value_cs'] ?? '') ?: ($concrete['cover_image'] ?? null);
$whyBg    = image_url(props()['img.why_bg']['value_cs'] ?? '');
$icon     = image_url(props()['img.benefit_icon']['value_cs'] ?? '');
$ogImage  = $heroImg;

$cardGrid = function (array $items, string $class = 'cards'): string {
    $h = '<div class="' . $class . '">';
    foreach ($items as $c) {
        $h .= '<a class="card" href="' . e(item_url($c)) . '">' . img($c['cover_image'], tr($c, 'title'), 'card__img')
            . '<div class="card__body"><h3>' . e(tr($c, 'menu_title') ?: tr($c, 'title')) . '</h3><p>' . e(tr($c, 'intro')) . '</p></div></a>';
    }
    return $h . '</div>';
};
?>
<section class="hero">
  <div class="container hero__inner">
    <div class="hero__text">
      <p class="eyebrow"><?= te('home.eyebrow') ?></p>
      <h1><?= t('home.title') /* HTML z administrace */ ?></h1>
      <p class="lead"><?= te('home.lead') ?></p>
      <div class="hero__actions">
        <a href="<?= e(route_url('contact')) ?>" class="btn"><?= te('home.cta_primary') ?></a>
        <a href="<?= e(route_url('references')) ?>" class="btn btn--ghost"><?= te('home.cta_secondary') ?></a>
      </div>
    </div>
    <div class="hero__visual">
      <?= img($heroImg, tr($concrete, 'title'), 'hero__img', true) ?>
      <div class="hero__badge"><strong><?= te('home.badge_strong') ?></strong><span><?= te('home.badge_text') ?></span></div>
    </div>
  </div>
</section>
<script src="<?= e(asset('js/hero-light.js')) ?>" defer></script>

<section class="section">
  <div class="container">
    <div class="section__head">
      <h2><?= te('home.interior_title') ?></h2>
      <p><?= te('home.interior_text') ?></p>
    </div>
    <?= $cardGrid($interior) ?>
  </div>
</section>

<section class="section section--tint">
  <div class="container about">
    <div class="about__img"><?= img(props()['img.about']['value_cs'] ?? null, site('owner'), 'about__photo') ?></div>
    <div class="prose">
      <p class="eyebrow"><?= te('home.about_eyebrow') ?></p>
      <h2><?= e(site('owner')) ?></h2>
      <?= t('home.about_text') ?>
      <a href="<?= e(route_url('contact')) ?>" class="link-arrow"><?= te('home.about_link') ?></a>
    </div>
  </div>
</section>

<?php if ($exterior): ?>
<section class="section">
  <div class="container">
    <div class="section__head">
      <h2><?= te('home.exterior_title') ?></h2>
      <p><?= te('home.exterior_text') ?></p>
    </div>
    <?= $cardGrid($exterior, 'cards cards--wide') ?>
  </div>
</section>
<?php endif; ?>

<section class="section section--dark<?= $whyBg ? ' section--bg' : '' ?>"<?= $whyBg ? ' style="--bg:url(\'' . e($whyBg) . '\')"' : '' ?>>
  <div class="container">
    <div class="section__head"><h2><?= te('home.why_title') ?></h2></div>
    <div class="benefits">
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="benefit">
          <?php if ($icon): ?><img src="<?= e($icon) ?>" alt="" class="benefit__icon" loading="lazy"><?php else: ?><span class="benefit__num">0<?= $i ?></span><?php endif; ?>
          <h3><?= te("home.b{$i}_title") ?></h3>
          <p><?= te("home.b{$i}_text") ?></p>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<?php if ($latest): ?>
<section class="section">
  <div class="container">
    <div class="section__head section__head--row">
      <h2><?= te('home.latest_title') ?></h2>
      <a href="<?= e(route_url('references')) ?>" class="link-arrow"><?= te('home.latest_link') ?></a>
    </div>
    <?= gallery_html($latest, t('home.latest_title')) ?>
  </div>
</section>
<?php endif; ?>

<?php $fb = site('facebook'); $fbPosts = prop_bool('site.fb_feed') ? fb_posts(6) : []; ?>
<?php if ($fbPosts): /* příspěvky stažené serverem (Graph API) – návštěvník se k Facebooku nepřipojuje */ ?>
<section class="section section--tint">
  <div class="container">
    <div class="section__head section__head--row">
      <div>
        <h2><?= te('home.fb_title') ?></h2>
        <p class="muted"><?= te('home.fb_lead') ?></p>
      </div>
      <?php if ($fb !== ''): ?><a href="<?= e($fb) ?>" class="link-arrow" target="_blank" rel="noopener"><?= te('home.fb_open') ?></a><?php endif; ?>
    </div>
    <div class="fb-posts">
      <?php foreach ($fbPosts as $p): $url = $p['url'] ?: $fb; ?>
        <article class="fb-post">
          <?php if ($p['image']): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener" class="fb-post__img" tabindex="-1" aria-hidden="true"><img src="<?= e(image_url($p['image'])) ?>" alt="" loading="lazy" decoding="async"></a><?php endif; ?>
          <div class="fb-post__body">
            <?php if ($p['date']): ?><time datetime="<?= e($p['date']) ?>" class="fb-post__date"><?= e(date(lang() === 'en' ? 'j M Y' : 'j. n. Y', strtotime($p['date']))) ?></time><?php endif; ?>
            <?php if ($p['text'] !== ''): ?><p><?= e(fb_excerpt($p['text'])) ?></p><?php endif; ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="fb-post__more"><?= te('home.fb_more') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php elseif (prop_bool('site.fb_feed') && preg_match('~^https://(www\.|m\.)?facebook\.com/~', $fb)):
  // Bez tokenu (config.php → facebook): vložený plugin Facebooku u této stránky ukazuje jen záhlaví bez příspěvků,
  // proto místo prázdného rámečku výzva ke sledování. Příspěvky se zobrazí samy po doplnění tokenu (README → Facebook). ?>
<section class="section section--tint">
  <div class="container">
    <div class="fb-follow">
      <span class="fb-follow__icon" aria-hidden="true"><?= icon('facebook', 30) ?></span>
      <div class="fb-follow__text">
        <h2><?= te('home.fb_title') ?></h2>
        <p><?= te('home.fb_lead') ?></p>
      </div>
      <a href="<?= e($fb) ?>" class="btn" target="_blank" rel="noopener"><?= te('home.fb_open') ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require ROOT_DIR . '/includes/partials/cta.php'; ?>
